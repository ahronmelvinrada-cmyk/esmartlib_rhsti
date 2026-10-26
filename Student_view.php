<?php require 'config.php';require 'ui2026.php';auth(['admin','librarian']);
photo_col($pdo);
$id=(int)($_GET['id']??0);
$isAdmin=($_SESSION['u']['role']??'')==='admin';
$S=$pdo->prepare("SELECT * FROM students WHERE id=?");$S->execute([$id]);$st=$S->fetch();

/* ---------- admin actions (then redirect so refresh won't resubmit) ---------- */
if($st&&$isAdmin&&$_SERVER['REQUEST_METHOD']==='POST'){
  $msg='';$ok=true;
  if(isset($_POST['save'])){
    $no=trim($_POST['student_no']??'');$nm=trim($_POST['name']??'');
    if($no===''||$nm===''){$ok=false;$msg='Student ID and full name are required.';}
    else{
      try{
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE students SET student_no=?,name=?,gender=?,track=?,strand=?,grade=?,section=? WHERE id=?")
            ->execute([$no,$nm,trim($_POST['gender']??''),trim($_POST['track']??''),trim($_POST['strand']??''),trim($_POST['grade']??''),trim($_POST['section']??''),$id]);
        if($no!==$st['student_no'])$pdo->prepare("UPDATE users SET username=? WHERE student_id=?")->execute([$no,$id]); // keep the student's login in sync
        $pdo->commit();$msg='Student information saved.';
      }catch(Exception $e){if($pdo->inTransaction())$pdo->rollBack();$ok=false;$msg='Could not save: that Student ID is already in use.';}
    }
  }elseif(isset($_POST['pwreset'])){
    $pdo->prepare("UPDATE users SET password=? WHERE student_id=?")->execute([password_hash($st['student_no'],PASSWORD_DEFAULT),$id]);
    $msg='Password reset. The student can now sign in with their Student ID.';
  }elseif(isset($_POST['photo_rm'])){photo_remove($pdo,$id);$msg='Photo removed.';}
  elseif(isset($_POST['photo_up'])){$e=photo_save($pdo,$id,$_FILES['photo']??[]);$ok=!$e;$msg=$e?:'Photo updated.';}
  if($msg){$_SESSION['flash']=[$ok,$msg];header('Location: student_view.php?id='.$id);exit;}
}

head('Student Profile');ui_css();
$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
if(!$st){echo '<div class="pg"><a class="back" href="students.php">&larr; Back to students</a><div class="card"><div class="empty"><b>Student not found</b>This student may have been deleted.</div></div></div>';foot('');exit;}

