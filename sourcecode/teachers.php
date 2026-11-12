<?php
require 'config.php';
require 'Ui2026.PHP';
auth(['admin','librarian']);
ensure_teacher_schema($pdo);

$message='';
$messageType='success';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_token_valid($_POST['csrf_token']??null)){
    $message='Your session expired. Refresh the page and try again.';
    $messageType='error';
  }elseif(isset($_POST['register_teacher'])){
    $teacherNo=trim((string)($_POST['teacher_no']??''));
    $name=trim((string)($_POST['name']??''));
    $password=(string)($_POST['password']??'');
    $length=fn($value)=>function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value);
    if($teacherNo===''||$name===''){
      $message='Teacher ID and full name are required.';
      $messageType='error';
    }elseif($length($teacherNo)>30||$length($name)>120){
      $message='Teacher ID must be 30 characters or fewer and name must be 120 characters or fewer.';
      $messageType='error';
    }elseif(strlen($password)<8||!preg_match('/[A-Za-z]/',$password)||!preg_match('/\d/',$password)){
      $message='Password must be at least 8 characters and include a letter and a number.';
      $messageType='error';
    }else{
      try{
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO teachers(teacher_no,name) VALUES(?,?)")->execute([$teacherNo,$name]);
        $teacherId=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO users(username,password,role,teacher_id,must_change) VALUES(?,?,'teacher',?,0)")
          ->execute([$teacherNo,password_hash($password,PASSWORD_DEFAULT),$teacherId]);
        $pdo->commit();
        $message='Teacher registered. Sign-in username is the Teacher ID.';
      }catch(PDOException $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if(($e->errorInfo[1]??null)===1062){
          $message='That Teacher ID or username is already registered.';
          $messageType='error';
        }else{
          error_log('Teacher registration failed: '.$e->getMessage());
          $message='Could not register the teacher. Please try again.';
          $messageType='error';
        }
      }
    }
  }elseif(isset($_POST['toggle_teacher'])){
    $teacherId=filter_var($_POST['toggle_teacher'],FILTER_VALIDATE_INT);
    $active=isset($_POST['activate_teacher'])?1:0;
    if($teacherId===false||$teacherId<1){
      $message='Invalid teacher account.';
      $messageType='error';
    }else{
      $update=$pdo->prepare("UPDATE teachers SET account_active=? WHERE id=? AND account_active<>?");
      $update->execute([$active,$teacherId,$active]);
      if($update->rowCount())$message=$active?'Teacher account activated.':'Teacher account deactivated.';
      else{
        $exists=$pdo->prepare("SELECT COUNT(*) FROM teachers WHERE id=?");
        $exists->execute([$teacherId]);
        if((int)$exists->fetchColumn()===0){$message='Teacher not found.';$messageType='error';}
        else $message='Teacher account status is unchanged.';
      }
    }
  }elseif(isset($_POST['delete_teacher'])){
    if(($_SESSION['u']['role']??'')!=='admin'){
      $message='Only an admin can delete a teacher.';
      $messageType='error';
    }else{
      $teacherId=filter_var($_POST['delete_teacher'],FILTER_VALIDATE_INT);
      if($teacherId===false||$teacherId<1){
        $message='Invalid teacher account.';
        $messageType='error';
      }else{
        try{
          $pdo->beginTransaction();
          $teacherCheck=$pdo->prepare("SELECT id FROM teachers WHERE id=? FOR UPDATE");
          $teacherCheck->execute([$teacherId]);
          if(!$teacherCheck->fetchColumn()){
            $pdo->rollBack();
            $message='Teacher not found.';
            $messageType='error';
          }else{
            $loanCheck=$pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE teacher_id=?");
            $loanCheck->execute([$teacherId]);
            if((int)$loanCheck->fetchColumn()>0){
              $pdo->rollBack();
              $message='This teacher has borrowing history and cannot be deleted. Deactivate the account to prevent sign-in.';
              $messageType='error';
            }else{
              $pdo->prepare("DELETE FROM users WHERE teacher_id=? AND role='teacher'")->execute([$teacherId]);
              $deleteTeacher=$pdo->prepare("DELETE FROM teachers WHERE id=?");
              $deleteTeacher->execute([$teacherId]);
              if($deleteTeacher->rowCount()!==1){
                $pdo->rollBack();
                $message='Teacher not found.';
                $messageType='error';
              }else{
                $pdo->commit();
                $message='Teacher and teacher login account deleted.';
              }
            }
          }
        }catch(PDOException $e){
          if($pdo->inTransaction())$pdo->rollBack();
          error_log('Teacher deletion failed: '.$e->getMessage());
          $message='Could not delete the teacher. Please try again.';
          $messageType='error';
        }
      }
    }
  }
}

