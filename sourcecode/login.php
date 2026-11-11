<?php require 'config.php';require_once 'Ui2026.PHP';
ensure_student_account_status_schema($pdo);
$libraryName=setting($pdo,'library_name','eSmartlib');
$schoolName=setting($pdo,'school_name','RHSTI Library');
$schoolLogo='assets/rhsti-school-logo.jpg';
$registrationMessage='';
$registrationSuccess=false;
$registrationValues=['student_no'=>'','name'=>'','gender'=>'','track'=>'','strand'=>'','grade'=>'','section'=>''];
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
<?php require 'config.php';require_once 'Ui2026.PHP';
ensure_student_account_status_schema($pdo);
$libraryName=setting($pdo,'library_name','eSmartlib');
$schoolName=setting($pdo,'school_name','RHSTI Library');
$schoolLogo='assets/rhsti-school-logo.jpg';
$registrationMessage='';
$registrationSuccess=false;
$registrationValues=['student_no'=>'','name'=>'','gender'=>'','track'=>'','strand'=>'','grade'=>'','section'=>''];
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

$err=isset($_GET['deactivated'])?'This student account has been deactivated. Please contact library staff.':'';
$notice=isset($_GET['reset'])?'Password updated. You can now sign in with your new password.':'';
$ip=$_SERVER['REMOTE_ADDR']??'';
const MAX_TRIES=5;      // failed attempts allowed...
const WINDOW_SEC=600;   // ...within this many seconds (10 minutes)

