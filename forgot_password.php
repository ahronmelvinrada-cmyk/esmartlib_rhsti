<?php
require 'config.php';
ensure_recovery_schema($pdo);

$message='';
$messageType='info';
$username=trim((string)($_POST['username']??''));
$showResetForm=$_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['reset_password']);

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_token_valid($_POST['csrf_token']??null)){
    $message='Your session expired. Refresh the page and try again.';
    $messageType='error';
  }elseif(isset($_POST['send_code'])){
    if($username===''){
      $message='Enter your username or Student ID.';
      $messageType='error';
    }else{
      $accountQuery=$pdo->prepare("SELECT id,role,student_id FROM users WHERE username=?");
      $accountQuery->execute([$username]);
      $account=$accountQuery->fetch();
      $eligible=(bool)$account;
      if($eligible&&$account['role']==='student'){
        ensure_student_account_status_schema($pdo);
        $status=$pdo->prepare("SELECT account_active FROM students WHERE id=?");
        $status->execute([(int)$account['student_id']]);
        $eligible=(int)$status->fetchColumn()===1;
      }

      if($eligible){
        $recentQuery=$pdo->prepare("SELECT requested_at,code_hash,expires_at,approved_by FROM password_reset_codes WHERE user_id=?");
        $recentQuery->execute([(int)$account['id']]);
        $existingRequest=$recentQuery->fetch();
        $recentPending=$existingRequest&&!$existingRequest['code_hash']&&empty($existingRequest['approved_by'])
          &&strtotime($existingRequest['requested_at'])>time()-60;
        $activeCode=$existingRequest&&!empty($existingRequest['code_hash'])&&!empty($existingRequest['approved_by'])
          &&!empty($existingRequest['expires_at'])&&strtotime($existingRequest['expires_at'])>time();
        if(!$recentPending&&!$activeCode){
          $pdo->prepare("INSERT INTO password_reset_codes(user_id,code_hash,requested_at,expires_at,attempts,approved_by,approved_at)
            VALUES(?,NULL,?,NULL,0,NULL,NULL)
            ON DUPLICATE KEY UPDATE code_hash=NULL,requested_at=VALUES(requested_at),expires_at=NULL,attempts=0,approved_by=NULL,approved_at=NULL")
            ->execute([(int)$account['id'],date('Y-m-d H:i:s')]);
        }
      }
      $message='If the account exists, your password reset request is ready for an administrator. Ask the library admin for your one-time code.';
      $messageType='success';
    }
  }elseif(isset($_POST['reset_password'])){
    $code=trim((string)($_POST['code']??''));
    $password=(string)($_POST['new_password']??'');
    $passwordRepeat=(string)($_POST['new_password_repeat']??'');
    if(!preg_match('/^\d{6}$/',$code)){
      $message='Enter the six-digit code given to you by the administrator.';
      $messageType='error';
    }elseif(strlen($password)<6||strlen($password)>72||$password!==$passwordRepeat){
      $message='The new password must be 6-72 bytes and both password fields must match.';
      $messageType='error';
    }elseif($username===''){
      $message='Enter your username or Student ID.';
      $messageType='error';
    }else{
      $accountQuery=$pdo->prepare("SELECT id,username,role,student_id FROM users WHERE username=?");
      $accountQuery->execute([$username]);
      $account=$accountQuery->fetch();
      $result='invalid';
      if($account){
        $pdo->beginTransaction();
        $codeQuery=$pdo->prepare("SELECT code_hash,expires_at,attempts,approved_by FROM password_reset_codes WHERE user_id=? FOR UPDATE");
        $codeQuery->execute([(int)$account['id']]);
        $reset=$codeQuery->fetch();
        if(!$reset||empty($reset['approved_by'])||empty($reset['code_hash'])){
          $result='invalid';
        }elseif(empty($reset['expires_at'])||strtotime($reset['expires_at'])<=time()){
          $pdo->prepare("UPDATE password_reset_codes SET code_hash=NULL,expires_at=NULL,attempts=0,approved_by=NULL,approved_at=NULL WHERE user_id=?")
            ->execute([(int)$account['id']]);
          $result='expired';
        }elseif((int)$reset['attempts']>=5){
          $pdo->prepare("UPDATE password_reset_codes SET code_hash=NULL,expires_at=NULL,attempts=0,approved_by=NULL,approved_at=NULL WHERE user_id=?")
            ->execute([(int)$account['id']]);
          $result='locked';
        }elseif(!password_verify($code,$reset['code_hash'])){
          $attempts=(int)$reset['attempts']+1;
          if($attempts>=5){
            $pdo->prepare("UPDATE password_reset_codes SET code_hash=NULL,expires_at=NULL,attempts=0,approved_by=NULL,approved_at=NULL WHERE user_id=?")
              ->execute([(int)$account['id']]);
          }else{
            $pdo->prepare("UPDATE password_reset_codes SET attempts=? WHERE user_id=?")->execute([$attempts,(int)$account['id']]);
          }
          $result=$attempts>=5?'locked':'invalid';
        }else{
          $pdo->prepare("UPDATE users SET password=? WHERE id=?")
            ->execute([password_hash($password,PASSWORD_DEFAULT),(int)$account['id']]);
          $pdo->prepare("DELETE FROM password_reset_codes WHERE user_id=?")->execute([(int)$account['id']]);
          $loginAttemptsTable=$pdo->query("SHOW TABLES LIKE 'login_attempts'");
          if($loginAttemptsTable&&$loginAttemptsTable->fetch()){
            $pdo->prepare("DELETE FROM login_attempts WHERE username=?")->execute([$account['username']]);
          }
          $studentLocksTable=$pdo->query("SHOW TABLES LIKE 'student_login_locks'");
          if($account['role']==='student'&&!empty($account['student_id'])&&$studentLocksTable&&$studentLocksTable->fetch()){
            $pdo->prepare("DELETE FROM student_login_locks WHERE student_id=?")->execute([(int)$account['student_id']]);
          }
          $pdo->commit();
          header('Location: login.php?reset=1');
          exit;
        }
        $pdo->commit();
      }
      $message=$result==='expired'?'That code expired. Submit a new request and ask the administrator for another code.':($result==='locked'?'Too many incorrect codes. Submit a new request to get a new code.':'That code is invalid.');
      $messageType='error';
    }
  }
}

