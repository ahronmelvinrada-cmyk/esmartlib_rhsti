<?php header('Location: account.php?tab=resets');exit;
  require 'config.php';
  require 'Ui2026.PHP';
  auth(['admin']);
  ensure_recovery_schema($pdo);
  ensure_student_account_status_schema($pdo);

  $me=(int)$_SESSION['u']['id'];
  $message='';
  $messageType='success';
  $issuedCode=$_SESSION['password_reset_code_flash']??null;
  unset($_SESSION['password_reset_code_flash']);

  if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['generate_code'])){
    if(!csrf_token_valid($_POST['csrf_token']??null)){
      $message='Your session expired. Refresh the page and try again.';
      $messageType='error';
    }else{
      $requestId=(int)$_POST['generate_code'];
      $pdo->beginTransaction();
      try{
        $requestQuery=$pdo->prepare("SELECT r.user_id,r.requested_at,u.username,u.role,u.student_id
          FROM password_reset_codes r JOIN users u ON u.id=r.user_id
          WHERE r.user_id=? AND r.code_hash IS NULL FOR UPDATE");
        $requestQuery->execute([$requestId]);
        $request=$requestQuery->fetch();
        if(!$request||strtotime($request['requested_at'])<time()-86400){
          $pdo->rollBack();
          $message='That reset request is no longer available. Ask the user to submit a new request.';
          $messageType='error';
        }else{
          $active=true;
          if($request['role']==='student'){
            $status=$pdo->prepare("SELECT account_active FROM students WHERE id=?");
            $status->execute([(int)$request['student_id']]);
            $active=(int)$status->fetchColumn()===1;
          }
          if(!$active){
            $pdo->rollBack();
            $message='This student account is deactivated and cannot be reset.';
            $messageType='error';
          }else{
            $code=(string)random_int(100000,999999);
            $expiresAt=date('Y-m-d H:i:s',time()+600);
            $update=$pdo->prepare("UPDATE password_reset_codes
              SET code_hash=?,expires_at=?,attempts=0,approved_by=?,approved_at=NOW()
              WHERE user_id=? AND code_hash IS NULL");
            $update->execute([password_hash($code,PASSWORD_DEFAULT),$expiresAt,$me,$requestId]);
            if($update->rowCount()!==1){
              $pdo->rollBack();
              $message='This request was already handled. Refresh the page and check the request list.';
              $messageType='error';
            }else{
              $pdo->commit();
              $_SESSION['password_reset_code_flash']=[
                'username'=>$request['username'],
                'code'=>$code,
                'expires_at'=>$expiresAt,
              ];
              header('Location: password_resets.php');
              exit;
            }
          }
        }
      }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
      }
    }
  }

  $allRequests=$pdo->query("SELECT r.user_id,r.requested_at,u.username,u.role
    FROM password_reset_codes r JOIN users u ON u.id=r.user_id
    WHERE r.code_hash IS NULL
    ORDER BY r.requested_at ASC LIMIT 200")->fetchAll();
  $requests=array_slice(array_values(array_filter($allRequests,function($request){
    return strtotime($request['requested_at'])>=time()-86400;
  })),0,100);

  head('Password reset requests');ui_css();?>
  <div class="pg">
    <?php if($issuedCode):?>
      <div class="card" role="status" style="border:2px solid var(--g)">
        <div class="ph"><span class="pico"><?=ic('info')?></span><div><h3>Give this code to <?=h($issuedCode['username'])?></h3><p>This code is shown once and expires at <?=h($issuedCode['expires_at'])?>. Do not share it with anyone else.</p></div></div>
        <p style="font-size:30px;font-weight:800;letter-spacing:.18em;text-align:center;margin:20px 0"><?=h($issuedCode['code'])?></p>
      </div>
    <?php endif;?>

    <?php if($message!==''):?>
      <div class="note<?=$messageType==='error'?' bad':''?>"><?=ic('info')?><span><?=h($message)?></span></div>
    <?php endif;?>

    <div class="card">
      <div class="ph"><span class="pico"><?=ic('user')?></span><div><h3>Pending requests</h3><p>Generate a one-time code only after confirming the user's identity.</p></div><span class="badge"><?=count($requests)?> pending</span></div>
      <?php if(!$requests):?>
        <p class="help">No recent reset requests.</p>
      <?php else:?>
        <div class="tw"><table class="rt">
          <tr><th>Username</th><th>Role</th><th>Requested</th><th style="text-align:right">Action</th></tr>
          <?php foreach($requests as $request):?>
            <tr>
              <td data-label="Username"><?=h($request['username'])?></td>
              <td data-label="Role"><?=h(ucfirst($request['role']))?></td>
              <td data-label="Requested"><?=h($request['requested_at'])?></td>
              <td data-label="Action" style="text-align:right">
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
                  <button name="generate_code" value="<?=(int)$request['user_id']?>">Generate code</button>
                </form>
              </td>
            </tr>
          <?php endforeach;?>
        </table></div>
      <?php endif;?>
    </div>
  </div>
  <?php foot('');