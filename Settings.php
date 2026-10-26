<?php require 'config.php';require 'ui2026.php';auth(['admin']);
settings_init($pdo);
$me=(int)$_SESSION['u']['id'];

if($_SERVER['REQUEST_METHOD']==='POST'){
  $ok=true;$msg='';
  if(isset($_POST['rules'])){
    setting_set($pdo,'max_loans',max(1,min(20,(int)$_POST['max_loans'])));
    setting_set($pdo,'loan_days',max(1,min(60,(int)$_POST['loan_days'])));
    $msg='Loan rules saved.';
  }elseif(isset($_POST['info'])){
    setting_set($pdo,'library_name',trim($_POST['library_name']??''));
    setting_set($pdo,'school_name',trim($_POST['school_name']??''));
    $msg='Library info saved.';
  }elseif(isset($_POST['mypw'])){
    $q=$pdo->prepare("SELECT password FROM users WHERE id=?");$q->execute([$me]);
    if(!password_verify($_POST['old']??'',(string)$q->fetchColumn())){$ok=false;$msg='Current password is incorrect.';}
    elseif(strlen($_POST['new']??'')<6||($_POST['new']??'')!==($_POST['new2']??'')){$ok=false;$msg='New password must be 6+ characters and match.';}
    else{$pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['new'],PASSWORD_DEFAULT),$me]);$msg='Your password was changed.';}
  }elseif(isset($_POST['addstaff'])){
    $un=trim($_POST['username']??'');$pw=$_POST['password']??'';$role=($_POST['role']??'')==='admin'?'admin':'librarian';
    $c=$pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");$c->execute([$un]);
    if($un===''||strlen($pw)<6){$ok=false;$msg='Username is required and the password must be 6+ characters.';}
    elseif($c->fetchColumn()){$ok=false;$msg='That username is already taken.';}
    else{$pdo->prepare("INSERT INTO users(username,password,role) VALUES(?,?,?)")->execute([$un,password_hash($pw,PASSWORD_DEFAULT),$role]);$msg='Staff account created.';}
  }elseif(isset($_POST['resetpw'])){
    $id=(int)$_POST['resetpw'];$pw=$_POST['pw']??'';
    if(strlen($pw)<6){$ok=false;$msg='New password must be 6+ characters.';}
    else{$pdo->prepare("UPDATE users SET password=? WHERE id=? AND role IN ('admin','librarian')")->execute([password_hash($pw,PASSWORD_DEFAULT),$id]);$msg='Password reset.';}
  }elseif(isset($_POST['delstaff'])){
    $id=(int)$_POST['delstaff'];
    $q=$pdo->prepare("SELECT role FROM users WHERE id=? AND role IN ('admin','librarian')");$q->execute([$id]);$r=$q->fetchColumn();
    $admins=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if($id===$me){$ok=false;$msg='You cannot delete your own account.';}
    elseif(!$r){$ok=false;$msg='Account not found.';}
    elseif($r==='admin'&&$admins<=1){$ok=false;$msg='You cannot delete the last admin.';}
    else{try{$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);$msg='Staff account deleted.';}catch(Exception $e){$ok=false;$msg='Cannot delete: this account has records.';}}
  }
  if($msg){$_SESSION['flash']=[$ok,$msg];header('Location: settings.php');exit;}
}

