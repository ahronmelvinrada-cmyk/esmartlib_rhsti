<?php header('Location: account.php?tab=settings');exit;
require 'config.php';require 'Ui2026.PHP';auth(['admin']);
settings_init($pdo);
$me=(int)$_SESSION['u']['id'];

if($_SERVER['REQUEST_METHOD']==='POST'){
  $ok=true;$msg='';
  if(isset($_POST['rules'])){
    setting_set($pdo,'max_loans',max(1,min(20,(int)$_POST['max_loans'])));
    setting_set($pdo,'loan_days',max(1,min(60,(int)$_POST['loan_days'])));
    $msg='Loan rules saved.';
  }elseif(isset($_POST['info'])){
    $newLibraryName=trim($_POST['library_name']??'');
    $newSchoolName=trim($_POST['school_name']??'');
    if($newLibraryName===''||$newSchoolName===''){$ok=false;$msg='System name and school/library name are both required.';}
    elseif(strlen($newLibraryName)>60||strlen($newSchoolName)>80){$ok=false;$msg='System name must be 60 characters or less and school/library name 80 characters or less.';}
    else{
      setting_set($pdo,'library_name',$newLibraryName);
      setting_set($pdo,'school_name',$newSchoolName);
      $msg='Library info saved.';
    }
  }elseif(isset($_POST['logo_up'])){
    $file=$_FILES['logo']??[];
    $uploadError=$file['error']??UPLOAD_ERR_NO_FILE;
    if($uploadError===UPLOAD_ERR_NO_FILE){$ok=false;$msg='Choose a logo image first.';}
    elseif($uploadError===UPLOAD_ERR_INI_SIZE||$uploadError===UPLOAD_ERR_FORM_SIZE){$ok=false;$msg='The logo is too large for the server.';}
    elseif($uploadError!==UPLOAD_ERR_OK){$ok=false;$msg='Logo upload failed. Please try again.';}
    elseif(($file['size']??0)>2*1024*1024){$ok=false;$msg='The logo must be 2 MB or smaller.';}
    else{
      $image=@getimagesize($file['tmp_name']??'');
      $extensions=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'];
      if(!$image||!isset($extensions[$image[2]])){$ok=false;$msg='Use a JPG, PNG, or WEBP image for the logo.';}
      else{
        $dir=__DIR__.'/uploads/library';
        if(!is_dir($dir)&&!@mkdir($dir,0755,true)){$ok=false;$msg='Cannot create the logo upload folder. Check hosting folder permissions.';}
        elseif(!is_writable($dir)){$ok=false;$msg='The logo upload folder is not writable. Check hosting folder permissions.';}
        else{
          $name='logo_'.bin2hex(random_bytes(8)).'.'.$extensions[$image[2]];
          $destination=$dir.'/'.$name;
          $protection=$dir.'/.htaccess';
          if(!is_file($protection)&&file_put_contents($protection,"<FilesMatch \"\\.(php|phtml|phar)$\">\nRequire all denied\n</FilesMatch>\n")===false){$ok=false;$msg='Could not secure the logo upload folder. Check hosting folder permissions.';}
          elseif(!is_uploaded_file($file['tmp_name']??'')||!move_uploaded_file($file['tmp_name'],$destination)){$ok=false;$msg='Could not save the logo. Please try again.';}
          else{
            $oldLogo=setting($pdo,'library_logo','');
            setting_set($pdo,'library_logo','uploads/library/'.$name);
            if($oldLogo&&strpos($oldLogo,'uploads/library/')===0&&strpos($oldLogo,'..')===false&&is_file(__DIR__.'/'.$oldLogo))@unlink(__DIR__.'/'.$oldLogo);
            $msg='Library logo updated.';
          }
        }
      }
    }
  }elseif(isset($_POST['logo_rm'])){
    $oldLogo=setting($pdo,'library_logo','');
    setting_set($pdo,'library_logo','');
    if($oldLogo&&strpos($oldLogo,'uploads/library/')===0&&strpos($oldLogo,'..')===false&&is_file(__DIR__.'/'.$oldLogo))@unlink(__DIR__.'/'.$oldLogo);
    $msg='Library logo removed.';
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
    $id=(int)$_