<?php require 'config.php';
if(empty($_SESSION['u'])){ header('Location: login.php'); exit; }

$me=$_SESSION['u'];
if(($me['role']??'')==='student'){
  ensure_student_account_status_schema($pdo);
  $status=$pdo->prepare("SELECT account_active FROM students WHERE id=?");
  $status->execute([(int)($me['student_id']??0)]);
  if((int)$status->fetchColumn()!==1){
    session_unset();session_destroy();
    header('Location: login.php?deactivated=1');exit;
  }
}elseif(($me['role']??'')==='teacher'){
  ensure_teacher_schema($pdo);
  $status=$pdo->prepare("SELECT account_active FROM teachers WHERE id=?");
  $status->execute([(int)($me['teacher_id']??0)]);
  if((int)$status->fetchColumn()!==1){
    session_unset();session_destroy();
    header('Location: login.php?deactivated=1');exit;
  }
}
$forced=!empty($_SESSION['must_change']);
$err='';
$home=($me['role']==='student')?'student.php':(($me['role']==='teacher')?'teacher.php':'index.php');

if($_SERVER['REQUEST_METHOD']==='POST'){
  $cur=(string)($_POST['current']??'');
  $new=(string)($_POST['new']??'');
  $con=(string)($_POST['confirm']??'');

  $s=$pdo->prepare("SELECT password FROM users WHERE id=?");$s->execute([$me['id']]);$hash=$s->fetchColumn();

  if(!$hash||!password_verify($cur,$hash)){
    $err='Your current password is incorrect.';
  }elseif(strlen($new)<8){
    $err='New password must be at least 8 characters.';
  }elseif(!preg_match('/[A-Za-z]/',$new)||!preg_match('/\d/',$new)){
    $err='New password must contain at least one letter and one number.';
  }elseif($new===$cur){
    $err='New password must be different from the current one.';
  }elseif($new!==$con){
    $err='The new passwords do not match.';
  }else{
    $pdo->prepare("UPDATE users SET password=?, must_change=0 WHERE id=?")
        ->execute([password_hash($new,PASSWORD_DEFAULT),$me['id']]);
    unset($_SESSION['must_change']);
    session_regenerate_id(true);
    header('Location: '.$home); exit;
  }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Change Password | eScan Go - RHSTI Library Management System</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:linear-gradient(145deg,#f0eff7,#e7e6f3 58%,#f7f7fb);font-family:'Plus Jakarta Sans',system-ui,sans-serif;color:#38434d}
.card{width:100%;max-width:440px;background:#fff;border:1px solid #e4e6ee;border-radius:24px;padding:32px 30px;box-shadow:0 24px 70px -38px rgba(56,58,87,.24)}
.brand{font-weight:800;font-size:15px;color:#3c3d5b;margin:0 0 18px}
.brand small{display:block;font-weight:500;color:#818b96;font-size:12.5px}
h1{margin:0 0 6px;font-size:26px;letter-spacing:-.03em}
p.sub{margin:0 0 20px;color:#818b96;font-size:14.5px;line-height:1.5}
.err{margin:0 0 16px;padding:12px 14px;border-radius:14px;background:#fde8e6;border:1px solid #f4c7c2;color:#b42318;font-weight:600;font-size:14px}
label{display:block;margin:0 0 14px;font-size:13px;font-weight:700}
input{display:block;width:100%;height:52px;margin-top:7px;padding:0 16px;font:inherit;font-size:15px;border:1.5px solid #e4e6ee;border-radius:14px;background:#fff;color:#38434d}
input:focus{outline:0;border-color:#716df1;box-shadow:0 0 0 5px #716df124}
button{width:100%;height:54px;margin-top:6px;border:0;border-radius:14px;font:inherit;font-size:16px;font-weight:800;color:#fff;cursor:pointer;background:linear-gradient(135deg,#827eff,#716df1)}
.rules{margin:14px 0 0;font-size:12.5px;color:#818b96;line-height:1.55}
a.out{display:block;margin-top:16px;text-align:center;font-size:13px;color:#818b96}
</style></head>
<body>
<form method="post" class="card" autocomplete="off">
  <div class="brand">eScan Go<small>RHSTI Library Management System</small></div>
  <h1><?= $forced ? 'Set your new password' : 'Change password' ?></h1>
  <p class="sub"><?= $forced
    ? 'For your security, please replace your temporary password before continuing.'
    : 'Choose a new password for your account.' ?></p>

  <?php if($err) echo '<div class="err" role="alert">'.h($err).'</div>'; ?>

  <label>Current password
    <input type="password" name="current" required autocomplete="current-password" autofocus></label>
  <label>New password
    <input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
  <label>Confirm new password
    <input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>

  <button type="submit">Save password</button>
  <p class="rules">At least 8 characters, with a letter and a number. Do not use your ID or share your password.</p>
  <?php if(!$forced): ?><a class="out" href="<?=h($home)?>">Cancel</a><?php endif; ?>
</form>
</body></html>
