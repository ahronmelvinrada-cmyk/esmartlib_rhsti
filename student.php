<?php require 'config.php';require 'ui2026.php';auth(['student']);
$sid=$_SESSION['u']['student_id'];$m='';
if(isset($_POST['pw'])){
  $u=$pdo->prepare("SELECT password FROM users WHERE id=?");$u->execute([$_SESSION['u']['id']]);
  if(!password_verify($_POST['old'],$u->fetchColumn()))$m='Current password is incorrect.';
  elseif(strlen($_POST['new'])<6||$_POST['new']!==$_POST['new2'])$m='New password must be 6+ characters and match.';
  else{$pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['new'],PASSWORD_DEFAULT),$_SESSION['u']['id']]);$m='Password changed.';}
}
photo_col($pdo);
if(isset($_POST['photo_rm'])){photo_remove($pdo,$sid);$m='Profile photo removed.';}
elseif(isset($_POST['photo_up'])){
  $e=photo_save($pdo,$sid,$_FILES['photo']??[]);
  if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='fetch'){header('Content-Type: application/json');echo json_encode(['ok'=>!$e,'msg'=>$e?:'Profile photo updated.']);exit;}
  $m=$e?:'Profile photo updated.';
}
if(isset($_GET['ph']))$m='Profile photo updated.';
$st=$pdo->prepare("SELECT * FROM students WHERE id=?");$st->execute([$sid]);$st=$st->fetch();
$loans=$pdo->prepare("SELECT b.title,b.author,r.borrowed_at,r.due_date,r.returned_at FROM borrowings r JOIN books b ON b.id=r.book_id WHERE r.student_id=? ORDER BY r.borrowed_at DESC");$loans->execute([$sid]);$loans=$loans->fetchAll();
$att=$pdo->prepare("SELECT time_in,time_out FROM attendance WHERE student_id=? ORDER BY id DESC LIMIT 30");$att->execute([$sid]);$att=$att->fetchAll();
$today=date('Y-m-d');$cur=array_filter($loans,fn($l)=>!$l['returned_at']);
$over=count(array_filter($cur,fn($l)=>$l['due_date']<$today));
$inside=$att&&substr($att[0]['time_in'],0,10)==$today&&!$att[0]['time_out'];
$month=count(array_filter($att,fn($a)=>substr($a['time_in'],0,7)==date('Y-m')));
$hist=array_filter($loans,fn($l)=>$l['returned_at']);
$pct=MAX_LOANS>0?min(100,round(count($cur)/MAX_LOANS*100)):0;
head('My Dashboard');ui_css();?>
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
</style>
<div class="pg">

<?php if($m)echo '<div class="note'.(in_array($m,['Password changed.','Profile photo updated.','Profile photo removed.'])?'':' bad').'">'.ic('info').'<span>'.h($m).'</span></div>';?>

<!-- Profile -->
<div class="card hero2">
  <form method="post" enctype="multipart/form-data" id="phform" class="pcol">
    <input type="hidden" name="photo_up" value="1">
    <div class="pwrap"><?=avatar($st,'xl')?>
      <label class="camb" for="photo" title="Change photo"><?=ic('camera')?></label>
      <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="hid">
    </div>
    <label class="lbtn" for="photo"><?=ic('upload')?>Change photo</label>
    <?php if(!empty($st['photo'])):?><button type="submit" name="photo_rm" value="1" class="ghost rm" onclick="return confirm('Remove your profile photo?')"><?=ic('trash')?>Remove</button><?php endif;?>
    <small class="phint">JPG, PNG or WEBP</small>
  </form>
  <div style="flex:1;min-width:240px">
    <h2><?=h($st['name'])?></h2>
    <div class="chips">
      <span class="chip id">ID: <?=h($st['student_no'])?></span>
      <?php if($st['grade']!==''):?><span class="chip">Grade <?=h($st['grade'])?></span><?php endif;?>
      <?php if($st['strand']!==''):?><span class="chip"><?=h($st['strand'])?></span><?php endif;?>
      <?php if($st['section']!==''):?><span class="chip"><?=h($st['section'])?></span><?php endif;?>
      <?php if($st['track']!==''):?><span class="chip"><?=h($st['track'])?></span><?php endif;?>
    </div>
    <div class="where"><i class="dot <?=$inside?'live':''?>"></i>
      <span class="<?=$inside?'':'muted'?>">Library today: <?=$inside?'Inside (timed in '.date('h:i A',strtotime($att[0]['time_in'])).')':'Not in library'?></span>
    </div>
  </div>
  <div class="qcap"><div class="qr" data-code="STU:<?=h($st['student_no'])?>"></div><small>My QR code (show to scanner)</small></div>