$isAdmin=($_SESSION['u']['role']??'')==='admin';
$teachers=$pdo->query("SELECT t.id,t.teacher_no,t.name,t.photo,t.account_active,t.created_at,u.id AS user_id,u.must_change
  FROM teachers t LEFT JOIN users u ON u.teacher_id=t.id AND u.role='teacher'
  ORDER BY t.name,t.teacher_no")->fetchAll();
head('Teachers');
ui_css();
?>
<div class="pg">
  <?php if($message!==''):?><div class="note<?=$messageType==='error'?' bad':''?>"><?=ic('info')?><span><?=h($message)?></span></div><?php endif;?>
  <form method="post" class="card">
    <div class="ph"><span class="pico"><?=ic('user')?></span><div><h3>Register teacher</h3><p>Create a teacher account and library pass. Only library staff can view this directory.</p></div></div>
    <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
    <div class="fgrid">
      <label class="fl"><span>Teacher ID</span><input name="teacher_no" maxlength="30" required></label>
      <label class="fl"><span>Full name</span><input name="name" maxlength="120" required></label>
      <label class="fl"><span> password</span><span class="teacher-password"><input id="teacher-password" type="password" name="password" minlength="8" autocomplete="new-password" required><button type="button" class="teacher-password-toggle" aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
    </div>
    <p class="help">The Teacher ID is also the login username. Passwords must contain at least 8 characters, including a letter and a number.</p>
    <div class="fact"><button name="register_teacher" value="1"><?=ic('plus')?>Register teacher</button></div>
  </form>

  <div class="card">
    <div class="ph"><span class="pico"><?=ic('users')?></span><div><h3>Teacher passes</h3><p>Teachers can sign in to display their own pass. Scan the pass, then scan each book QR.</p></div><span class="badge"><?=count($teachers)?> teacher<?=count($teachers)===1?'':'s'?></span></div>
    <?php if(!$teachers):?><p class="help">No teachers have been registered.</p>
    <?php else:?>
      <div class="tw"><table class="rt">
        <tr><th>Teacher</th><th>Teacher ID</th><th>Pass QR</th><th>Account</th><th>Action</th></tr>
        <?php foreach($teachers as $teacher):?>
          <tr>
            <td data-label="Teacher"><span class="teacher-person"><?php if(teacher_photo_path_ok($teacher['photo'])&&is_file(__DIR__.'/'.$teacher['photo'])):?><img src="<?=h($teacher['photo'])?>" alt="<?=h($teacher['name'])?>"><?php else:?><span class="teacher-person-initial"><?=h(function_exists('mb_substr')?mb_strtoupper(mb_substr(trim($teacher['name']),0,1,'UTF-8'),'UTF-8'):strtoupper(substr(trim($teacher['name']),0,1)))?></span><?php endif;?><strong><?=h($teacher['name'])?></strong></span></td>
            <td data-label="Teacher ID"><?=h($teacher['teacher_no'])?></td>
            <td data-label="Pass QR"><div class="qr teacher-qr" data-code="TCH:<?=h($teacher['teacher_no'])?>"></div></td>
            <td data-label="Account"><span class="pill <?=$teacher['account_active']?'ok':'late'?>"><?=$teacher['account_active']?'Active':'Deactivated'?></span><?php if((int)$teacher['must_change']===1):?><small class="muted" style="display:block">Password change required</small><?php endif;?></td>
            <td data-label="Action">
              <form method="post" onsubmit="return confirm('<?=$teacher['account_active']?'Deactivate':'Activate'?> this teacher account?')">
                <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
                <input type="hidden" name="toggle_teacher" value="<?=(int)$teacher['id']?>">
                <?php if($teacher['account_active']):?>
                  <button class="ghost sm" name="deactivate_teacher" value="1">Deactivate</button>
                <?php else:?>
                  <button class="ghost sm" name="activate_teacher" value="1">Activate</button>
                <?php endif;?>
              </form>
              <?php if($isAdmin):?>
                <form method="post" onsubmit="return confirm('Permanently delete this teacher and their login? This cannot be undone. Teachers with borrowing history cannot be deleted.')">
                  <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
                  <input type="hidden" name="delete_teacher" value="<?=(int)$teacher['id']?>">
                  <button class="danger sm" type="submit">Delete</button>
                </form>
              <?php endif;?>
            </td>
          </tr>
        <?php endforeach;?>
      </table></div>
    <?php endif;?>
  </div>
</div>
<style>
.pg .teacher-qr{width:88px;height:88px;padding:4px}
.pg .teacher-qr canvas,.pg .teacher-qr img{width:80px!important;height:80px!important}
.pg .teacher-person{display:flex;align-items:center;gap:10px;min-width:180px}
.pg .teacher-person img,.pg .teacher-person-initial{width:42px;height:42px;flex:none;border-radius:14px;object-fit:cover}
.pg .teacher-person-initial{display:grid;place-items:center;background:var(--leaf);color:var(--gd);font-size:17px;font-weight:800}
.pg .teacher-password{position:relative;display:block}
.pg .teacher-password input{padding-right:52px}
.pg .teacher-password-toggle{position:absolute;right:4px;top:3px;width:40px;height:40px;padding:0;border:0;border-radius:10px;background:transparent;color:var(--mute);box-shadow:none}
.pg .teacher-password-toggle:hover{background:var(--leaf);color:var(--gd);transform:none}
.pg .teacher-password-toggle svg{width:20px;height:20px}
</style>
<?php foot('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>renderQR();(function(){var input=document.getElementById("teacher-password"),button=document.querySelector(".teacher-password-toggle");if(!input||!button)return;button.addEventListener("click",function(){var show=input.type==="password";input.type=show?"text":"password";button.setAttribute("aria-label",show?"Hide password":"Show password");button.setAttribute("aria-pressed",show?"true":"false");input.focus();});})();</script>'); ?>