if(isset($_POST['register_student'])){
  foreach($registrationValues as $key=>$value){
    $registrationValues[$key]=trim(is_string($_POST[$key]??null)?$_POST[$key]:'');
  }
  $studentNo=$registrationValues['student_no'];
  $name=$registrationValues['name'];
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
      $insert=$pdo->prepare("INSERT INTO students(student_no,name,gender,track,strand,grade,section,account_active) VALUES(?,?,?,?,?,?,?,1)");
      $insert->execute([$studentNo,$name,$gender,$track,$strand,$grade,$section]);
      $studentId=(int)$pdo->lastInsertId();
      $createAccount=$pdo->prepare("INSERT INTO users(username,password,role,student_id,must_change) VALUES(?,?,'student',?,0)");
      $createAccount->execute([$studentNo,password_hash($registrationPassword,PASSWORD_DEFAULT),$studentId]);
      $pdo->commit();
      $registrationMessage='Student registered successfully. Sign in using your Student ID and the password you created.';
      $registrationSuccess=true;
      $registrationValues=['student_no'=>'','name'=>'','gender'=>'','track'=>'','strand'=>'','grade'=>'','section'=>''];
      $_SESSION['student_registration_token']=bin2hex(random_bytes(32));
      $registrationToken=$_SESSION['student_registration_token'];
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
  if($u&&$u['role']==='student'){
    $studentQuery=$pdo->prepare("SELECT * FROM students WHERE id=?");
    $studentQuery->execute([(int)$u['student_id']]);
    $student=$studentQuery->fetch();
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

  if($authenticatedUser){
    session_regenerate_id(true);
    $pdo->prepare("DELETE FROM login_attempts WHERE ip=? AND username=?")->execute([$ip,$user]);
    $_SESSION['u']=['id'=>$authenticatedUser['id'],'username'=>$authenticatedUser['username'],'role'=>$authenticatedUser['role'],'student_id'=>$authenticatedUser['student_id']];
    unset($_SESSION['must_change']);
    header('Location: '.($authenticatedUser['role']=='student'?'student.php':'index.php'));exit;
  }
}
$authView=$registrationMessage!==''||($_GET['view']??'')==='register'?'register':'signin';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($libraryName)?> | <?=h($schoolName)?> - Sign in</title>
<meta name="description" content="Official library management system of The Ripple of Hope Skills and Technology Institute (RHSTI). For enrolled students and library staff only.">
<?=pwa_head_assets()?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?=filemtime(__DIR__.'/assets/style.css')?>">
<style>
body.lg{display:block;min-height:100vh;margin:0;background:#f4f7f3;font-family:'Plus Jakarta Sans',system-ui,sans-serif}
.lg *{box-sizing:border-box}
.lg .shell{display:grid;grid-template-columns:1.05fr 1fr;min-height:100vh}

/* ---------- Left: brand panel ---------- */
.lg .hero{position:relative;overflow:hidden;color:#fff;padding:48px 56px;display:flex;flex-direction:column;justify-content:space-between;
  background:radial-gradient(90% 70% at 0% 0%,#2a9d5c 0%,transparent 60%),radial-gradient(70% 60% at 100% 100%,#14653a 0%,transparent 65%),linear-gradient(160deg,#0f3d26,#092a19)}
.lg .hero::before{content:"";position:absolute;inset:0;opacity:.07;pointer-events:none;
  background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:44px 44px;
  -webkit-mask-image:radial-gradient(80% 80% at 30% 30%,#000,transparent);mask-image:radial-gradient(80% 80% at 30% 30%,#000,transparent)}
.lg .hero>*{position:relative}
.lg .brand2{display:flex;align-items:center;gap:12px;font-weight:800;font-size:19px;letter-spacing:-.02em}
.lg .brand2 i{width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,#fff,#d4f0de);color:#0f3d26;display:grid;place-items:center;font-style:normal;font-size:27px;font-weight:800;box-shadow:0 12px 26px -10px rgba(0,0,0,.6)}
.lg .brand2 img{width:48px;height:48px;object-fit:contain;flex:none;border-radius:50%;background:#fff;box-shadow:0 8px 20px -10px rgba(0,0,0,.5)}
.lg .brand2 small{display:block;font-weight:500;font-size:12.5px;color:#a9d1b7;letter-spacing:0}
.lg .hero h2{margin:0 0 14px;font-size:clamp(30px,3.6vw,46px);line-height:1.08;font-weight:800;letter-spacing:-.035em;max-width:520px}
.lg .hero h2 span{color:#7ee2a8}
.lg .hero p.lead{margin:0 0 30px;color:#bcd8c6;font-size:16px;max-width:460px;line-height:1.6}
.lg .feats{display:grid;gap:12px;max-width:460px}
.lg .feat{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:18px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(6px);font-weight:600;font-size:14.5px}
.lg .feat svg{width:38px;height:38px;padding:9px;border-radius:12px;background:rgba(126,226,168,.16);color:#7ee2a8;flex:none}
.lg .feat small{display:block;font-weight:500;color:#a9d1b7;font-size:12.5px;margin-top:1px}
.lg .foot{color:#8fbba0;font-size:12.5px}

/* ---------- Right: form ---------- */
.lg .panel{position:relative;isolation:isolate;overflow:hidden;display:grid;place-items:center;padding:32px 24px;background:linear-gradient(145deg,#f5faf6,#edf5ef 58%,#f8fbf8)}
.lg .panel::before,.lg .panel::after{position:absolute;z-index:-1;pointer-events:none;content:""}
.lg .panel::before{
  top:-150px;right:-125px;width:340px;height:340px;border:1px solid rgba(31,122,70,.1);
  border-radius:50%;background:radial-gradient(circle,rgba(42,157,92,.08),transparent 68%);
  box-shadow:0 0 0 24px rgba(31,122,70,.035),0 0 0 52px rgba(31,122,70,.022)
}
.lg .panel::after{
  bottom:0;left:0;width:260px;height:220px;
  background-image:radial-gradient(rgba(31,122,70,.14) 1.2px,transparent 1.2px);
  background-size:18px 18px;
  -webkit-mask-image:linear-gradient(45deg,#000,transparent 78%);
  mask-image:linear-gradient(45deg,#000,transparent 78%)
}
.lg .lcard{position:relative;z-index:1;width:100%;max-width:450px;margin:0;padding:36px 34px 30px;background:rgba(255,255,255,.92);border:1px solid rgba(31,122,70,.1);border-radius:26px;box-shadow:0 24px 70px -38px rgba(15,61,38,.36);backdrop-filter:blur(12px);display:block;animation:rise .5s ease both}
@keyframes rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.lg .mbrand{display:none}
.lg .lcard h1{margin:0 0 6px;font-size:30px;font-weight:800;letter-spacing:-.035em;color:#16231b;line-height:1.15}
.lg .sub{margin:0 0 26px;color:#667467;font-size:15px;line-height:1.5}
.lg .err{display:flex;align-items:center;gap:10px;margin:0 0 18px;padding:12px 14px;border-radius:14px;background:#fde8e6;border:1px solid #f4c7c2;color:#b42318;font-weight:600;font-size:14px;animation:shake .4s ease}
.lg .err svg{width:20px;height:20px;flex:none}
@keyframes shake{20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}
.lg label.f{display:block;margin:0 0 16px}
.lg label.f>span{display:block;margin:0 0 7px;font-size:13px;font-weight:700;color:#16231b}
.lg .inp{position:relative}
.lg .inp>svg.l{position:absolute;left:16px;top:50%;transform:translateY(-50%);width:20px;height:20px;color:#8a998f;pointer-events:none;transition:color .2s}
.lg .inp input{width:100%;height:54px;margin:0;padding:0 16px 0 48px;font:inherit;font-size:15px;font-weight:500;color:#16231b;background:#fff;border:1.5px solid #dde6dd;border-radius:16px;transition:border-color .2s,box-shadow .2s}
.lg .inp input::placeholder{color:#9aa9a0;font-weight:400}
.lg .inp input:hover{border-color:#c4d4c8}
.lg .inp input:focus{outline:0;border-color:#1f7a46;box-shadow:0 0 0 5px rgba(31,122,70,.14)}
.lg .inp:focus-within>svg.l{color:#1f7a46}
.lg .inp.pw input{padding-right:52px}
.lg .eye{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:40px;height:40px;margin:0;padding:0;border:0;border-radius:12px;background:transparent;color:#667467;display:grid;place-items:center;cursor:pointer;box-shadow:none}
.lg .eye:hover{background:#eef3ef;color:#16231b}
.lg .eye svg{width:20px;height:20px}
.lg .go{width:100%;height:56px;margin:8px 0 0;border:0;border-radius:16px;font:inherit;font-size:16px;font-weight:800;color:#fff;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(135deg,#2a9d5c,#1f7a46);box-shadow:0 16px 30px -14px #1f7a46;transition:transform .15s,box-shadow .2s,filter .2s}
.lg .go:hover{transform:translateY(-2px);filter:brightness(1.06);box-shadow:0 20px 34px -14px #1f7a46}
.lg .go:active{transform:none}
.lg .go svg{width:20px;height:20px;transition:transform .2s}
.lg .go:hover svg{transform:translateX(4px)}
.lg .go.busy{pointer-events:none;opacity:.85}
.lg .school-note{margin:18px 0 0;font-size:12.5px;color:#667467;line-height:1.55;text-align:center}
.lg .forgot-link{margin:12px 0 0;text-align:center;font-size:13px;font-weight:700}
.lg .forgot-link a{color:#1f7a46;text-decoration:none}
.lg .forgot-link a:hover{text-decoration:underline}
.lg .school-note b{color:#0f3d26}
.lg .auth-tabs{display:grid;grid-template-columns:1fr 1fr;gap:4px;padding:4px;margin:0 0 22px;background:#eef3ef;border-radius:14px}
.lg .auth-tabs a{height:42px;margin:0;border:0;border-radius:11px;background:transparent;color:#667467;font:inherit;font-size:14px;font-weight:700;text-decoration:none;display:grid;place-items:center}
.lg .auth-tabs a[aria-selected="true"]{background:#fff;color:#0f3d26;box-shadow:0 2px 8px rgba(15,61,38,.1)}
.lg .auth-view[hidden]{display:none}
.lg .auth-title{margin:0 0 5px;font-size:24px;font-weight:800;letter-spacing:-.03em;color:#16231b}
.lg .auth-description{margin:0 0 20px;color:#667467;font-size:14px;line-height:1.5}
.lg .reg-msg{margin:0 0 16px;padding:10px 12px;border-radius:12px;font-size:13px;font-weight:600;line-height:1.5}
.lg .reg-msg.success{background:#ecfdf5;border:1px solid #bbf7d0;color:#14532d}
.lg .reg-msg.error{background:#fff1f2;border:1px solid #fecdd3;color:#9f1239}
.lg .register-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.lg .register-form label{display:block;min-width:0;font-size:12px;font-weight:700;color:#16231b}
.lg .register-form label.wide,.lg .register-form button{grid-column:1/-1}
.lg .register-form input,.lg .register-form select{display:block;width:100%;height:46px;margin:5px 0 0;padding:0 12px;font:inherit;font-size:14px;color:#16231b;background:#fff;border:1.5px solid #dde6dd;border-radius:12px}
.lg .register-password-wrap{position:relative;display:block;margin-top:5px}
.lg .register-password-wrap input{margin:0;padding-right:48px}
.lg .register-form input:focus,.lg .register-form select:focus{outline:0;border-color:#1f7a46;box-shadow:0 0 0 4px rgba(31,122,70,.12)}
.lg .register-form button{height:50px;margin:4px 0 0;border:0;border-radius:14px;background:linear-gradient(135deg,#2a9d5c,#1f7a46);color:#fff;font:inherit;font-weight:800;cursor:pointer;box-shadow:0 12px 24px -12px #1f7a46}
.lg .register-form button:hover{filter:brightness(1.06)}
.lg .register-form button.is-loading{display:flex;align-items:center;justify-content:center;gap:9px;opacity:.88;cursor:wait}
.lg .register-spinner{width:17px;height:17px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;animation:register-spin .7s linear infinite}
@keyframes register-spin{to{transform:rotate(360deg)}}
.lg .register-form .register-password-wrap .eye{width:40px;height:40px;margin:0;padding:0;border:0;border-radius:12px;background:transparent;color:#667467;font:inherit;box-shadow:none}
.lg .register-form .register-password-wrap .eye:hover{background:#eef3ef;color:#16231b;filter:none}
[data-theme="dark"] .lg .auth-tabs{background:#101713}
[data-theme="dark"] .lg .auth-tabs a{color:#a6b5aa}
[data-theme="dark"] .lg .auth-tabs a[aria-selected="true"]{background:#233c2c;color:#d6e8db}
[data-theme="dark"] .lg .auth-title,[data-theme="dark"] .lg .register-form label{color:#d6e8db}
[data-theme="dark"] .lg .register-form input,[data-theme="dark"] .lg .register-form select{color:#e7eee9;background:#19231d;border-color:#2c3b31}

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
  .lg .mbrand{display:flex;align-items:center;gap:12px;margin:0 0 clamp(26px,6vh,48px);font-weight:800;font-size:19px;letter-spacing:-.02em;color:#0f3d26}
  .lg .mbrand i{wid}catch(Exception $e){error_log('Login security schema setup failed: '.$e->getMessage());}

/* NOTE: the old auto-seed of admin/admin123 and librarian/lib123 was removed on purpose.
   Create staff accounts directly in the database (see instructions). */

$err=isset($_GET['deactivated'])?'This student account has been deactivated. Please contact library staff.':'';
$notice=isset($_GET['reset'])?'Password updated. You can now sign in with your new password.':'';
$ip=$_SERVER['REMOTE_ADDR']??'';
const MAX_TRIES=5;      // failed attempts allowed...
const WINDOW_SEC=600;   // ...within this many seconds (10 minutes)

if(isset($_POST['register_student'])){
  foreach($registrationValues as $key=>$value){
    $registrationValues[$key]=trim(is_string($_POST[$key]??null)?$_POST[$key]:'');
  }
  $studentNo=$registrationValues['student_no'];
  $name=$registrationValues['name'];
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
      $insert=$pdo->prepare("INSERT INTO students(student_no,name,gender,track,strand,grade,section,account_active) VALUES(?,?,?,?,?,?,?,1)");
      $insert->execute([$studentNo,$name,$gender,$track,$strand,$grade,$section]);
      $studentId=(int)$pdo->lastInsertId();
      $createAccount=$pdo->prepare("INSERT INTO users(username,password,role,student_id,must_change) VALUES(?,?,'student',?,0)");
      $createAccount->execute([$studentNo,password_hash($registrationPassword,PASSWORD_DEFAULT),$studentId]);
      $pdo->commit();
      $registrationMessage='Student registered successfully. Sign in using your Student ID and the password you created.';
      $registrationSuccess=true;
      $registrationValues=['student_no'=>'','name'=>'','gender'=>'','track'=>'','strand'=>'','grade'=>'','section'=>''];
      $_SESSION['student_registration_token']=bin2hex(random_bytes(32));
      $registrationToken=$_SESSION['student_registration_token'];
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
  if($u&&$u['role']==='student'){
    $studentQuery=$pdo->prepare("SELECT * FROM students WHERE id=?");
    $studentQuery->execute([(int)$u['student_id']]);
    $student=$studentQuery->fetch();
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

  if($authenticatedUser){
    session_regenerate_id(true);
    $pdo->prepare("DELETE FROM login_attempts WHERE ip=? AND username=?")->execute([$ip,$user]);
    $_SESSION['u']=['id'=>$authenticatedUser['id'],'username'=>$authenticatedUser['username'],'role'=>$authenticatedUser['role'],'student_id'=>$authenticatedUser['student_id']];
    unset($_SESSION['must_change']);
    header('Location: '.($authenticatedUser['role']=='student'?'student.php':'index.php'));exit;
