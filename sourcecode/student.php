<?php require 'config.php';require 'Ui2026.PHP';auth(['student']);
$sid=$_SESSION['u']['student_id'];$m='';
ensure_user_email_schema($pdo);
photo_col($pdo);
ensure_book_request_schema($pdo);
if(isset($_POST['photo_rm'])){photo_remove($pdo,$sid);$m='Profile photo removed.';}
elseif(isset($_POST['photo_up'])){
  $e=photo_save($pdo,$sid,$_FILES['photo']??[]);
  if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='fetch'){header('Content-Type: application/json');echo json_encode(['ok'=>!$e,'msg'=>$e?:'Profile photo updated.']);exit;}
  $m=$e?:'Profile photo updated.';
}elseif(isset($_POST['book_request'])){
  $bookId=filter_var($_POST['book_id']??null,FILTER_VALIDATE_INT);
  $message=trim((string)($_POST['booking_message']??''));
  $messageLength=function_exists('mb_strlen')?mb_strlen($message,'UTF-8'):strlen($message);
  if(!csrf_token_valid($_POST['csrf_token']??null))$m='Your session expired. Refresh the page and try again.';
  elseif(!$bookId||$messageLength>500)$m='Choose a valid book and keep your message to 500 characters or fewer.';
  else{
    $pdo->beginTransaction();
    try{
      $studentLock=$pdo->prepare("SELECT id FROM students WHERE id=? FOR UPDATE");$studentLock->execute([$sid]);
      $bookQuery=$pdo->prepare("SELECT id,title,available FROM books WHERE id=? FOR UPDATE");$bookQuery->execute([$bookId]);$book=$bookQuery->fetch();
      $existing=$pdo->prepare("SELECT COUNT(*) FROM book_requests WHERE student_id=? AND book_id=? AND status IN ('pending','approved')");
      $existing->execute([$sid,$bookId]);
      $borrowed=$pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE student_id=? AND book_id=? AND returned_at IS NULL");
      $borrowed->execute([$sid,$bookId]);
      $held=$pdo->prepare("SELECT COUNT(*) FROM book_requests WHERE book_id=? AND status='approved'");$held->execute([$bookId]);
      if(!$book){$pdo->rollBack();$m='That book is no longer in the catalog.';}
      elseif((int)$existing->fetchColumn()>0){$pdo->rollBack();$m='You already have an active request for this book.';}
      elseif((int)$borrowed->fetchColumn()>0){$pdo->rollBack();$m='You already have this book borrowed.';}
      elseif((int)$book['available']-(int)$held->fetchColumn()<1){$pdo->rollBack();$m='No unreserved copy is available for booking right now.';}
      else{
        $pdo->prepare("INSERT INTO book_requests(student_id,book_id,message,status,requested_at) VALUES(?,?,?,'pending',NOW())")
          ->execute([$sid,$bookId,$message]);
        $pdo->commit();
        $m='Your booking request was sent to the library admin.';
      }
    }catch(Throwable $e){
      if($pdo->inTransaction())$pdo->rollBack();
      throw $e;
    }
  }
}elseif(isset($_POST['cancel_booking'])){
  $requestId=filter_var($_POST['cancel_booking'],FILTER_VALIDATE_INT);
  if(!csrf_token_valid($_POST['csrf_token']??null))$m='Your session expired. Refresh the page and try again.';
  elseif(!$requestId)$m='Invalid booking request.';
  else{
    $cancel=$pdo->prepare("UPDATE book_requests SET status='cancelled' WHERE id=? AND student_id=? AND status IN ('pending','approved')");
    $cancel->execute([$requestId,$sid]);
    $m=$cancel->rowCount()?'Booking request cancelled.':'That request can no longer be cancelled.';
  }
}
if(isset($_GET['ph']))$m='Profile photo updated.';
$catalogQuery=$pdo->prepare("SELECT b.id,b.title,b.author,b.category,
  GREATEST(0,b.available-(SELECT COUNT(*) FROM book_requests r WHERE r.book_id=b.id AND r.status='approved')) AS bookable
  FROM books b
  WHERE NOT EXISTS(SELECT 1 FROM book_requests r WHERE r.book_id=b.id AND r.student_id=? AND r.status IN ('pending','approved'))
    AND NOT EXISTS(SELECT 1 FROM borrowings r WHERE r.book_id=b.id AND r.student_id=? AND r.returned_at IS NULL)
  ORDER BY b.title");
$catalogQuery->execute([$sid,$sid]);$catalogBooks=$catalogQuery->fetchAll();
$bookingRows=$pdo->prepare("SELECT r.id,r.message,r.admin_reply,r.status,r.requested_at,r.reviewed_at,r.replied_at,b.title,b.author
  FROM book_requests r JOIN books b ON b.id=r.book_id
  WHERE r.student_id=? ORDER BY r.requested_at DESC LIMIT 50");
$bookingRows->execute([$sid]);$bookingRows=$bookingRows->fetchAll();
$st=$pdo->prepare("SELECT s.*,u.email FROM students s LEFT JOIN users u ON u.student_id=s.id AND u.role='student' WHERE s.id=? LIMIT 1");$st->execute([$sid]);$st=$st->fetch();
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

<?php if($m)echo '<div class="note'.(in_array($m,['Password changed.','Profile photo updated.','Profile photo removed.','Your booking request was sent to the library admin.','Booking request cancelled.'],true)?'':' bad').'">'.ic('info').'<span>'.h($m).'</span></div>';?>

<nav class="student-bottom-nav" aria-label="Student dashboard sections">
  <a href="#dashboard" class="active"><?=ic('user')?><span> Dashboard</span></a>
  <a href="#pass"><?=ic('file')?><span>Library Pass</span></a>
  <a href="#booking"><?=ic('book')?><span>Booking</span></a>
  <a href="#borrowed"><?=ic('book')?><span> Borrowed</span></a>
  <a href="#history"><?=ic('return')?><span>History</span></a>
  <a href="#attendance"><?=ic('clock')?><span>Attendance</span></a>
</nav>

<!-- Profile -->  
<section class="student-section" id="dashboard">
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
</section>

<section class="student-section" id="pass"><?php library_pass($st,setting($pdo,'library_logo','assets/rhsti-school-logo.jpg'),setting($pdo,'library_name','eSmartlib'),setting($pdo,'school_name','RHSTI Library')); ?></section>

<!-- Online booking -->
<section class="student-section card" id="booking">
  <div class="ph"><span class="pico"><?=ic('book')?></span><div><h3>Online book booking</h3><p>Choose an available book and send a message to the library admin.</p></div></div>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
    <div class="fgrid">
      <label class="fl"><span>Book</span>
        <select name="book_id" required>
          <option value="">Select a book</option>
          <?php foreach($catalogBooks as $book):if((int)$book['bookable']>0):?>
            <option value="<?=(int)$book['id']?>"><?=h($book['title'])?><?=!empty($book['author'])?' — '.h($book['author']):''?> (<?=(int)$book['bookable']?> available)</option>
          <?php endif;endforeach;?>
        </select>
      </label>
      <label class="fl"><span>Message to admin (optional)</span><input name="booking_message" maxlength="500" placeholder="Add a note about your booking"></label>
    </div>
    <div class="fact"><button name="book_request" value="1" <?=!array_filter($catalogBooks,fn($book)=>(int)$book['bookable']>0)?'disabled':''?>><?=ic('book')?>Send booking request</button></div>
    <?php if(!array_filter($catalogBooks,fn($book)=>(int)$book['bookable']>0)):?><p class="help">No copies are currently available to book.</p><?php endif;?>
  </form>

  <div class="ph" style="margin-top:24px"><div><h3>My booking requests</h3><p>Admin replies and booking status appear here.</p></div><span class="badge"><?=count($bookingRows)?> request<?=count($bookingRows)===1?'':'s'?></span></div>
  <div class="tw"><table class="rt">
    <tr><th>Book</th><th>Your message</th><th>Admin reply</th><th>Status</th><th>Requested</th><th>Action</th></tr>
    <?php foreach($bookingRows as $request):
      $statusClass=['approved'=>'ok','fulfilled'=>'ok','pending'=>'warn','declined'=>'late','cancelled'=>'late'][$request['status']]??'';
    ?>
      <tr>
        <td data-label="Book"><?=h($request['title'])?><small class="muted" style="display:block"><?=h($request['author']??'')?></small></td>
        <td data-label="Your message"><?=h($request['message']?:'—')?></td>
        <td data-label="Admin reply"><?=h($request['admin_reply']?:'Waiting for admin reply')?></td>
        <td data-label="Status"><span class="pill <?=$statusClass?>"><?=h(ucfirst($request['status']))?></span></td>
        <td data-label="Requested"><?=h(date('M j, Y · h:i A',strtotime($request['requested_at'])))?></td>
        <td data-label="Action">
          <?php if(in_array($request['status'],['pending','approved'],true)):?>
            <form method="post" onsubmit="return confirm('Cancel this booking request?')">
              <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
              <button class="ghost" name="cancel_booking" value="<?=(int)$request['id']?>">Cancel</button>
            </form>
          <?php else:?>—<?php endif;?>
        </td>
      </tr>
    <?php endforeach;?>
    <?php if(!$bookingRows):?><tr><td colspan="6" class="muted e" style="text-align:center;padding:28px">You have no booking requests yet.</td></tr><?php endif;?>
  </table></div>
</section>

<!-- Currently borrowed -->
<section class="student-section card" id="borrowed">
  <div class="ph"><span class="pico"><?=ic('book')?></span><div><h3>Currently borrowed</h3><p>Return on or before the due date.</p></div><span class="badge"><?=count($cur)?> book<?=count($cur)==1?'':'s'?></span></div>
  <div class="tw"><table class="rt">
    <tr><th>Book</th><th>Author</th><th>Borrowed</th><th>Due</th><th>Status</th></tr>
    <?php foreach($cur as $l){$d=(int)((strtotime($l['due_date'])-strtotime($today))/86400);
      $cls=$d<0?'late':($d==0?'warn':'ok');
      $txt=$d<0?'Overdue by '.-$d.' day(s)':($d==0?'Due today':$d.' day(s) left');
      echo '<tr><td data-label="Book">'.h($l['title']).'</td><td class="muted" data-label="Author">'.h($l['author']).'</td><td class="muted" data-label="Borrowed">'.h(substr($l['borrowed_at'],0,10)).'</td><td data-label="Due">'.h($l['due_date']).'</td><td data-label="Status"><span class="pill '.$cls.'">'.h($txt).'</span></td></tr>';}
    if(!$cur)echo '<tr><td colspan="5" class="muted e" style="text-align:center;padding:28px">No borrowed books.</td></tr>';?>
  </table></div>
</section>

<!-- History + attendance -->
<div class="two">
  <section class="student-section card" id="history">
    <div class="ph"><span class="pico"><?=ic('return')?></span><div><h3>Borrowing history</h3></div></div>
    <div class="tw"><table class="rt">
      <tr><th>Book</th><th>Borrowed</th><th>Returned</th></tr>
      <?php foreach($hist as $l)echo '<tr><td data-label="Book">'.h($l['title']).'</td><td class="muted" data-label="Borrowed">'.h(substr($l['borrowed_at'],0,10)).'</td><td data-label="Returned">'.h(substr($l['returned_at'],0,10)).'</td></tr>';
      if(!$hist)echo '<tr><td colspan="3" class="muted e" style="text-align:center;padding:28px">No returned books yet.</td></tr>';?>
    </table></div>
  </section>
  <section class="student-section card" id="attendance">
    <div class="ph"><span class="pico"><?=ic('clock')?></span><div><h3>Attendance (last 30)</h3></div></div>
    <div class="tw"><table class="rt">
      <tr><th>Date</th><th>Time-In</th><th>Time-Out</th></tr>
      <?php foreach($att as $a)echo '<tr><td data-label="Date">'.h(substr($a['time_in'],0,10)).'</td><td data-label="Time-In">'.h(date('h:i A',strtotime($a['time_in']))).'</td><td data-label="Time-Out">'.($a['time_out']?h(date('h:i A',strtotime($a['time_out']))):'-').'</td></tr>';
      if(!$att)echo '<tr><td colspan="3" class="muted e" style="text-align:center;padding:28px">No visits recorded yet.</td></tr>';?>
    </table></div>
  </section>
</div>

</div>
<?php foot('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>renderQR();
(function(){
  var menus=document.querySelectorAll(".student-bottom-nav,.student-app aside nav");
  if(!menus.length)return;
  var links=Array.from(document.querySelectorAll(".student-bottom-nav a[href^=\\"#\\"],.student-app aside nav a[href^=\\"#\\"]"));
  var sections=Array.from(new Set(links.map(function(link){return document.getElementById(link.hash.slice(1));}).filter(Boolean)));
  if(!sections.length)return;
  function showSection(id,scroll){
    var selected=sections.some(function(section){return section.id===id;})?id:"dashboard";
    sections.forEach(function(section){section.hidden=section.id!==selected;});
    links.forEach(function(link){
      var active=link.hash==="#"+selected;
      link.classList.toggle("active",active);
      link.classList.toggle("on",active);
      if(active)link.setAttribute("aria-current","page");
      else link.removeAttribute("aria-current");
    });
    if(scroll){
      var target=document.getElementById(selected);
      if(target)target.scrollIntoView({behavior:"smooth",block:"start"});
    }
  }
  menus.forEach(function(menu){
    menu.addEventListener("click",function(event){
      var link=event.target.closest("a[href^=\\"#\\"]");
      if(!link)return;
      event.preventDefault();
      history.replaceState(null,"",link.hash);
      showSection(link.hash.slice(1),true);
    });
  });
  window.addEventListener("hashchange",function(){showSection(location.hash.slice(1),true);});
  showSection(location.hash.slice(1),false);
})();
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