</div>

<!-- Stats -->
<div class="sgrid">
  <div class="sc t1"><div class="ico"><?=ic('book')?></div><span>Books borrowed now</span><b><?=count($cur)?>/<?=MAX_LOANS?></b>
    <div class="meter <?=$pct>=100?'low':($pct>=67?'mid':'')?>"><i style="width:<?=$pct?>%"></i></div></div>
  <div class="sc t2"><div class="ico"><?=ic('clock')?></div><span>Overdue</span><b class="<?=$over?'bad':''?>"><?=$over?></b></div>
  <div class="sc t3"><div class="ico"><?=ic('return')?></div><span>Total books borrowed</span><b><?=count($loans)?></b></div>
  <div class="sc t4"><div class="ico"><?=ic('user')?></div><span>Library visits this month</span><b><?=$month?></b></div>
</div>

<!-- Currently borrowed -->
<div class="card">
  <div class="ph"><span class="pico"><?=ic('book')?></span><div><h3>Currently borrowed</h3><p>Return on or before the due date.</p></div><span class="badge"><?=count($cur)?> book<?=count($cur)==1?'':'s'?></span></div>
  <div class="tw"><table class="rt">
    <tr><th>Book</th><th>Author</th><th>Borrowed</th><th>Due</th><th>Status</th></tr>
    <?php foreach($cur as $l){$d=(int)((strtotime($l['due_date'])-strtotime($today))/86400);
      $cls=$d<0?'late':($d==0?'warn':'ok');
      $txt=$d<0?'Overdue by '.-$d.' day(s)':($d==0?'Due today':$d.' day(s) left');
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

<!-- Change password -->
<form method="post" class="card">
  <div class="ph"><span class="pico"><?=ic('user')?></span><div><h3>Change password</h3><p>Use at least 6 characters.</p></div></div>
  <div class="pwgrid">
    <input type="password" name="old" placeholder="Current" required>
    <input type="password" name="new" placeholder="New (6+ chars)" required>
    <input type="password" name="new2" placeholder="Repeat new" required>
    <button name="pw" value="1">Update</button>
  </div>
</form>

</div>
<?php foot('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>renderQR();
(function(){
  var pf=document.getElementById("photo"); if(!pf) return;
  pf.onchange=async function(){
    var f=pf.files[0]; if(!f) return;
    try{
      var bm=await createImageBitmap(f), s=Math.min(bm.width,bm.height), S=600;
      var c=document.createElement("canvas"); c.width=S; c.height=S;
      c.getContext("2d").drawImage(bm,(bm.width-s)/2,(bm.height-s)/2,s,s,0,0,S,S);
      var blob=await new Promise(function(r){c.toBlob(r,"image/jpeg",0.86);});
      var fd=new FormData(); fd.append("photo_up","1"); fd.append("photo",blob,"photo.jpg");
      var r=await fetch(location.pathname,{method:"POST",body:fd,headers:{"X-Requested-With":"fetch"}});
      var j=await r.json();
      if(j.ok){location.href=location.pathname+"?ph=1";}
      else{alert(j.msg);pf.value="";}
    }catch(e){document.getElementById("phform").submit();}
  };
})();</script>');