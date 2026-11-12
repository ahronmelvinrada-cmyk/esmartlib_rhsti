<?php require 'config.php';require 'Ui2026.PHP';auth(['admin','librarian','student','teacher']);
photo_col($pdo);
$me=(int)$_SESSION['u']['id'];
$role=$_SESSION['u']['role']??'';
if($role==='teacher')ensure_teacher_schema($pdo);
if($role==='student')ensure_user_email_schema($pdo);
$isStaff=in_array($role,['admin','librarian'],true);
$isAdmin=$role==='admin';
$isTeacher=$role==='teacher';
$tab=$_GET['tab']??'profile';
if(!in_array($tab,['profile','settings','resets','bookings'],true)||(!$isAdmin&&$tab!=='profile'))$tab='profile';
if($isAdmin){settings_init($pdo);ensure_recovery_schema($pdo);ensure_book_request_schema($pdo);ensure_student_account_status_schema($pdo);}

/* ---------- actions (post -> redirect, so refresh won't resubmit) ---------- */
if($_SERVER['REQUEST_METHOD']==='POST'){
  $ok=true;$msg='';
  $redirectTab='profile';
  if($isTeacher&&isset($_POST['teacher_photo_action'])){
    if(!csrf_token_valid($_POST['csrf_token']??null)){$ok=false;$msg='Your session expired. Refresh the page and try again.';}
    elseif(empty($_SESSION['u']['teacher_id'])){$ok=false;$msg='Teacher account not found.';}
    elseif($_POST['teacher_photo_action']==='upload'){
      $photoError=teacher_photo_save($pdo,(int)$_SESSION['u']['teacher_id'],$_FILES['teacher_photo']??[]);
      if($photoError!==''){$ok=false;$msg=$photoError;}
      else $msg='Profile photo updated.';
    }elseif($_POST['teacher_photo_action']==='remove'){
      if(!teacher_photo_remove($pdo,(int)$_SESSION['u']['teacher_id'])){$ok=false;$msg='Teacher account not found.';}
      else $msg='Profile photo removed.';
    }else{$ok=false;$msg='Invalid profile photo action.';}
  }elseif($isAdmin&&isset($_POST['admin_action'])){
    $action=(string)$_POST['admin_action'];
    $redirectTab=in_array($action,['rules','info','logo_up','logo_rm','addstaff','resetpw','delstaff'],true)?'settings':($action==='generate_code'?'resets':'bookings');
    if(!csrf_token_valid($_POST['csrf_token']??null)){
      $ok=false;$msg='Your session expired. Refresh the page and try again.';
    }elseif($action==='rules'){
      setting_set($pdo,'max_loans',max(1,min(20,(int)($_POST['max_loans']??3))));
      setting_set($pdo,'loan_days',max(1,min(60,(int)($_POST['loan_days']??7))));
      $msg='Loan rules saved.';
    }elseif($action==='info'){
      $newLibraryName=trim($_POST['library_name']??'');$newSchoolName=trim($_POST['school_name']??'');
      if($newLibraryName===''||$newSchoolName===''){$ok=false;$msg='System name and school/library name are both required.';}
      elseif(strlen($newLibraryName)>60||strlen($newSchoolName)>80){$ok=false;$msg='System name must be 60 characters or less and school/library name 80 characters or less.';}
      else{setting_set($pdo,'library_name',$newLibraryName);setting_set($pdo,'school_name',$newSchoolName);$msg='Library info saved.';}
    }elseif($action==='logo_up'){
      $file=$_FILES['logo']??[];$uploadError=$file['error']??UPLOAD_ERR_NO_FILE;
      if($uploadError===UPLOAD_ERR_NO_FILE){$ok=false;$msg='Choose a logo image first.';}
      elseif($uploadError===UPLOAD_ERR_INI_SIZE||$uploadError===UPLOAD_ERR_FORM_SIZE){$ok=false;$msg='The logo is too large for the server.';}
      elseif($uploadError!==UPLOAD_ERR_OK){$ok=false;$msg='Logo upload failed. Please try again.';}
      elseif(($file['size']??0)>2*1024*1024){$ok=false;$msg='The logo must be 2 MB or smaller.';}
      else{
        $image=@getimagesize($file['tmp_name']??'');$extensions=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'];
        if(!$image||!isset($extensions[$image[2]])){$ok=false;$msg='Use a JPG, PNG, or WEBP image for the logo.';}
        else{
          $dir=__DIR__.'/uploads/library';
          if(!is_dir($dir)&&!@mkdir($dir,0755,true)){$ok=false;$msg='Cannot create the logo upload folder. Check hosting folder permissions.';}
          elseif(!is_writable($dir)){$ok=false;$msg='The logo upload folder is not writable. Check hosting folder permissions.';}
          else{
            $name='logo_'.bin2hex(random_bytes(8)).'.'.$extensions[$image[2]];$destination=$dir.'/'.$name;$protection=$dir.'/.htaccess';
            if(!is_file($protection)&&file_put_contents($protection,"<FilesMatch \"\\.(php|phtml|phar)$\">\nRequire all denied\n</FilesMatch>\n")===false){$ok=false;$msg='Could not secure the logo upload folder. Check hosting folder permissions.';}
            elseif(!is_uploaded_file($file['tmp_name']??'')||!move_uploaded_file($file['tmp_name'],$destination)){$ok=false;$msg='Could not save the logo. Please try again.';}
            else{$oldLogo=setting($pdo,'library_logo','');setting_set($pdo,'library_logo','uploads/library/'.$name);if($oldLogo&&strpos($oldLogo,'uploads/library/')===0&&strpos($oldLogo,'..')===false&&is_file(_DIR_.'/'.$oldLogo))@unlink(_DIR_.'/'.$oldLogo);$msg='Library logo updated.';}
          }
        }
      }
    }elseif($action==='logo_rm'){
      $oldLogo=setting($pdo,'library_logo','');setting_set($pdo,'library_logo','');
      if($oldLogo&&strpos($oldLogo,'uploads/library/')===0&&strpos($oldLogo,'..')===false&&is_file(_DIR_.'/'.$oldLogo))@unlink(_DIR_.'/'.$oldLogo);
      $msg='Library logo removed.';
    }elseif($action==='addstaff'){
      $un=trim($_POST['username']??'');$pw=$_POST['password']??'';$staffRole=($_POST['role']??'')==='admin'?'admin':'librarian';
      $c=$pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");$c->execute([$un]);
      if($un===''||strlen($pw)<6){$ok=false;$msg='Username is required and the password must be 6+ characters.';}
      elseif($c->fetchColumn()){$ok=false;$msg='That username is already taken.';}
      else{$pdo->prepare("INSERT INTO users(username,password,role) VALUES(?,?,?)")->execute([$un,password_hash($pw,PASSWORD_DEFAULT),$staffRole]);$msg='Staff account created.';}
    }elseif($action==='resetpw'){
      $id=(int)($_POST['resetpw']??0);$pw=$_POST['pw']??'';
      if(strlen($pw)<6){$ok=false;$msg='New password must be 6+ characters.';}
      else{$update=$pdo->prepare("UPDATE users SET password=? WHERE id=? AND role IN ('admin','librarian')");$update->execute([password_hash($pw,PASSWORD_DEFAULT),$id]);$msg=$update->rowCount()?'Password reset.':'Staff account not found.';if(!$update->rowCount())$ok=false;}
    }elseif($action==='delstaff'){
      $id=(int)($_POST['delstaff']??0);$q=$pdo->prepare("SELECT role FROM users WHERE id=? AND role IN ('admin','librarian')");$q->execute([$id]);$staffRole=$q->fetchColumn();
      $admins=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
      if($id===$me){$ok=false;$msg='You cannot delete your own account.';}
      elseif(!$staffRole){$ok=false;$msg='Account not found.';}
      elseif($staffRole==='admin'&&$admins<=1){$ok=false;$msg='You cannot delete the last admin.';}
      else{try{$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);$msg='Staff account deleted.';}catch(Exception $e){$ok=false;$msg='Cannot delete: this account has records.';}}
    }elseif($action==='generate_code'){
      $requestId=(int)($_POST['generate_code']??0);
      $pdo->beginTransaction();
      try{
        $requestQuery=$pdo->prepare("SELECT r.user_id,r.requested_at,u.username,u.role,u.student_id FROM password_reset_codes r JOIN users u ON u.id=r.user_id WHERE r.user_id=? AND r.code_hash IS NULL FOR UPDATE");
        $requestQuery->execute([$requestId]);$request=$requestQuery->fetch();
        if(!$request||strtotime($request['requested_at'])<time()-86400){$pdo->rollBack();$ok=false;$msg='That reset request is no longer available. Ask the user to submit a new request.';}
        else{
          $active=true;
          if($request['role']==='student'){$status=$pdo->prepare("SELECT account_active FROM students WHERE id=?");$status->execute([(int)$request['student_id']]);$active=(int)$status->fetchColumn()===1;}
          if(!$active){$pdo->rollBack();$ok=false;$msg='This student account is deactivated and cannot be reset.';}
          else{
            $code=(string)random_int(100000,999999);$expiresAt=date('Y-m-d H:i:s',time()+600);
            $update=$pdo->prepare("UPDATE password_reset_codes SET code_hash=?,expires_at=?,attempts=0,approved_by=?,approved_at=NOW() WHERE user_id=? AND code_hash IS NULL");
            $update->execute([password_hash($code,PASSWORD_DEFAULT),$expiresAt,$me,$requestId]);
            if($update->rowCount()!==1){$pdo->rollBack();$ok=false;$msg='This request was already handled. Refresh and check the request list.';}
            else{$pdo->commit();$_SESSION['password_reset_code_flash']=['username'=>$request['username'],'code'=>$code,'expires_at'=>$expiresAt];header('Location: account.php?tab=resets');exit;}
          }
        }
      }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }elseif(in_array($action,['approve','decline','reply'],true)){
      $requestId=filter_var($_POST['request_id']??null,FILTER_VALIDATE_INT);$reply=trim((string)($_POST['admin_reply']??''));
      $replyLength=function_exists('mb_strlen')?mb_strlen($reply,'UTF-8'):strlen($reply);
      if(!$requestId){$ok=false;$msg='Invalid booking request action.';}
      elseif($replyLength>500){$ok=false;$msg='Admin reply must be 500 characters or fewer.';}
      elseif($action==='reply'&&$reply===''){$ok=false;$msg='Write a reply before sending it to the student.';}
      else{
        $pdo->beginTransaction();
        try{
          $requestQuery=$pdo->prepare("SELECT r.id,r.student_id,r.book_id,r.status,b.available FROM book_requests r JOIN books b ON b.id=r.book_id WHERE r.id=? FOR UPDATE");
          $requestQuery->execute([$requestId]);$request=$requestQuery->fetch();
          if(!$request){$pdo->rollBack();$ok=false;$msg='Booking request not found.';}
          elseif($action==='reply'){$pdo->prepare("UPDATE book_requests SET admin_reply=?,replied_at=NOW(),replied_by=? WHERE id=?")->execute([$reply,$me,$requestId]);$pdo->commit();$msg='Reply sent to the student.';}
          elseif($request['status']!=='pending'){$pdo->rollBack();$ok=false;$msg='This request has already been handled.';}
          elseif($action==='approve'){
            $pdo->prepare("SELECT id FROM books WHERE id=? FOR UPDATE")->execute([(int)$request['book_id']]);
            $held=$pdo->prepare("SELECT COUNT(*) FROM book_requests WHERE book_id=? AND status='approved'");$held->execute([(int)$request['book_id']]);
            $borrowed=$pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE student_id=? AND book_id=? AND returned_at IS NULL");$borrowed->execute([(int)$request['student_id'],(int)$request['book_id']]);
            if((int)$request['available']-(int)$held->fetchColumn()<1){$pdo->rollBack();$ok=false;$msg='No unreserved copy is available for this booking.';}
            elseif((int)$borrowed->fetchColumn()>0){$pdo->rollBack();$ok=false;$msg='The student already has this book borrowed.';}
            else{$response=$reply!==''?$reply:'Your booking is approved. Please visit the library to borrow the book.';$pdo->prepare("UPDATE book_requests SET status='approved',admin_reply=?,reviewed_at=NOW(),reviewed_by=?,replied_at=NOW(),replied_by=? WHERE id=?")->execute([$response,$me,$me,$requestId]);$pdo->commit();$msg='Booking approved. The copy is reserved until the student borrows it.';}
          }else{$response=$reply!==''?$reply:'This booking request was declined by the library admin.';$pdo->prepare("UPDATE book_requests SET status='declined',admin_reply=?,reviewed_at=NOW(),reviewed_by=?,replied_at=NOW(),replied_by=? WHERE id=?")->execute([$response,$me,$me,$requestId]);$pdo->commit();$msg='Booking request declined.';}
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
      }
    }
    if($msg){$_SESSION['flash']=[$ok,$msg];header('Location: account.php?tab='.$redirectTab);exit;}
  }elseif(isset($_POST['pw'])){
    $q=$pdo->prepare("SELECT password FROM users WHERE id=?");$q->execute([$me]);
    if(!password_verify($_POST['old']??'',(string)$q->fetchColumn())){$ok=false;$msg='Current password is incorrect.';}
    elseif(strlen($_POST['new']??'')<6||($_POST['new']??'')!==($_POST['new2']??'')){$ok=false;$msg='New password must be 6+ characters and match.';}
    elseif(($_POST['new']??'')===($_POST['old']??'')){$ok=false;$msg='Your new password must be different from the current one.';}
    else{$pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['new'],PASSWORD_DEFAULT),$me]);$msg='Password changed.';}
  }elseif(isset($_POST['uname'])&&$isStaff){
    $un=trim($_POST['username']??'');
    $c=$pdo->prepare("SELECT COUNT(*) FROM users WHERE username=? AND id<>?");$c->execute([$un,$me]);
    if(!preg_match('/^[A-Za-z0-9_.-]{3,30}$/',$un)){$ok=false;$msg='Username must be 3-30 characters: letters, numbers, dot, dash or underscore.';}
    elseif($c->fetchColumn()){$ok=false;$msg='That username is already taken.';}
    else{$pdo->prepare("UPDATE users SET username=? WHERE id=?")->execute([$un,$me]);$_SESSION['u']['username']=$un;$msg='Username updated.';}
  }elseif(isset($_POST['save_student_email'])&&$role==='student'){
    $email=trim((string)($_POST['email']??''));
    if(!csrf_token_valid($_POST['csrf_token']??null)){$ok=false;$msg='Your session expired. Refresh the page and try again.';}
    elseif($email!==''&&(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)){$ok=false;$msg='Enter a valid Gmail or email address.';}
    else{
      $duplicateEmail=$pdo->prepare("SELECT COUNT(*) FROM users WHERE email=? AND id<>?");
      $duplicateEmail->execute([$email,$me]);
      if($email!==''&&(int)$duplicateEmail->fetchColumn()>0){$ok=false;$msg='That email address is already associated with another account.';}
      else try{
        $emailQuery=$pdo->prepare("UPDATE users SET email=? WHERE id=? AND role='student'");
        $emailQuery->execute([$email!==''?$email:null,$me]);
        $msg='Email saved. It will appear on the back of your student ID.';
      }catch(PDOException $e){
        if(($e->errorInfo[1]??null)===1062){$ok=false;$msg='That email address is already associated with another account.';}
        else{throw $e;}
      }
    }
  }
  if($msg){$_SESSION['flash']=[$ok,$msg];header('Location: account.php?tab=profile');exit;}
}
$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
$issuedCode=$_SESSION['password_reset_code_flash']??null;unset($_SESSION['password_reset_code_flash']);
$adminSettings=[];$resetRequests=[];$bookingRequests=[];$bookingCounts=[];
$bookingFilter=$_GET['booking_status']??'pending';
$validBookingFilters=['pending','approved','declined','cancelled','fulfilled','all'];
if(!in_array($bookingFilter,$validBookingFilters,true))$bookingFilter='pending';
if($isAdmin){
  $adminSettings=[
    'max_loans'=>(int)setting($pdo,'max_loans',defined('MAX_LOANS')?MAX_LOANS:3),
    'loan_days'=>(int)setting($pdo,'loan_days',7),
    'library_name'=>setting($pdo,'library_name','eSmartlib'),
    'school_name'=>setting($pdo,'school_name','RHSTI Library'),
    'logo_path'=>setting($pdo,'library_logo','assets/rhsti-school-logo.jpg')
  ];
  $adminSettings['logo_file']=($adminSettings['logo_path']==='assets/rhsti-school-logo.jpg'||(strpos($adminSettings['logo_path'],'uploads/library/')===0&&strpos($adminSettings['logo_path'],'..')===false))&&is_file(_DIR_.'/'.$adminSettings['logo_path'])?$adminSettings['logo_path']:'';
  $adminSettings['staff']=$pdo->query("SELECT id,username,role FROM users WHERE role IN ('admin','librarian') ORDER BY role,username")->fetchAll();
  $adminSettings['student_accounts']=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
  $resetRequests=$pdo->query("SELECT r.user_id,r.requested_at,u.username,u.role FROM password_reset_codes r JOIN users u ON u.id=r.user_id WHERE r.code_hash IS NULL AND r.requested_at>=DATE_SUB(NOW(),INTERVAL 1 DAY) ORDER BY r.requested_at ASC LIMIT 100")->fetchAll();
  $bookingQuery="SELECT r.id,r.student_id,r.book_id,r.message,r.admin_reply,r.status,r.requested_at,s.name,s.student_no,b.title,b.author,b.accession_no,b.available FROM book_requests r JOIN students s ON s.id=r.student_id JOIN books b ON b.id=r.book_id";
  $bookingParams=[];
  if($bookingFilter!=='all'){$bookingQuery.=" WHERE r.status=?";$bookingParams[]=$bookingFilter;}
  $bookingQuery.=" ORDER BY FIELD(r.status,'pending','approved','declined','cancelled','fulfilled'),r.requested_at DESC LIMIT 200";
  $bookingStatement=$pdo->prepare($bookingQuery);$bookingStatement->execute($bookingParams);$bookingRequests=$bookingStatement->fetchAll();
  $bookingCounts=$pdo->query("SELECT status,COUNT(*) AS total FROM book_requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
}

/* ---------- data ---------- */
$u=$pdo->prepare("SELECT * FROM users WHERE id=?");$u->execute([$me]);$u=$u->fetch();
$st=null;
$teacher=null;
if($role==='student'&&!empty($u['student_id'])){$s=$pdo->prepare("SELECT * FROM students WHERE id=?");$s->execute([$u['student_id']]);$st=$s->fetch();}
$hasTeacherAccount=$role==='teacher'&&!empty($u['teacher_id']);
if($hasTeacherAccount){ensure_teacher_schema($pdo);$t=$pdo->prepare("SELECT * FROM teachers WHERE id=?");$t->execute([$u['teacher_id']]);$teacher=$t->fetch();}
$display=$st['name']??($teacher['name']??($u['username']??'User'));
$teacherPhoto=$teacher['photo']??'';
$av=$st?avatar($st,'xl'):($teacher&&teacher_photo_path_ok($teacherPhoto)&&is_file(_DIR_.'/'.$teacherPhoto)
  ?'<img class="pav xl" src="'.h($teacherPhoto).'" alt="'.h($display).'" loading="lazy">'
  :avatar(['name'=>$display],'xl'));
$perm=[
  'admin'=>'Full access: manage students, books, staff accounts and settings.',
  'librarian'=>'Borrow, return and attendance. Can view students; only admins can edit them.',
  'student'=>'View your profile, borrowed books, attendance and QR code.',
  'teacher'=>'View your teacher pass and borrowing history.'
][$role]??'';
head($role==='student'?'My Profile':($tab==='profile'?'My Account':($tab==='resets'?'Password resets':($tab==='bookings'?'Book requests':'Settings'))));ui_css();?>
<style>
.pg .note.bad{background:var(--redbg);border-color:#f9c2bd;color:var(--red)}
.pg .acc{display:flex;gap:26px;align-items:center;flex-wrap:wrap;background:linear-gradient(135deg,#f3faf5,#fff 60%)}
.pg .acc h2{margin:0;font-size:clamp(22px,3vw,30px);font-weight:800;letter-spacing:-.03em}
.pg .acc .chips{justify-content:flex-start;margin:10px 0 12px}
.pg .acc p{margin:0;color:var(--mute);font-size:14px;max-width:520px}
.pg .pill.admin{background:#dbeafe;color:#1d4ed8}.pg .pill.librarian{background:#d1fae5;color:#065f46}.pg .pill.student{background:#ede9fe;color:#6d28d9}.pg .pill.teacher{background:#fef3c7;color:#92400e}
.pg .split{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start}
@media(max-width:900px){.pg .split{grid-template-columns:1fr}}
.pg label.fl{display:block}
.pg label.fl>span{display:block;font-size:12.5px;font-weight:700;color:var(--mute);margin:0 0 6px}
.pg .help{color:var(--mute);font-size:12.5px;margin:10px 0 0}
.pg .kv{display:grid;gap:10px;margin:0}
.pg .kv div{display:flex;justify-content:space-between;gap:16px;padding:12px 14px;border-radius:14px;background:#f8fbf9;font-size:14px}
.pg .kv dt{color:var(--mute);font-weight:600;margin:0}.pg .kv dd{margin:0;font-weight:700;text-align:right;word-break:break-all}
.pg .pwf{position:relative}
.pg .pwf input{padding-right:50px}
.pg .eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:38px;height:38px;margin:0;padding:0;border-radius:11px;background:transparent;color:var(--mute);box-shadow:none}
.pg .eye:hover{background:var(--leaf);transform:translateY(-50%);color:var(--ink)}
.pg .eye svg{width:19px;height:19px}
.pg .str{display:flex;gap:6px;margin:10px 0 0}
.pg .str i{flex:1;height:6px;border-radius:99px;background:#e9efea;transition:background .25s}
.pg .str.s1 i:nth-child(-n+1){background:#ef4444}.pg .str.s2 i:nth-child(-n+2){background:#f59e0b}.pg .str.s3 i:nth-child(-n+3){background:#84cc16}.pg .str.s4 i{background:#16a34a}
.pg .strt{font-size:12px;color:var(--mute);margin:6px 0 0;min-height:16px}
.pg .account-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px}
.pg .account-tabs a{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;border:1px solid var(--line);border-radius:12px;color:var(--mute);font-size:13px;font-weight:800;text-decoration:none}
.pg .account-tabs a.on{background:var(--leaf);border-color:#b9d3c0;color:var(--gd)}
.pg .account-tabs .nav-alert-dot{margin-left:7px;vertical-align:middle}
.pg .settings-split{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start}
.pg .logo-form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:end}
.pg .settings-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
.pg .settings-actions form{display:flex;gap:8px;margin:0}
.pg .settings-actions input{height:42px;width:170px;font-size:13px}
.pg .logo-preview{display:flex;align-items:center;gap:12px;margin:0 0 14px;color:var(--mute);font-size:13px;font-weight:700}
.pg .logo-preview img{width:80px;height:68px;object-fit:contain;padding:7px;border:1px solid var(--line);border-radius:10px}
.pg .request-filter{display:flex;gap:8px;flex-wrap:wrap}
.pg .request-filter a{padding:9px 13px;border:1px solid var(--line);border-radius:12px;color:var(--mute);font-size:12px;font-weight:700;text-decoration:none}
.pg .request-filter a.on{background:var(--leaf);border-color:#b9d3c0;color:var(--gd)}
.pg .request-message{max-width:260px;white-space:pre-wrap;overflow-wrap:anywhere}
.pg .reply-form{display:grid;min-width:220px;gap:7px}
.pg .reply-form textarea{min-height:64px;resize:vertical;padding:10px 12px;border:1px solid var(--line);border-radius:12px;font:inherit}
.pg .request-actions{display:flex;gap:6px;flex-wrap:wrap}
.pg .request-actions button{height:38px;padding:0 12px;font-size:12px}
.pg .teacher-profile-photo{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}
.pg .teacher-profile-photo label.fl{flex:1;min-width:220px}
.pg .teacher-profile-photo input[type=file]{width:100%;padding:9px}
.pg .teacher-profile-remove{display:inline-block;margin-top:4px}
.pg .teacher-profile-remove button{height:42px}
@media(max-width:900px){.pg .settings-split{grid-template-columns:1fr}}
@media(max-width:640px){.pg .logo-form{grid-template-columns:1fr}.pg .settings-actions,.pg .settings-actions form{width:100%}.pg .settings-actions input{flex:1;min-width:0}}
</style>
<div class="pg">

<?php if($flash)echo '<div class="note'.($flash[0]?'':' bad').'">'.ic('info').'<span>'.h($flash[1]).'</span></div>';?>
<?php if($isAdmin):?>
<nav class="account-tabs" aria-label="Account sections">
  <?php foreach(['profile'=>'My account','settings'=>'Settings','resets'=>'Password resets','bookings'=>'Book requests'] as $key=>$label):$tabIcon=['profile'=>'user-circle','settings'=>'settings','resets'=>'clipboard-list','bookings'=>'send'][$key];?>
  <a class="<?=$tab===$key?'on':''?>" href="account.php?tab=<?=$key?>"><?=ic($tabIcon)?><?=h($label)?><?=($key==='resets'&&count($resetRequests)>0)||($key==='bookings'&&(int)($bookingCounts['pending']??0)>0)?'<i class="nav-alert-dot" aria-label="Pending requests"></i>':''?></a>
  <?php endforeach;?>
</nav>
<?php endif;?>

<?php if($tab==='profile'):?>
<!-- Profile -->
<div class="card acc">
  <div><?=$av?></div>
  <div style="flex:1;min-width:240px">
    <h2><?=h($display)?></h2>
    <div class="chips">
      <span class="pill <?=h($role)?>"><?=h(ucfirst($role))?></span>
      <span class="chip id">@<?=h($u['username']??'')?></span>
      <?php if($st&&$st['grade']!==''):?><span class="chip">Grade <?=h($st['grade'])?></span><?php endif;?>
      <?php if($st&&$st['strand']!==''):?><span class="chip"><?=h($st['strand'])?></span><?php endif;?>
      <?php if($st&&$st['section']!==''):?><span class="chip"><?=h($st['section'])?></span><?php endif;?>
    </div>
    <p><?=h($perm)?></p>
    <?php if($st):?><div class="fact"><a class="btn" href="student.php" style="display:inline-flex;gap:8px;align-items:center;height:42px;border-radius:13px"><?=ic('user-circle')?>Open my dashboard &amp; change photo</a></div><?php elseif($teacher):?>
      <div class="fact"><a class="btn" href="teacher.php" style="display:inline-flex;gap:8px;align-items:center;height:42px;border-radius:13px"><?=ic('id-card')?>Open my teacher pass</a></div>
      <form method="post" enctype="multipart/form-data" class="teacher-profile-photo">
        <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
        <input type="hidden" name="teacher_photo_action" value="upload">
        <label class="fl"><span>Profile photo (JPG, PNG, or WEBP; max 8 MB)</span><input type="file" name="teacher_photo" accept="image/jpeg,image/png,image/webp" required></label>
        <button type="submit"><?=ic('upload')?>Save profile photo</button>
      </form>
      <?php if($teacherPhoto!==''):?>
        <form method="post" onsubmit="return confirm('Remove your profile photo?')" class="teacher-profile-remove">
          <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
          <input type="hidden" name="teacher_photo_action" value="remove">
          <button type="submit" class="ghost"><?=ic('trash')?>Remove photo</button>
        </form>
      <?php endif;?>
    <?php endif;?>
  </div>
</div>

<div class="split">
  <!-- Account details -->
  <?php if($isStaff):?>
  <form method="post" class="card">
    <div class="ph"><span class="pico"><?=ic('user-circle')?></span><div><h3>Username</h3><p>This is what you type when signing in.</p></div></div>
    <label class="fl"><span>Username</span><input name="username" value="<?=h($u['username']??'')?>" maxlength="30" autocomplete="username" required></label>
    <div class="fact"><button name="uname" value="1"><?=ic('plus')?>Save username</button></div>
    <p class="help">3-30 characters: letters, numbers, dot, dash or underscore.</p>
  </form>
  <?php else:?>
  <div class="card">
    <div class="ph"><span class="pico"><?=ic('user-circle')?></span><div><h3>Account details</h3><p>Your sign-in information.</p></div></div>
    <dl class="kv">
      <div><dt>Username</dt><dd><?=h($u['username']??'')?></dd></div>
      <?php if($st):?><div><dt>Student ID</dt><dd><?=h($st['student_no'])?></dd></div><?php elseif($teacher):?><div><dt>Teacher ID</dt><dd><?=h($teacher['teacher_no'])?></dd></div><?php endif;?>
      <?php if($role==='student'):?><div><dt>Email</dt><dd><?=h($u['email']??'')?:'Not added yet'?></dd></div><?php endif;?>
      <div><dt>Role</dt><dd><?=h(ucfirst($role))?></dd></div>
    </dl>
    <p class="help">Your username is your <?= $teacher?'Teacher':'Student' ?> ID. Ask the library staff if something is wrong.</p>
    <?php if($role==='student'):?>
      <form method="post" style="margin-top:16px">
        <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
        <label class="fl"><span>Gmail / Email (shown on the back of your ID)</span><input type="email" name="email" maxlength="254" value="<?=h($u['email']??'')?>" placeholder="yourname@gmail.com" autocomplete="email"></label>
        <div class="fact"><button type="submit" name="save_student_email" value="1"><?=ic('plus')?>Save email</button></div>
      </form>
    <?php endif;?>
  </div>
  <?php endif;?>

  <!-- Password -->
  <form method="post" class="card" id="pwform">
    <div class="ph"><span class="pico"><?=ic('user')?></span><div><h3>Change password</h3><p>Use at least 6 characters.</p></div></div>
    <div class="fgrid" style="grid-template-columns:1fr">
      <label class="fl"><span>Current password</span><div class="pwf"><input type="password" name="old" autocomplete="current-password" required><button type="button" class="eye ghost" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></label>
      <label class="fl"><span>New password</span><div class="pwf"><input type="password" name="new" id="np" minlength="6" autocomplete="new-password" required><button type="button" class="eye ghost" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></label>
      <div><div class="str" id="str"><i></i><i></i><i></i><i></i></div><div class="strt" id="strt"></div></div>
      <label class="fl"><span>Repeat new password</span><div class="pwf"><input type="password" name="new2" id="np2" minlength="6" autocomplete="new-password" required><button type="button" class="eye ghost" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></label>
    </div>
    <div class="fact"><button name="pw" value="1">Update password</button></div>
  </form>
</div>
<?php elseif($isAdmin&&$tab==='settings'):?>
<div class="settings-split">
  <form method="post" class="card">
    <input type="hidden" name="admin_action" value="rules"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
    <div class="ph"><span class="pico"><?=ic('books')?></span><div><h3>Loan rules</h3><p>How many books and for how long.</p></div></div>
    <div class="fgrid">
      <label class="fl"><span>Max books per student</span><input type="number" name="max_loans" min="1" max="20" value="<?=$adminSettings['max_loans']?>" required></label>
      <label class="fl"><span>Loan period (days)</span><input type="number" name="loan_days" min="1" max="60" value="<?=$adminSettings['loan_days']?>" required></label>
    </div><div class="fact"><button>Save rules</button></div>
  </form>
  <form method="post" class="card">
    <input type="hidden" name="admin_action" value="info"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
    <div class="ph"><span class="pico"><?=ic('file')?></span><div><h3>Library info</h3><p>Names shown across the system.</p></div></div>
    <div class="fgrid">
      <label class="fl"><span>System name</span><input name="library_name" value="<?=h($adminSettings['library_name'])?>" maxlength="60" required></label>
      <label class="fl"><span>School / library</span><input name="school_name" value="<?=h($adminSettings['school_name'])?>" maxlength="80" required></label>
    </div><div class="fact"><button>Save info</button></div>
  </form>
</div>
<div class="card">
  <div class="ph"><span class="pico"><?=ic('upload')?></span><div><h3>Library logo</h3><p>JPG, PNG, or WEBP; maximum 2 MB.</p></div></div>
  <?php if($adminSettings['logo_file']):?><div class="logo-preview"><img src="<?=h($adminSettings['logo_file'])?>?v=<?=filemtime(_DIR_.'/'.$adminSettings['logo_file'])?>" alt="Current library logo"><span>Current library logo</span></div><?php endif;?>
  <form method="post" enctype="multipart/form-data" class="logo-form">
    <input type="hidden" name="admin_action" value="logo_up"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="MAX_FILE_SIZE" value="2097152">
    <label class="fl"><span>Choose logo image</span><input type="file" name="logo" accept="image/jpeg,image/png,image/webp" required></label><div class="fact"><button>Upload logo</button></div>
  </form>
  <?php if($adminSettings['logo_file']):?><form method="post" class="fact"><input type="hidden" name="admin_action" value="logo_rm"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><button class="ghost">Remove logo</button></form><?php endif;?>
</div>
<form method="post" class="card">
  <input type="hidden" name="admin_action" value="addstaff"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
  <div class="ph"><span class="pico"><?=ic('plus')?></span><div><h3>Add staff account</h3><p>Create an admin or librarian login.</p></div></div>
  <div class="fgrid">
    <label class="fl"><span>Username</span><input name="username" autocomplete="off" required></label>
    <label class="fl"><span>Password (6+ chars)</span><input type="password" name="password" autocomplete="new-password" minlength="6" required></label>
    <label class="fl"><span>Role</span><select name="role"><option value="librarian">Librarian</option><option value="admin">Admin</option></select></label>
  </div><div class="fact"><button>Create account</button></div>
</form>
<div class="card">
  <div class="ph"><span class="pico"><?=ic('user')?></span><div><h3>Staff accounts</h3><p><?=$adminSettings['student_accounts']?> student accounts are created on first login.</p></div><span class="badge"><?=count($adminSettings['staff'])?> staff</span></div>
  <div class="tw"><table class="rt"><tr><th>Username</th><th>Role</th><th style="text-align:right">Actions</th></tr>
  <?php foreach($adminSettings['staff'] as $staff):$self=((int)$staff['id']===$me);?>
    <tr><td data-label="Username"><?=h($staff['username'])?><?=$self?'<span class="you">You</span>':''?></td><td data-label="Role"><span class="pill <?=h($staff['role'])?>"><?=h(ucfirst($staff['role']))?></span></td>
    <td data-label="Actions"><div class="settings-actions">
      <form method="post"><input type="hidden" name="admin_action" value="resetpw"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="password" name="pw" placeholder="New password" minlength="6" autocomplete="new-password" required><button class="ghost" name="resetpw" value="<?=(int)$staff['id']?>">Reset</button></form>
      <?php if(!$self):?><form method="post" onsubmit="return confirm('Delete this staff account?')"><input type="hidden" name="admin_action" value="delstaff"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><button class="danger" name="delstaff" value="<?=(int)$staff['id']?>">Delete</button></form><?php endif;?>
    </div></td></tr>
  <?php endforeach;?></table></div>
</div>
<?php elseif($isAdmin&&$tab==='resets'):?>
<?php if($issuedCode):?><div class="card" role="status" style="border:2px solid var(--g)"><div class="ph"><span class="pico"><?=ic('info')?></span><div><h3>Give this code to <?=h($issuedCode['username'])?></h3><p>This one-time code expires at <?=h($issuedCode['expires_at'])?>. Do not share it with anyone else.</p></div></div><p style="font-size:30px;font-weight:800;letter-spacing:.18em;text-align:center;margin:20px 0"><?=h($issuedCode['code'])?></p></div><?php endif;?>
<div class="card">
  <div class="ph"><span class="pico"><?=ic('clipboard-list')?></span><div><h3>Pending password reset requests</h3><p>Confirm the user's identity before generating a one-time code.</p></div><span class="badge"><?=count($resetRequests)?> pending</span></div>
  <?php if(!$resetRequests):?><p class="help">No recent reset requests.</p><?php else:?><div class="tw"><table class="rt"><tr><th>Username</th><th>Role</th><th>Requested</th><th>Action</th></tr>
  <?php foreach($resetRequests as $request):?><tr><td data-label="Username"><?=h($request['username'])?></td><td data-label="Role"><?=h(ucfirst($request['role']))?></td><td data-label="Requested"><?=h($request['requested_at'])?></td><td data-label="Action"><form method="post"><input type="hidden" name="admin_action" value="generate_code"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><button name="generate_code" value="<?=(int)$request['user_id']?>">Generate code</button></form></td></tr><?php endforeach;?>
  </table></div><?php endif;?>
</div>
<?php elseif($isAdmin&&$tab==='bookings'):?>
<div class="card">
  <div class="ph"><span class="pico"><?=ic('send')?></span><div><h3>Student booking requests</h3><p>Review messages, reply, and approve or decline bookings.</p></div><span class="badge"><?=count($bookingRequests)?> shown</span></div>
  <nav class="request-filter" aria-label="Filter booking requests"><?php foreach($validBookingFilters as $status):?><a class="<?=$bookingFilter===$status?'on':''?>" href="account.php?tab=bookings&amp;booking_status=<?=h($status)?>"><?=h(ucfirst($status))?><?=$status!=='all'?' ('.(int)($bookingCounts[$status]??0).')':''?></a><?php endforeach;?></nav>
  <?php if(!$bookingRequests):?><p class="help">No booking requests in this view.</p><?php else:?><div class="tw" style="margin-top:14px"><table class="rt">
  <tr><th>Student</th><th>Book</th><th>Message</th><th>Status</th><th>Requested</th><th>Reply / action</th></tr>
  <?php foreach($bookingRequests as $request):$statusClass=['approved'=>'ok','fulfilled'=>'ok','pending'=>'warn','declined'=>'late','cancelled'=>'late'][$request['status']]??'';?>
  <tr><td data-label="Student"><?=h($request['name'])?><small class="muted" style="display:block"><?=h($request['student_no'])?></small></td>
    <td data-label="Book"><?=h($request['title'])?><small class="muted" style="display:block"><?=h($request['author']??'')?> · <?=h($request['accession_no'])?></small></td>
    <td data-label="Message"><div class="request-message"><?=h($request['message']?:'No message')?></div><?php if($request['admin_reply']):?><small class="muted" style="display:block;margin-top:8px">Previous reply: <?=h($request['admin_reply'])?></small><?php endif;?></td>
    <td data-label="Status"><span class="pill <?=$statusClass?>"><?=h(ucfirst($request['status']))?></span></td><td data-label="Requested"><?=h(date('M j, Y · h:i A',strtotime($request['requested_at'])))?></td>
    <td data-label="Reply / action"><form method="post" class="reply-form"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="request_id" value="<?=(int)$request['id']?>">
      <textarea name="admin_reply" maxlength="500" placeholder="Reply to student (optional)"><?=h($request['admin_reply']??'')?></textarea><div class="request-actions"><button name="admin_action" value="reply" class="ghost">Send reply</button><?php if($request['status']==='pending'):?><button name="admin_action" value="approve">Approve</button><button name="admin_action" value="decline" class="danger">Decline</button><?php endif;?></div>
    </form></td></tr><?php endforeach;?></table></div><?php endif;?>
</div>
<?php endif;?>
<?php foot('<script>
(function(){
  document.querySelectorAll("#pwform .eye").forEach(function(b){b.onclick=function(){var i=b.parentNode.querySelector("input");i.type=i.type==="password"?"text":"password";i.focus();};});
  var np=document.getElementById("np"),np2=document.getElementById("np2"),s=document.getElementById("str"),t=document.getElementById("strt");
  if(!np||!np2||!s||!t)return;
  var L=["","Too weak","Okay","Good","Strong"];
  np.addEventListener("input",function(){
    var v=np.value,n=0; if(v.length>=6)n++; if(v.length>=10)n++; if(/[A-Z]/.test(v)&&/[a-z]/.test(v))n++; if(/\d/.test(v)&&/[^A-Za-z0-9]/.test(v))n++;
    if(!v)n=0; s.className="str"+(n?" s"+n:""); t.textContent=L[n];
  });
  document.getElementById("pwform").addEventListener("submit",function(e){
    if(np.value!==np2.value){e.preventDefault();np2.setCustomValidity("Passwords do not match");np2.reportValidity();}
  });
  np2.addEventListener("input",function(){np2.setCustomValidity("");});
})();
</script>');