$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
$maxLoans=(int)setting($pdo,'max_loans',defined('MAX_LOANS')?MAX_LOANS:3);
$loanDays=(int)setting($pdo,'loan_days',7);
$libName=setting($pdo,'library_name','eSmartlib');
$schoolName=setting($pdo,'school_name','RHSTI Library');
$staff=$pdo->query("SELECT id,username,role FROM users WHERE role IN ('admin','librarian') ORDER BY role,username")->fetchAll();
$studentAccts=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
head('Settings');ui_css();?>
<style>
.pg .split{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start}
@media(max-width:900px){.pg .split{grid-template-columns:1fr}}
.pg label.fl{display:block}
.pg label.fl>span{display:block;font-size:12.5px;font-weight:700;color:var(--mute);margin:0 0 6px}
.pg .note.bad{background:var(--redbg);border-color:#f9c2bd;color:var(--red)}
.pg .help{color:var(--mute);font-size:12.5px;margin:6px 0 0}
.pg .rowact{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
.pg .rowact form{display:flex;gap:8px;margin:0}
.pg .rowact input{height:38px;width:170px;font-size:13px}
.pg .rowact button{height:38px;padding:0 14px;font-size:13px}
.pg .pill.admin{background:#dbeafe;color:#1d4ed8}.pg .pill.librarian{background:#d1fae5;color:#065f46}
.pg .you{margin-left:8px;font-size:11px;font-weight:700;color:var(--g);background:var(--leaf);padding:2px 8px;border-radius:999px}
.pg .stat3{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:6px}
@media(max-width:640px){.pg .rowact{justify-content:flex-start}.pg .rowact input{width:100%}.pg .rowact form{width:100%}}
</style>
<div class="pg">

<?php if($flash)echo '<div class="note'.($flash[0]?'':' bad').'">'.ic('info').'<span>'.h($flash[1]).'</span></div>';?>

<div class="split">
  <!-- Loan rules -->
  <form method="post" class="card">
    <div class="ph"><span class="pico"><?=ic('book')?></span><div><h3>Loan rules</h3><p>How many books and for how long.</p></div></div>
    <div class="fgrid">
      <label class="fl"><span>Max books per student</span><input type="number" name="max_loans" min="1" max="20" value="<?=$maxLoans?>" required></label>
      <label class="fl"><span>Loan period (days)</span><input type="number" name="loan_days" min="1" max="60" value="<?=$loanDays?>" required></label>
    </div>
    <div class="fact"><button name="rules" value="1"><?=ic('plus')?>Save rules</button></div>
    <p class="help">Used by Borrow and the student dashboard once connected in config.php.</p>
  </form>

  <!-- Library info -->
  <form method="post" class="card">
    <div class="ph"><span class="pico"><?=ic('file')?></span><div><h3>Library info</h3><p>Names shown across the system.</p></div></div>
    <div class="fgrid">
      <label class="fl"><span>System name</span><input name="library_name" value="<?=h($libName)?>" maxlength="60"></label>
      <label class="fl"><span>School / library</span><input name="school_name" value="<?=h($schoolName)?>" maxlength="80"></label>
    </div>
    <div class="fact"><button name="info" value="1"><?=ic('plus')?>Save info</button></div>
  </form>
</div>

<!-- My password -->
<form method="post" class="card">
  <div class="ph"><span class="pico"><?=ic('user')?></span><div><h3>My password</h3><p>Signed in as <b><?=h($_SESSION['u']['username'])?></b>. Change the default password right away.</p></div></div>
  <div class="fgrid">
    <label class="fl"><span>Current password</span><input type="password" name="old" autocomplete="current-password" required></label>
    <label class="fl"><span>New password (6+ chars)</span><input type="password" name="new" autocomplete="new-password" required></label>
    <label class="fl"><span>Repeat new password</span><input type="password" name="new2" autocomplete="new-password" required></label>
  </div>
  <div class="fact"><button name="mypw" value="1">Update password</button></div>
</form>

<!-- Add staff -->
<form method="post" class="card">
  <div class="ph"><span class="pico"><?=ic('plus')?></span><div><h3>Add staff account</h3><p>Create an admin or librarian login.</p></div></div>
  <div class="fgrid">
    <label class="fl"><span>Username</span><input name="username" autocomplete="off" required></label>
    <label class="fl"><span>Password (6+ chars)</span><input type="password" name="password" autocomplete="new-password" required></label>
    <label class="fl"><span>Role</span><select name="role"><option value="librarian">Librarian</option><option value="admin">Admin</option></select></label>
  </div>
  <div class="fact"><button name="addstaff" value="1"><?=ic('plus')?>Create account</button></div>
  <p class="help">Librarians can borrow, return, record attendance and view students. Only admins can edit or delete.</p>
</form>

<!-- Staff list -->
<div class="card">
  <div class="ph"><span class="pico"><?=ic('user')?></span><div><h3>Staff accounts</h3><p><?=$studentAccts?> student account<?=$studentAccts==1?'':'s'?> are created automatically on first login.</p></div><span class="badge"><?=count($staff)?> staff</span></div>
  <div class="tw"><table class="rt">
    <tr><th>Username</th><th>Role</th><th style="text-align:right">Actions</th></tr>
    <?php foreach($staff as $s):$self=((int)$s['id']===$me);?>
      <tr>
        <td data-label="Username"><?=h($s['username'])?><?=$self?'<span class="you">You</span>':''?></td>
        <td data-label="Role"><span class="pill <?=h($s['role'])?>"><?=h(ucfirst($s['role']))?></span></td>
        <td data-label="Actions"><div class="rowact">
          <form method="post"><input type="password" name="pw" placeholder="New password" minlength="6" autocomplete="new-password" required><button class="ghost" name="resetpw" value="<?=$s['id']?>">Reset</button></form>
          <?php if(!$self):?><form method="post" onsubmit="return confirm('Delete this staff account?')"><button class="danger" name="delstaff" value="<?=$s['id']?>" formnovalidate style="width:auto"><?=ic('trash')?>Delete</button></form><?php endif;?>
        </div></td>
      </tr>
    <?php endforeach;?>
  </table></div>
</div>

</div>
<?php foot('');