/* ---------- data ---------- */
$loans=$pdo->prepare("SELECT b.title,b.author,r.borrowed_at,r.due_date,r.returned_at FROM borrowings r JOIN books b ON b.id=r.book_id WHERE r.student_id=? ORDER BY r.borrowed_at DESC");$loans->execute([$id]);$loans=$loans->fetchAll();
$att=$pdo->prepare("SELECT time_in,time_out FROM attendance WHERE student_id=? ORDER BY id DESC LIMIT 30");$att->execute([$id]);$att=$att->fetchAll();
$today=date('Y-m-d');$cur=array_filter($loans,fn($l)=>!$l['returned_at']);
$over=count(array_filter($cur,fn($l)=>$l['due_date']<$today));
$inside=$att&&substr($att[0]['time_in'],0,10)==$today&&!$att[0]['time_out'];
$month=count(array_filter($att,fn($a)=>substr($a['time_in'],0,7)==date('Y-m')));
$hist=array_filter($loans,fn($l)=>$l['returned_at']);
$pct=MAX_LOANS>0?min(100,round(count($cur)/MAX_LOANS*100)):0;
$d=$isAdmin?'':'disabled';
$genders=['Male','Female'];if($st['gender']!==''&&!in_array($st['gender'],$genders))$genders[]=$st['gender'];
?>
<style>
.pg .note.bad{background:var(--redbg);border-color:#f9c2bd;color:var(--red)}
.pg .hero2{display:flex;gap:28px;align-items:center;flex-wrap:wrap;background:linear-gradient(135deg,#f3faf5,#fff 60%)}
.pg .hero2 .qr{padding:14px;border-radius:20px;box-shadow:0 14px 30px -18px rgba(15,61,38,.4)}
.pg .hero2 .qcap{display:flex;flex-direction:column;align-items:center;gap:8px}
.pg .hero2 .qcap small{color:var(--mute);font-size:12px;font-weight:600}
.pg .hero2 h2{margin:0;font-size:clamp(22px,3vw,30px);font-weight:800;letter-spacing:-.03em}
.pg .hero2 .chips{justify-content:flex-start;margin:10px 0 14px}
.pg .where{display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:14px}
.pg .dot{width:10px;height:10px;border-radius:50%;background:#c4cdc7}
.pg .dot.live{background:#10b981;animation:pulse 2s infinite}
@keyframes pulse{70%{box-shadow:0 0 0 8px rgba(16,185,129,0)}0%{box-shadow:0 0 0 0 rgba(16,185,129,.55)}100%{box-shadow:0 0 0 0 rgba(16,185,129,0)}}
.pg .sgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.pg .sc{position:relative;overflow:hidden;background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:20px}
.pg .sc::after{content:"";position:absolute;right:-30px;bottom:-50px;width:140px;height:140px;border-radius:50%;background:var(--tb);opacity:.55;filter:blur(26px);pointer-events:none}
.pg .sc .ico{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:var(--tb);color:var(--t);margin-bottom:14px}
.pg .sc .ico .ic{width:21px;height:21px}
.pg .sc span{display:block;color:var(--mute);font-size:13px;font-weight:600}
.pg .sc b{display:block;font-size:34px;line-height:1.1;font-weight:800;letter-spacing:-.04em;margin-top:4px}
.pg .sc b.bad{color:var(--red)}
.pg .sc .meter{margin-top:10px}
.pg .t1{--t:#059669;--tb:#d1fae5}.pg .t2{--t:#e11d48;--tb:#ffe4e6}.pg .t3{--t:#2563eb;--tb:#dbeafe}.pg .t4{--t:#7c3aed;--tb:#ede9fe}
.pg .two{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:18px;align-items:start}
.pg .two table{min-width:0}
.pg .pill.warn{background:#fef3c7;color:#92400e}
.pg .pwgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr)) auto;gap:12px;align-items:center}
@media(max-width:700px){.pg .pwgrid{grid-template-columns:1fr}.pg .two{grid-template-columns:1fr}}

/* ---------- Phone layout ---------- */
@media(max-width:640px){
  .pg{gap:14px}
  .pg .card{padding:16px;border-radius:18px}
  /* profile: centered */
  .pg .hero2{flex-direction:column;text-align:center;gap:18px}
  .pg .hero2 .chips{justify-content:center}
  .pg .where{justify-content:center}
  /* stats: 2 per row, compact */
  .pg .sgrid{grid-template-columns:1fr 1fr;gap:12px}
  .pg .sc{padding:14px}
  .pg .sc .ico{width:36px;height:36px;margin-bottom:10px}
  .pg .sc b{font-size:26px}
  .pg .sc span{font-size:12px}
  /* tables -> stacked cards (no sideways scrolling) */
  .pg .tw{margin:0;padding:0;overflow:visible}
  .pg table.rt,.pg table.rt tbody{display:block;min-width:0;width:100%}
  .pg table.rt tr:first-child{display:none}
  .pg table.rt tr{display:block;background:#f8fbf9;border-radius:14px;padding:8px 14px;margin-bottom:10px}
  .pg table.rt td{display:flex;justify-content:space-between;align-items:center;gap:14px;background:transparent;padding:7px 0;border-radius:0!important;text-align:right}
  .pg table.rt td::before{content:attr(data-label);color:var(--mute);font-size:12px;font-weight:700;text-align:left;flex:none}
  .pg table.rt td:first-child{font-size:15px;border-bottom:1px solid var(--line);padding-bottom:9px;margin-bottom:2px}
  .pg table.rt td.e{justify-content:center;text-align:center}
  .pg table.rt td.e::before{content:none}
  .pg .ph .badge{margin-left:0}
}

/* ---- admin student view ---- */
.pg .back{display:inline-flex;align-items:center;gap:6px;color:var(--g);font-weight:700;text-decoration:none;font-size:14px;width:max-content}
.pg .back:hover{text-decoration:underline}
.pg label.fl>span{display:block;font-size:12.5px;font-weight:700;color:var(--mute);margin:0 0 6px}
.pg input:disabled,.pg select:disabled{background:#f1f5f3;color:var(--ink);opacity:1;cursor:not-allowed}
.pg .hero2 .pav.xl{box-shadow:0 14px 30px -18px rgba(15,61,38,.5)}
.pg .acts{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.pg .acts form{margin:0}
.pg .split{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start}
@media(max-width:800px){.pg .split{grid-template-columns:1fr}}
.pg .note.bad{background:var(--redbg);border-color:#f9c2bd;color:var(--red)}
</style>
<div class="pg">

<a class="back" href="students.php">&larr; Back to students</a>

<?php if($flash)echo '<div class="note'.($flash[0]?'':' bad').'">'.ic('info').'<span>'.h($flash[1]).'</span></div>';?>

<!-- Profile -->
<div class="card hero2">
  <div><?=avatar($st,'xl')?></div>
  <div style="flex:1;min-width:240px">
    <h2><?=h($st['name'])?></h2>
    <div class="chips">
      <span class="chip id">ID: <?=h($st['student_no'])?></span>
      <?php if($st['gender']!==''):?><span class="chip"><?=h($st['gender'])?></span><?php endif;?>
      <?php if($st['grade']!==''):?><span class="chip">Grade <?=h($st['grade'])?></span><?php endif;?>
      <?php if($st['strand']!==''):?><span class="chip"><?=h($st['strand'])?></span><?php endif;?>
      <?php if($st['section']!==''):?><span class="chip"><?=h($st['section'])?></span><?php endif;?>
      <?php if($st['track']!==''):?><span class="chip"><?=h($st['track'])?></span><?php endif;?>
    </div>
    <div class="where"><i class="dot <?=$inside?'live':''?>"></i>
      <span class="<?=$inside?'':'muted'?>">Library today: <?=$inside?'Inside (timed in '.date('h:i A',strtotime($att[0]['time_in'])).')':'Not in library'?></span>
    </div>
  </div>
  <div class="qcap"><div class="qr" data-code="STU:<?=h($st['student_no'])?>"></div><small>Student QR code</small></div>
</div>

<!-- Stats -->
<div class="sgrid">
  <div class="sc t1"><div class="ico"><?=ic('book')?></div><span>Books borrowed now</span><b><?=count($cur)?>/<?=MAX_LOANS?></b>
    <div class="meter <?=$pct>=100?'low':($pct>=67?'mid':'')?>"><i style="width:<?=$pct?>%"></i></div></div>
  <div class="sc t2"><div class="ico"><?=ic('clock')?></div><span>Overdue</span><b class="<?=$over?'bad':''?>"><?=$over?></b></div>
  <div class="sc t3"><div class="ico"><?=ic('return')?></div><span>Total books borrowed</span><b><?=count($loans)?></b></div>
  <div class="sc t4"><div class="ico"><?=ic('user')?></div><span>Library visits this month</span><b><?=$month?></b></div>
</div>

<!-- Edit information -->
<form method="post" class="card">
  <div class="ph"><span class="pico"><?=ic('user')?></span><div><h3>Student information</h3><p><?=$isAdmin?'Edit the details, then save.':'View only. Only an admin can edit.'?></p></div></div>
  <div class="fgrid">
    <label class="fl"><span>Student ID</span><input name="student_no" value="<?=h($st['student_no'])?>" required <?=$d?>></label>
    <label class="fl"><span>Full name</span><input name="name" value="<?=h($st['name'])?>" required <?=$d?>></label>
    <label class="fl"><span>Gender</span><select name="gender" <?=$d?>><?php foreach($genders as $g):?><option <?=$g===$st['gender']?'selected':''?>><?=h($g)?></option><?php endforeach;?></select></label>
    <label class="fl"><span>Track</span><input name="track" value="<?=h($st['track'])?>" <?=$d?>></label>
    <label class="fl"><span>Strand</span><input name="strand" value="<?=h($st['strand'])?>" <?=$d?>></label>
    <label class="fl"><span>Grade</span><input name="grade" value="<?=h($st['grade'])?>" <?=$d?>></label>
    <label class="fl"><span>Section</span><input name="section" value="<?=h($st['section'])?>" <?=$d?>></label>
  </div>
  <?php if($isAdmin):?>
    <div class="fact"><button name="save" value="1"><?=ic('plus')?>Save changes</button><a class="link" href="student_view.php?id=<?=$id?>">Discard</a></div>
    <p class="hint">Changing the Student ID also changes the student&rsquo;s login username and QR code. Reprint their QR if you change it.</p>
  <?php endif;?>
</form>

<?php if($isAdmin):?>
<div class="split">
  <!-- Photo -->
  <form method="post" enctype="multipart/form-data" class="card">
    <div class="ph"><span class="pico"><?=ic('camera')?></span><div><h3>Profile photo</h3><p>JPG, PNG or WEBP. Cropped to a square.</p></div></div>
    <div class="importrow">
      <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
      <button name="photo_up" value="1"><?=ic('upload')?>Upload</button>
    </div>
    <?php if(!empty($st['photo'])):?>
      <div class="fact"><button name="photo_rm" value="1" class="ghost rm" formnovalidate onclick="return confirm('Remove this student photo?')"><?=ic('trash')?>Remove photo</button></div>
    <?php endif;?>
  </form>

  <!-- Account -->
  <form method="post" class="card" onsubmit="return confirm('Reset this student password to their Student ID?')">
    <div class="ph"><span class="pico"><?=ic('user')?></span><div><h3>Account</h3><p>Forgot password? Reset it here.</p></div></div>
    <div class="acts"><button name="pwreset" value="1" class="ghost">Reset password to Student ID</button></div>
    <p class="hint">The student can then sign in with their Student ID and change the password from their dashboard.</p>
  </form>
</div>
<?php endif;?>

<!-- Currently borrowed -->
<div class="card">
  <div class="ph"><span class="pico"><?=ic('book')?></span><div><h3>Currently borrowed</h3></div><span class="badge"><?=count($cur)?> book<?=count($cur)==1?'':'s'?></span></div>
  <div class="tw"><table class="rt">
    <tr><th>Book</th><th>Author</th><th>Borrowed</th><th>Due</th><th>Status</th></tr>
    <?php foreach($cur as $l){$dd=(int)((strtotime($l['due_date'])-strtotime($today))/86400);
      $cls=$dd<0?'late':($dd==0?'warn':'ok');
      $txt=$dd<0?'Overdue by '.-$dd.' day(s)':($dd==0?'Due today':$dd.' day(s) left');
      echo '<tr><td data-label="Book">'.h($l['title']).'</td><td class="muted" data-label="Author">'.h($l['author']).'</td><td class="muted" data-label="Borrowed">'.h(substr($l['borrowed_at'],0,10)).'</td><td data-label="Due">'.h($l['due_date']).'</td><td data-label="Status"><span class="pill '.$cls.'">'.h($txt).'</span></td></tr>';}
    if(!$cur)echo '<tr><td colspan="5" class="muted e" style="text-align:center;padding:28px">No borrowed books.</td></tr>';?>
  </table></div>
</div>

<!-- History + attendance -->
<div class="two">
  <div class="card">
    <div class="ph"><span class="pico"><?=ic('return')?></span><div><h3>Borrowing history</h3></div></div>
    <div class="tw"><table class="rt">
      <tr><th>Book</th><th>Borrowed</th><th>Returned</th></tr>
      <?php foreach($hist as $l)echo '<tr><td data-label="Book">'.h($l['title']).'</td><td class="muted" data-label="Borrowed">'.h(substr($l['borrowed_at'],0,10)).'</td><td data-label="Returned">'.h(substr($l['returned_at'],0,10)).'</td></tr>';
      if(!$hist)echo '<tr><td colspan="3" class="muted e" style="text-align:center;padding:28px">No returned books yet.</td></tr>';?>
    </table></div>
  </div>
  <div class="card">
    <div class="ph"><span class="pico"><?=ic('clock')?></span><div><h3>Attendance (last 30)</h3></div></div>
    <div class="tw"><table class="rt">
      <tr><th>Date</th><th>Time-In</th><th>Time-Out</th></tr>
      <?php foreach($att as $a)echo '<tr><td data-label="Date">'.h(substr($a['time_in'],0,10)).'</td><td data-label="Time-In">'.h(date('h:i A',strtotime($a['time_in']))).'</td><td data-label="Time-Out">'.($a['time_out']?h(date('h:i A',strtotime($a['time_out']))):'-').'</td></tr>';
      if(!$att)echo '<tr><td colspan="3" class="muted e" style="text-align:center;padding:28px">No visits recorded yet.</td></tr>';?>
    </table></div>
  </div>
</div>

</div>
<?php foot('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>renderQR()</script>');