$title='Forgot password';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=h($title)?> | eSmartLib</title>
  <?=pwa_head_assets()?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=<?=filemtime(__DIR__.'/assets/style.css')?>">
  <style>
    body.recovery{min-height:100vh;margin:0;padding:24px;display:grid;place-items:center;background:#f4f7f3;font-family:'Plus Jakarta Sans',system-ui,sans-serif}
    .recovery *{box-sizing:border-box}
    .recovery-card{width:100%;max-width:460px;padding:34px;background:#fff;border:1px solid #dfe9e1;border-radius:24px;box-shadow:0 24px 70px -38px rgba(15,61,38,.36)}
    .recovery-card h1{margin:0 0 8px;color:#16231b;font-size:28px;letter-spacing:-.03em}
    .recovery-card p{color:#667467;line-height:1.55}
    .recovery-card label{display:block;margin:18px 0 0;font-size:13px;font-weight:700;color:#16231b}
    .recovery-card input{display:block;width:100%;height:50px;margin-top:7px;padding:0 14px;border:1.5px solid #dde6dd;border-radius:13px;font:inherit}
    .recovery-card input:focus{outline:0;border-color:#1f7a46;box-shadow:0 0 0 4px rgba(31,122,70,.12)}
    .recovery-card button{width:100%;min-height:50px;margin-top:18px;border:0;border-radius:13px;background:linear-gradient(135deg,#2a9d5c,#1f7a46);color:#fff;font:inherit;font-weight:800;cursor:pointer}
    .recovery-message{padding:12px 14px;border-radius:12px;font-size:14px}
    .recovery-message.success{background:#ecfdf5;color:#14532d}
    .recovery-message.error{background:#fff1f2;color:#9f1239}
    .recovery-message.info{background:#eff6ff;color:#1e40af}
    .recovery-back{display:block;margin-top:20px;text-align:center;color:#1f7a46;font-weight:700;text-decoration:none}
    @media(max-width:480px){body.recovery{padding:16px}.recovery-card{padding:26px 22px}}
  </style>
</head>
<body class="recovery">
  <main class="recovery-card">
    <h1>Forgot password?</h1>
    <p>Request a one-time reset code from the administrator. The code expires after 10 minutes.</p>
    <?php if($message!==''):?><p class="recovery-message <?=h($messageType)?>" role="<?=$messageType==='error'?'alert':'status'?>"><?=h($message)?></p><?php endif;?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
      <label>Username / Student ID
        <input name="username" maxlength="50" value="<?=h($username)?>" autocomplete="username" required>
      </label>
      <button type="submit" name="send_code" value="1">Request a reset code</button>
    </form>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
      <input type="hidden" name="username" value="<?=h($username)?>">
      <label>Six-digit code from admin
        <input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
      </label>
      <label>New password
        <input type="password" name="new_password" minlength="6" maxlength="72" autocomplete="new-password" required>
      </label>
      <label>Confirm new password
        <input type="password" name="new_password_repeat" minlength="6" maxlength="72" autocomplete="new-password" required>
      </label>
      <button type="submit" name="reset_password" value="1">Reset password</button>
    </form>
    <a class="recovery-back" href="login.php">Back to sign in</a>
  </main>
</body>
</html>
