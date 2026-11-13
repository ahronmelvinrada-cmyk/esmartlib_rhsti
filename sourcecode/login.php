  <?php require 'config.php';require_once 'Ui2026.PHP';
    ensure_teacher_schema($pdo);
    ensure_student_account_status_schema($pdo);
  ensure_user_email_schema($pdo);
  $libraryName=setting($pdo,'library_name','eSmartlib');
  $schoolName=setting($pdo,'school_name','RHSTI Library');
  $schoolLogo='assets/rhsti-school-logo.jpg';
  $registrationMessage='';
  $registrationSuccess=false;
  $registrationValues=['student_no'=>'','name'=>'','email'=>'','gender'=>'','track'=>'','strand'=>'','grade'=>'','section'=>''];
  if(empty($_SESSION['student_registration_token']))$_SESSION['student_registration_token']=bin2hex(random_bytes(32));
  $registrationToken=$_SESSION['student_registration_token'];

  /* ---------- Schema helpers (safe to run every time) ---------- */
  try{
    $c=$pdo->query("SHOW COLUMNS FROM users LIKE 'must_change'");
    if(!$c||!$c->fetch()) $pdo->exec("ALTER TABLE users ADD COLUMN must_change TINYINT(1) NOT NULL DEFAULT 0");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
      id INT AUTO_INCREMENT PRIMARY KEY,
      ip VARCHAR(45) NOT NULL,
      username VARCHAR(100) NOT NULL,
      tried_at DATETIME NOT NULL,
      KEY k_ip_user (ip,username,tried_at)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_login_locks (
      student_id INT NOT NULL PRIMARY KEY,
      failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
      locked_until BIGINT UNSIGNED DEFAULT NULL
    )");
  }catch(Exception $e){error_log('Login security schema setup failed: '.$e->getMessage());}

  /* NOTE: the old auto-seed of admin/admin123 and librarian/lib123 was removed on purpose.
    Create staff accounts directly in the database (see instructions). */

  $err=isset($_GET['deactivated'])?'This account has been deactivated. Please contact library staff.':'';
  $notice=isset($_GET['reset'])?'Password updated. You can now sign in with your new password.':'';
  $ip=$_SERVER['REMOTE_ADDR']??'';
  const MAX_TRIES=5;      // failed attempts allowed...
  const WINDOW_SEC=600;   // ...within this many seconds (10 minutes)

  if(isset($_POST['register_student'])){
    foreach($registrationValues as $key=>$value){
      $submittedValue=is_string($_POST[$key]??null)?$_POST[$key]:'';
      $registrationValues[$key]=$key==='student_no'?$submittedValue:trim($submittedValue);
    }
    $studentNo=$registrationValues['student_no'];
    $name=$registrationValues['name'];
    $email=$registrationValues['email'];
    $gender=$registrationValues['gender'];
    $track=$registrationValues['track'];
    $strand=$registrationValues['strand'];
    $grade=$registrationValues['grade'];
    $section=$registrationValues['section'];
    $registrationPassword=(string)($_POST['registration_password']??'');
    $registrationPasswordConfirm=(string)($_POST['registration_password_confirm']??'');
    $length=fn($value)=>function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value);

    if(!is_string($_POST['registration_token']??null)||!hash_equals($registrationToken,$_POST['registration_token'])){
      $registrationMessage='Your registration session expired. Refresh the page and try again.';
    }elseif($studentNo===''||$name===''){
      $registrationMessage='Student ID and full name are required.';
    }elseif(!preg_match('/\A[0-9]+\z/',$studentNo)){
      $registrationMessage='Student ID must contain numbers only, without dashes or symbols.';
    }elseif(!in_array($grade,['11','12'],true)){
      $registrationMessage='Choose Grade 11 or Grade 12.';
    }elseif($email!==''&&(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)){
      $registrationMessage='Enter a valid Gmail or email address.';
    }elseif(strlen($registrationPassword)<8){
      $registrationMessage='Password must be at least 8 characters.';
    }elseif(!preg_match('/[A-Za-z]/',$registrationPassword)||!preg_match('/\d/',$registrationPassword)){
      $registrationMessage='Password must contain at least one letter and one number.';
    }elseif($registrationPassword!==$registrationPasswordConfirm){
      $registrationMessage='The passwords do not match.';
    }elseif($gender!==''&&!in_array($gender,['Male','Female'],true)){
      $registrationMessage='Choose a valid gender.';
    }elseif($length($studentNo)>30||$length($name)>120||$length($gender)>10
      ||$length($track)>60||$length($strand)>60||$length($grade)>10||$length($section)>30){
      $registrationMessage='One or more fields exceed the allowed length.';
    }else{
      try{
        $pdo->beginTransaction();
        $emailAlreadyUsed=false;
        if($email!==''){
          $existingEmail=$pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?");
          $existingEmail->execute([$email]);
          $emailAlreadyUsed=(int)$existingEmail->fetchColumn()>0;
        }
        if($emailAlreadyUsed){
          $pdo->rollBack();
          $registrationMessage='That email address is already associated with an account.';
        }else{
          $insert=$pdo->prepare("INSERT INTO students(student_no,name,gender,track,strand,grade,section) VALUES(?,?,?,?,?,?,?)");
          $insert->execute([$studentNo,$name,$gender,$track,$strand,$grade,$section]);
          $studentId=(int)$pdo->lastInsertId();
          $createAccount=$pdo->prepare("INSERT INTO users(username,password,role,student_id,must_change,email) VALUES(?,?,'student',?,0,?)");
          $createAccount->execute([$studentNo,password_hash($registrationPassword,PASSWORD_DEFAULT),$studentId,$email!==''?$email:null]);
          $pdo->commit();
          $registrationMessage='Student registered successfully. Sign in using your Student ID and the password you created.';
          $registrationSuccess=true;
          $registrationValues=['student_no'=>'','name'=>'','email'=>'','gender'=>'','track'=>'','strand'=>'','grade'=>'','section'=>''];
          $_SESSION['student_registration_token']=bin2hex(random_bytes(32));
          $registrationToken=$_SESSION['student_registration_token'];
        }
      }catch(PDOException $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if(($e->errorInfo[1]??null)===1062){
          $registrationMessage='That Student ID is already registered or its username is already in use.';
        }else{
          error_log('Student registration failed: '.$e->getMessage());
          $registrationMessage='Could not register the student. Please try again.';
        }
      }
    }
  }elseif($_SERVER['REQUEST_METHOD']==='POST'){
    $user=trim((string)($_POST['username']??''));
    $pass=(string)($_POST['password']??'');
    $accountQuery=$pdo->prepare("SELECT * FROM users WHERE username=?");
    $accountQuery->execute([$user]);
    $u=$accountQuery->fetch();
    $student=null;
    $teacher=null;
    if($u&&$u['role']==='student'){
      $studentQuery=$pdo->prepare("SELECT * FROM students WHERE id=?");
      $studentQuery->execute([(int)$u['student_id']]);
      $student=$studentQuery->fetch();
    }elseif($u&&$u['role']==='teacher'){
      $teacherQuery=$pdo->prepare("SELECT * FROM teachers WHERE id=?");
      $teacherQuery->execute([(int)$u['teacher_id']]);
      $teacher=$teacherQuery->fetch();
    }

    $authenticatedUser=null;
    $isStudentAccount=$u&&$u['role']==='student';
    if($isStudentAccount){
      if(!$student||(int)$student['account_active']!==1){
        $err=$student?'This student account has been deactivated. Please contact library staff.':'Invalid username/Student ID or password.';
      }else{
        $pdo->prepare("INSERT INTO student_login_locks(student_id) VALUES(?) ON DUPLICATE KEY UPDATE student_id=VALUES(student_id)")
            ->execute([(int)$student['id']]);
        $lockQuery=$pdo->prepare("SELECT failed_attempts,locked_until FROM student_login_locks WHERE student_id=?");
        $lockQuery->execute([(int)$student['id']]);
        $lock=$lockQuery->fetch();
        $now=time();
        $lockedUntil=(int)($lock['locked_until']??0);
        if($lockedUntil>$now){
          $minutes=(int)ceil(($lockedUntil-$now)/60);
          $err='Too many failed attempts. Please wait '.$minutes.' minute'.($minutes===1?'':'s').' before trying again.';
        }else{
          $passwordMatches=password_verify($pass,$u['password']);
          if($passwordMatches){
            $pdo->prepare("UPDATE student_login_locks SET failed_attempts=0,locked_until=NULL WHERE student_id=?")
                ->execute([(int)$student['id']]);
            $authenticatedUser=$u;
          }else{
            $failures=min((int)$lock['failed_attempts']+1,14);
            $lockMinutes=$failures>=5?min($failures-4,10):0;
            $newLockedUntil=$lockMinutes>0?$now+($lockMinutes*60):null;
            $pdo->prepare("UPDATE student_login_locks SET failed_attempts=?,locked_until=? WHERE student_id=?")
                ->execute([$failures,$newLockedUntil,(int)$student['id']]);
            $err=$lockMinutes>0
              ?'Too many failed attempts. Please wait '.$lockMinutes.' minute'.($lockMinutes===1?'':'s').' before trying again.'
              :'Invalid username/Student ID or password.';
          }
        }
      }
    }else{
      if($u&&$u['role']==='teacher'&&(!$teacher||(int)$teacher['account_active']!==1)){
        $err=$teacher?'This teacher account has been deactivated. Please contact library staff.':'Invalid username or password.';
      }else{
        $since=date('Y-m-d H:i:s',time()-WINDOW_SEC);
        $cnt=$pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND username=? AND tried_at>=?");
        $cnt->execute([$ip,$user,$since]);
        if((int)$cnt->fetchColumn()>=MAX_TRIES){
          $err='Too many failed attempts. Please try again in 10 minutes.';
        }elseif($u&&password_verify($pass,$u['password'])){
          $authenticatedUser=$u;
        }else{
          $pdo->prepare("INSERT INTO login_attempts(ip,username,tried_at) VALUES(?,?,?)")
              ->execute([$ip,$user,date('Y-m-d H:i:s')]);
          $pdo->prepare("DELETE FROM login_attempts WHERE tried_at<?")
              ->execute([date('Y-m-d H:i:s',time()-86400)]);
          $err='Invalid username/Student ID or password.';
        }
      }
    }

    if($authenticatedUser){
      session_regenerate_id(true);
      $pdo->prepare("DELETE FROM login_attempts WHERE ip=? AND username=?")->execute([$ip,$user]);
      $_SESSION['u']=['id'=>$authenticatedUser['id'],'username'=>$authenticatedUser['username'],'role'=>$authenticatedUser['role'],'student_id'=>$authenticatedUser['student_id'],'teacher_id'=>$authenticatedUser['teacher_id']??null];
      if($authenticatedUser['role']!=='teacher'&&(int)($authenticatedUser['must_change']??0)===1)$_SESSION['must_change']=true;
      else unset($_SESSION['must_change']);
      if(!empty($_SESSION['must_change'])){header('Location: change_password.php');exit;}
      header('Location: '.($authenticatedUser['role']==='student'?'student.php':($authenticatedUser['role']==='teacher'?'teacher.php':'index.php')));exit;
    }
  }
  $authView=$registrationMessage!==''||($_GET['view']??'')==='register'?'register':'signin';
  ?>
  <!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=h($libraryName)?> | <?=h($schoolName)?> - Sign in</title>
  <meta name="description" content="Official library management system of The Ripple of Hope Skills and Technology Institute (RHSTI), for students, teachers, and library staff.">
  <?=pwa_head_assets()?>
  <script>try{var a=JSON.parse(localStorage.getItem('esl-appearance-v1')||'{}'),c=['green','blue','pink','teal','orange','purple','red','indigo','cyan','amber'],f=['jakarta','arial','verdana','georgia','tahoma','trebuchet'],s=['small','normal','large','x-large'];if(c.indexOf(a.accent)!==-1)document.documentElement.dataset.accent=a.accent;if(f.indexOf(a.fontFamily)!==-1)document.documentElement.dataset.fontFamily=a.fontFamily;if(s.indexOf(a.textSize)!==-1)document.documentElement.dataset.textSize=a.textSize;var t=localStorage.getItem('theme');if(t==='dark'||t==='light')document.documentElement.dataset.theme=t}catch(e){}</script>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=<?=filemtime(__DIR__.'/assets/style.css')?>">
  <style>
  body.lg{display:block;min-height:100vh;margin:0;background:var(--bg);font-family:var(--app-font-family,'Plus Jakarta Sans',system-ui,sans-serif)}
  .lg *{box-sizing:border-box}
  .lg .shell{display:grid;grid-template-columns:1.05fr 1fr;min-height:100vh}

  /* ---------- Left: brand panel ---------- */
  .lg .hero{position:relative;overflow:hidden;color:#fff;padding:48px 56px;display:flex;flex-direction:column;justify-content:space-between;
    background:radial-gradient(90% 70% at 0% 0%,var(--g) 0%,transparent 60%),radial-gradient(70% 60% at 100% 100%,var(--gd) 0%,transparent 65%),linear-gradient(160deg,var(--gd),var(--gd))}
  .lg .hero::before{content:"";position:absolute;inset:0;opacity:.07;pointer-events:none;
    background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:44px 44px;
    -webkit-mask-image:radial-gradient(80% 80% at 30% 30%,#000,transparent);mask-image:radial-gradient(80% 80% at 30% 30%,#000,transparent)}
  .lg .hero>*{position:relative}
  .lg .brand2{display:flex;align-items:center;gap:12px;font-weight:800;font-size:19px;letter-spacing:-.02em}
  .lg .brand2 i{width:44px;height:44px;border-radius:14px;background:var(--leaf);color:var(--gd);display:grid;place-items:center;font-style:normal;font-size:27px;font-weight:800;box-shadow:0 12px 26px -10px rgba(0,0,0,.6)}
  .lg .brand2 img{width:48px;height:48px;object-fit:contain;flex:none;border-radius:50%;background:#fff;box-shadow:0 8px 20px -10px rgba(0,0,0,.5)}
  .lg .brand2 small{display:block;font-weight:500;font-size:12.5px;color:rgba(255,255,255,.72);letter-spacing:0}
  .lg .hero h2{margin:0 0 14px;font-size:clamp(30px,3.6vw,46px);line-height:1.08;font-weight:800;letter-spacing:-.035em;max-width:520px}
  .lg .hero h2 span{color:var(--g)}
  .lg .hero p.lead{margin:0 0 30px;color:#bcd8c6;font-size:16px;max-width:460px;line-height:1.6}
  .lg .feats{display:grid;gap:12px;max-width:460px}
  .lg .feat{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:18px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(6px);font-weight:600;font-size:14.5px}
  .lg .feat svg{width:38px;height:38px;padding:9px;border-radius:12px;background:var(--leaf);color:var(--g);flex:none}
  .lg .feat small{display:block;font-weight:500;color:rgba(255,255,255,.72);font-size:12.5px;margin-top:1px}
  .lg .foot{color:rgba(255,255,255,.68);font-size:12.5px}

  /* ---------- Right: form ---------- */
  .lg .panel{position:relative;isolation:isolate;overflow:hidden;display:grid;place-items:center;padding:32px 24px;background:linear-gradient(145deg,var(--bg),var(--leaf) 58%,var(--bg))}
  .lg .panel::before,.lg .panel::after{position:absolute;z-index:-1;pointer-events:none;content:""}
  .lg .panel::before{
    top:-150px;right:-125px;width:340px;height:340px;border:1px solid var(--line);
    border-radius:50%;background:radial-gradient(circle,var(--leaf),transparent 68%);
    box-shadow:0 0 0 24px var(--bg),0 0 0 52px var(--line)
  }
  .lg .panel::after{
    bottom:0;left:0;width:260px;height:220px;
    background-image:radial-gradient(var(--g) 1.2px,transparent 1.2px);
    background-size:18px 18px;
    -webkit-mask-image:linear-gradient(45deg,#000,transparent 78%);
    mask-image:linear-gradient(45deg,#000,transparent 78%)
  }
  .lg .lcard{position:relative;z-index:1;width:100%;max-width:450px;margin:0;padding:36px 34px 30px;background:var(--card);border:1px solid var(--line);border-radius:26px;box-shadow:0 24px 70px -38px rgba(15,31,23,.36);backdrop-filter:blur(12px);display:block;animation:rise .5s ease both}
  @keyframes rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
  .lg .mbrand{display:none}
  .lg .lcard h1{margin:0 0 6px;font-size:30px;font-weight:800;letter-spacing:-.035em;color:var(--ink);line-height:1.15}
  .lg .sub{margin:0 0 26px;color:var(--mute);font-size:15px;line-height:1.5}
  .lg .err{display:flex;align-items:center;gap:10px;margin:0 0 18px;padding:12px 14px;border-radius:14px;background:#fde8e6;border:1px solid #f4c7c2;color:#b42318;font-weight:600;font-size:14px;animation:shake .4s ease}
  .lg .err svg{width:20px;height:20px;flex:none}
  @keyframes shake{20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}
  .lg label.f{display:block;margin:0 0 16px}
  .lg label.f>span{display:block;margin:0 0 7px;font-size:13px;font-weight:700;color:var(--ink)}
  .lg .inp{position:relative}
  .lg .inp>svg.l{position:absolute;left:16px;top:50%;transform:translateY(-50%);width:20px;height:20px;color:#8a998f;pointer-events:none;transition:color .2s}
  .lg .inp input{width:100%;height:54px;margin:0;padding:0 16px 0 48px;font:inherit;font-size:15px;font-weight:500;color:var(--ink);background:var(--card);border:1.5px solid var(--line);border-radius:16px;transition:border-color .2s,box-shadow .2s}
  .lg .inp input::placeholder{color:#9aa9a0;font-weight:400}
  .lg .inp input:hover{border-color:var(--g)}
  .lg .inp input:focus{outline:0;border-color:var(--g);box-shadow:0 0 0 5px var(--leaf)}
  .lg .inp:focus-within>svg.l{color:var(--g)}
  .lg .inp.pw input{padding-right:52px}
  .lg .eye{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:40px;height:40px;margin:0;padding:0;border:0;border-radius:12px;background:transparent;color:#667467;display:grid;place-items:center;cursor:pointer;box-shadow:none}
  .lg .eye:hover{background:#eef3ef;color:#16231b}
  .lg .eye svg{width:20px;height:20px}
  .lg .go{width:100%;height:56px;margin:8px 0 0;border:0;border-radius:16px;font:inherit;font-size:16px;font-weight:800;color:#fff;cursor:pointer;
    display:flex;align-items:center;justify-content:center;gap:10px;background:var(--g);box-shadow:0 16px 30px -14px var(--g);transition:transform .15s,box-shadow .2s,filter .2s}
  .lg .go:hover{transform:translateY(-2px);filter:brightness(.92);box-shadow:0 20px 34px -14px var(--g)}
  .lg .go:active{transform:none}
  .lg .go svg{width:20px;height:20px;transition:transform .2s}
  .lg .go:hover svg{transform:translateX(4px)}
  .lg .go.busy{pointer-events:none;opacity:.85}
  .lg .school-note{margin:18px 0 0;font-size:12.5px;color:var(--mute);line-height:1.55;text-align:center}
  .lg .forgot-link{margin:12px 0 0;text-align:center;font-size:13px;font-weight:700}
  .lg .forgot-link a{color:var(--g);text-decoration:none}
  .lg .forgot-link a:hover{text-decoration:underline}
  .lg .school-note b{color:var(--gd)}
  .lg .auth-tabs{display:grid;grid-template-columns:1fr 1fr;gap:4px;padding:4px;margin:0 0 22px;background:#eef3ef;border-radius:14px}
  .lg .auth-tabs a{height:42px;margin:0;border:0;border-radius:11px;background:transparent;color:#667467;font:inherit;font-size:14px;font-weight:700;text-decoration:none;display:grid;place-items:center}
  .lg .auth-tabs a[aria-selected="true"]{background:var(--card);color:var(--gd);box-shadow:0 2px 8px rgba(15,31,23,.1)}
  .lg .auth-view[hidden]{display:none}
  .lg .auth-title{margin:0 0 5px;font-size:24px;font-weight:800;letter-spacing:-.03em;color:var(--ink)}
  .lg .auth-description{margin:0 0 20px;color:var(--mute);font-size:14px;line-height:1.5}
  .lg .reg-msg{margin:0 0 16px;padding:10px 12px;border-radius:12px;font-size:13px;font-weight:600;line-height:1.5}
  .lg .reg-msg.success{background:#ecfdf5;border:1px solid #bbf7d0;color:#14532d}
  .lg .reg-msg.error{background:#fff1f2;border:1px solid #fecdd3;color:#9f1239}
  .lg .register-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  .lg .register-form label{display:block;min-width:0;font-size:12px;font-weight:700;color:var(--ink)}
  .lg .register-form label.wide,.lg .register-form button{grid-column:1/-1}
  .lg .register-form input,.lg .register-form select{display:block;width:100%;height:46px;margin:5px 0 0;padding:0 12px;font:inherit;font-size:14px;color:var(--ink);background:var(--card);border:1.5px solid var(--line);border-radius:12px}
  .lg .register-form input[aria-invalid="true"]{border-color:#dc2626}
  .lg .register-form input[aria-invalid="true"]:focus{border-color:#dc2626;box-shadow:0 0 0 4px rgba(220,38,38,.12)}
  .lg .register-student-id-error{margin:5px 0 0;color:#b91c1c;font-size:12px;font-weight:600}
  .lg .register-student-id-error[hidden]{display:none}
  .lg .register-password-wrap{position:relative;display:block;margin-top:5px}
  .lg .register-password-wrap input{margin:0;padding-right:48px}
  .lg .register-form input:focus,.lg .register-form select:focus{outline:0;border-color:var(--g);box-shadow:0 0 0 4px var(--leaf)}
  .lg .register-form button{height:50px;margin:4px 0 0;border:0;border-radius:14px;background:var(--g);color:#fff;font:inherit;font-weight:800;cursor:pointer;box-shadow:0 12px 24px -12px var(--g)}
  .lg .register-form button:hover{filter:brightness(.92)}
  .lg .register-form button.is-loading{display:flex;align-items:center;justify-content:center;gap:9px;opacity:.88;cursor:wait}
  .lg .register-spinner{width:17px;height:17px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;animation:register-spin .7s linear infinite}
  @keyframes register-spin{to{transform:rotate(360deg)}}
  .lg .register-form .register-password-wrap .eye{width:40px;height:40px;margin:0;padding:0;border:0;border-radius:12px;background:transparent;color:#667467;font:inherit;box-shadow:none}
  .lg .register-form .register-password-wrap .eye:hover{background:#eef3ef;color:#16231b;filter:none}
  [data-theme="dark"] .lg .auth-tabs{background:#101713}
  [data-theme="dark"] .lg .auth-tabs a{color:#a6b5aa}
  [data-theme="dark"] .lg .auth-tabs a[aria-selected="true"]{background:var(--leaf);color:var(--g)}

  /* ---------- Responsive ---------- */
  @media(min-width:641px) and (max-width:900px){
    .lg .shell{grid-template-columns:.9fr 1.1fr}
    .lg .hero{padding:32px 28px}
    .lg .hero h2{font-size:clamp(26px,3vw,34px)}
    .lg .hero p.lead{font-size:14px;margin-bottom:20px}
    .lg .feats{gap:8px}
    .lg .feat{gap:10px;padding:10px 12px;font-size:13px}
    .lg .feat svg{width:34px;height:34px}
    .lg .panel{padding:24px 20px}
    .lg .lcard{padding:30px 26px 26px}
  }
  @media(max-width:640px){
    .lg .register-form{grid-template-columns:1fr}
    .lg .register-form label.wide{grid-column:auto}
    .lg .shell{grid-template-columns:1fr}
    .lg .hero{display:none}
    .lg .panel{
      min-height:100svh;align-items:start;padding:clamp(22px,7vh,54px) 20px 28px
    }
    .lg .lcard{position:relative;z-index:1;max-width:420px;margin:0 auto;padding:26px 22px 22px;border-radius:24px}
    .lg .mbrand{display:flex;align-items:center;gap:12px;margin:0 0 clamp(26px,6vh,48px);font-weight:800;font-size:19px;letter-spacing:-.02em;color:var(--gd)}
    .lg .mbrand i{width:48px;height:48px;border-radius:16px;background:var(--g);color:#fff;display:grid;place-items:center;font-style:normal;font-size:25px;font-weight:800;box-shadow:0 12px 24px -10px var(--g);flex:none}
    .lg .mbrand img{width:52px;height:52px;object-fit:contain;flex:none;border-radius:50%;background:#fff;box-shadow:0 8px 20px -10px rgba(15,61,38,.3)}
    .lg .mbrand small{display:block;font-weight:500;font-size:12.5px;color:#667467;letter-spacing:0;line-height:1.35}
    .lg .lcard h1{font-size:clamp(26px,7.4vw,31px);line-height:1.12}
    .lg .sub{margin-bottom:26px;font-size:15px}
    .lg label.f{margin-bottom:18px}
    .lg label.f>span{font-size:13px}
    .lg .inp input{height:58px;border-radius:17px;font-size:16px}
    .lg .eye{width:44px;height:44px}
    .lg .register-form .register-password-wrap .eye{width:44px;height:44px}
    .lg .go{height:58px;margin-top:8px;border-radius:17px}
  }
  @media(max-width:380px){
    .lg .panel{padding-right:16px;padding-left:16px}
    .lg .mbrand{margin-bottom:28px}
  }
  [data-theme="dark"] .lg .lcard{box-shadow:0 24px 70px -38px rgba(0,0,0,.8)}
  @media(prefers-reduced-motion:reduce){.lg *{animation:none!important;transition:none!important}}
  </style></head>
  <body class="lg">
  <div class="shell">

    <!-- Brand panel -->
    <section class="hero">
      <div class="brand2"><img src="<?=h($schoolLogo)?>" alt="RHSTI school logo"><div><?=h($libraryName)?> <small><?=h($schoolName)?></small></div></div>
      <div>
        <h2>Your school library, <span>one scan away.</span></h2>
        <p class="lead">Borrow, return and time-in with a QR code. Everything the library needs, in one clean dashboard.</p>
        <div class="feats">
          <div class="feat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M4 12h16"/></svg><div>QR borrow &amp; return<small>Scan the student, then the book. Done.</small></div></div>
          <div class="feat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><div>Library attendance<small>Automatic time-in and time-out.</small></div></div>
          <div class="feat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg><div>Live dashboard<small>Overdue alerts and weekly activity.</small></div></div>
        </div>
      </div>
      <div class="foot">&copy; <?=date('Y')?> The Ripple of Hope Skills and Technology Institute Library</div>
    </section>

    <!-- Form -->
    <section class="panel">
      <div class="lcard">
        <div class="auth-tabs" role="tablist" aria-label="Account options">
          <a role="tab" data-auth-tab="signin" href="?view=signin" aria-controls="signin-view" aria-selected="<?=$authView==='signin'?'true':'false'?>">Sign in</a>
          <a role="tab" data-auth-tab="register" href="?view=register" aria-controls="register-view" aria-selected="<?=$authView==='register'?'true':'false'?>">Register</a>
        </div>
      <form method="post" class="signin-form auth-view" id="signin-view" role="tabpanel" autocomplete="on" <?=$authView==='signin'?'':'hidden'?>>
        <div class="mbrand"><img src="<?=h($schoolLogo)?>" alt="RHSTI school logo"><div><?=h($libraryName)?><small><?=h($schoolName)?></small></div></div>
        <h1><?=h($libraryName)?></h1>
        <p class="sub"><?=h($schoolName)?> &middot; Sign in with your school library account.</p>
        <?php if($notice!==''):?><p class="reg-msg success" role="status"><?=h($notice)?></p><?php endif;?>
        <?php if($err)echo '<div class="err" role="alert"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg><span>'.h($err).'</span></div>';?>

        <label class="f"><span>Student ID</span>
          <div class="inp"><svg class="l" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
          <input name="username" placeholder="Enter your student ID" value="<?=h($_POST['username']??'')?>" autocomplete="username" required <?=empty($_POST['username'])?'autofocus':''?>></div></label>

        <label class="f"><span>Password</span>
          <div class="inp pw"><svg class="l" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          <input id="pw" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required <?=!empty($_POST['username'])?'autofocus':''?>>
          <button type="button" class="eye" id="eye" aria-label="Show password"><svg id="eyei" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></label>

        <button class="go" id="go">Sign in <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
        <p class="forgot-link"><a href="forgot_password.php">Forgot password?</a></p>

        <p class="school-note">
          Official library system of <b>The Ripple of Hope Skills and Technology Institute </b>.
          For enrolled students and library staff only. Never share your password with anyone.
        </p>
      </form>
        <div class="auth-view" id="register-view" role="tabpanel" <?=$authView==='register'?'':'hidden'?>>
        <h2 class="auth-title">Create student account</h2>
        <p class="auth-description">Enter your school details and create a password for your account.</p>
        <?php if($registrationMessage!==''):?>
          <p class="reg-msg <?=$registrationSuccess?'success':'error'?>" role="<?=$registrationSuccess?'status':'alert'?>"><?=h($registrationMessage)?></p>
        <?php endif;?>
        <form method="post" class="register-form" autocomplete="on">
          <input type="hidden" name="registration_token" value="<?=h($registrationToken)?>">
          <label>Student ID Number<input id="register-student-id" name="student_no" inputmode="numeric" pattern="[0-9]+" title="Use numbers only, without dashes or symbols." placeholder="Enter your Student ID number" maxlength="30" value="<?=h($registrationValues['student_no'])?>" aria-invalid="<?=preg_match('/\A[0-9]+\z/',$registrationValues['student_no'])||$registrationValues['student_no']===''?'false':'true'?>" aria-describedby="register-student-id-error" required><span class="register-student-id-error" id="register-student-id-error" <?=preg_match('/\A[0-9]+\z/',$registrationValues['student_no'])||$registrationValues['student_no']===''?'hidden':''?>>Numbers only; no dashes or symbols.</span></label>
          <label>Full name<input name="name" maxlength="120" value="<?=h($registrationValues['name'])?>" placeholder="Last name, First name, Middle name" required></label>
          <label class="wide">Gmail / Email<input type="email" name="email" maxlength="254" value="<?=h($registrationValues['email'])?>" placeholder="" autocomplete="email"></label>
          <label>Gender<select name="gender"><option value="">Select</option><option value="Male" <?=$registrationValues['gender']==='Male'?'selected':''?>>Male</option><option value="Female" <?=$registrationValues['gender']==='Female'?'selected':''?>>Female</option></select></label>
          <label>Grade<select name="grade" required><option value="">Select Grade</option><option value="11" <?=$registrationValues['grade']==='11'?'selected':''?>>11</option><option value="12" <?=$registrationValues['grade']==='12'?'selected':''?>>12</option></select></label>
          <label>Track<input name="track" maxlength="60" value="<?=h($registrationValues['track'])?>"></label>
          <label>Strand<input name="strand" maxlength="60" value="<?=h($registrationValues['strand'])?>"></label>
          <label class="wide">Section<input name="section" maxlength="30" value="<?=h($registrationValues['section'])?>"></label>
          <label>Password<span class="register-password-wrap"><input id="register-pw" name="registration_password" type="password" minlength="8" autocomplete="new-password" required><button type="button" class="eye" data-password-toggle="register-pw" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
          <label>Confirm password<span class="register-password-wrap"><input id="register-pw-confirm" name="registration_password_confirm" type="password" minlength="8" autocomplete="new-password" required><button type="button" class="eye" data-password-toggle="register-pw-confirm" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
          <p class="auth-description register-password-hint">Use at least 8 characters, including a letter and a number.</p>
          <button type="submit" name="register_student" value="1"><span>Register Student</span></button>
        </form>
        </div>
      </div>
    </section>
  </div>
  <script src="assets/text-size.js?v=<?=filemtime(__DIR__.'/assets/text-size.js')?>"></script>
  <script>
  (function(){
    var pw=document.getElementById("pw"),eye=document.getElementById("eye"),open='<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        shut='<path d="M3 3l18 18M10.6 6.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4M6.5 6.9C3.8 8.7 2 12 2 12s3.5 7 10 7c1.6 0 3-.4 4.3-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>';
    function bindPasswordToggle(input,button,icon){
      button.addEventListener("click",function(){
        var show=input.type==="password";
        input.type=show?"text":"password";
        icon.innerHTML=show?shut:open;
        button.setAttribute("aria-label",show?"Hide password":"Show password");
        input.focus();
      });
    }
    bindPasswordToggle(pw,eye,document.getElementById("eyei"));
    document.querySelectorAll("[data-password-toggle]").forEach(function(button){
      bindPasswordToggle(document.getElementById(button.dataset.passwordToggle),button,button.querySelector("svg"));
    });
    document.querySelectorAll("[data-auth-tab]").forEach(function(tab){
      tab.addEventListener("click",function(event){
        event.preventDefault();
        var selected=tab.dataset.authTab;
        document.querySelectorAll("[data-auth-tab]").forEach(function(item){item.setAttribute("aria-selected",item===tab?"true":"false");});
        document.querySelectorAll(".auth-view").forEach(function(view){view.hidden=view.id!==(selected==="signin"?"signin-view":"register-view");});
      });
    });
    var registerForm=document.querySelector("form.register-form");
    var studentIdInput=document.getElementById("register-student-id");
    var studentIdError=document.getElementById("register-student-id-error");
    function validateStudentId(){
      var invalid=studentIdInput.value!==""&&!/^[0-9]+$/.test(studentIdInput.value);
      studentIdInput.setAttribute("aria-invalid",invalid?"true":"false");
      studentIdError.hidden=!invalid;
      return !invalid;
    }
    studentIdInput.addEventListener("input",validateStudentId);
    studentIdInput.addEventListener("blur",validateStudentId);
    registerForm.addEventListener("submit",function(event){
      if(!validateStudentId()){event.preventDefault();studentIdInput.focus();return;}
      if(registerForm.dataset.submitting==="true"){event.preventDefault();return;}
      registerForm.dataset.submitting="true";
      registerForm.setAttribute("aria-busy","true");
      var button=registerForm.querySelector('button[type="submit"]');
      button.classList.add("is-loading");
      button.setAttribute("aria-label","Registering student");
      button.innerHTML='<span class="register-spinner" aria-hidden="true"></span><span>Registering...</span>';
    });
    document.querySelector("form.signin-form").addEventListener("submit",function(){var g=document.getElementById("go");g.classList.add("busy");g.firstChild.nodeValue="Signing in… ";});
  })();
  </script>
  </body></html>
