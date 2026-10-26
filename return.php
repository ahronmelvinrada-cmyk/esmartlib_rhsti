<?php require 'config.php';require 'ui2026.php';auth();
$rows=$pdo->query("SELECT s.name,b.title,r.borrowed_at,r.due_date,r.returned_at FROM borrowings r JOIN students s ON s.id=r.student_id JOIN books b ON b.id=r.book_id WHERE r.returned_at IS NOT NULL ORDER BY r.returned_at DESC LIMIT 15")->fetchAll();
head('Return Books (QR Scan)');ui_css();?>
<div class="pg">

<div class="card scan">
  <div class="ph"><span class="pico"><?=ic('return')?></span><div><h3>Return a book</h3><p>Scan the <b>book QR</b> to record the return and update availability.</p></div></div>
  <div class="scanwrap"><?=ic('scan')?><input id="scan" class="big" placeholder="Scan book QR here..." autocomplete="off" autofocus></div>
  <div class="fact"><button id="cam" type="button" class="ghost"><?=ic('camera')?>Use Camera</button></div>
  <div id="reader"></div>
</div>

<div id="log"></div>

<div class="card">
  <div class="ph"><span class="pico"><?=ic('clock')?></span><div><h3>Recent returns</h3><p>Latest 15 returned books.</p></div><span class="badge"><?=count($rows)?> record<?=count($rows)==1?'':'s'?></span></div>
  <div class="tw">
  <table>
    <tr><th>Student</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th></tr>
    <?php foreach($rows as $r):
      $late=strtotime(substr($r['returned_at'],0,10))>strtotime($r['due_date']);?>
      <tr>
        <td><?=h($r['name'])?></td>
        <td><?=h($r['title'])?></td>
        <td class="muted"><?=h(date('M j, Y',strtotime($r['borrowed_at'])))?></td>
        <td class="muted"><?=h(date('M j, Y',strtotime($r['due_date'])))?></td>
        <td><?=h(date('M j, Y · h:i A',strtotime($r['returned_at'])))?></td>
        <td><span class="pill <?=$late?'late':'ok'?>"><?=$late?'Late':'On time'?></span></td>
      </tr>
    <?php endforeach;?>
    <?php if(!$rows):?><tr><td colspan="6" class="muted" style="text-align:center;padding:28px">No returns recorded yet.</td></tr><?php endif;?>
  </table>
  </div>
</div>

</div>
<?php foot('<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script><script>
async function handle(v){const r=await api("return",{code:v});flash(r.ok&&!r.late,r.msg);}
bindScanner(scan,handle);cam.onclick=()=>startCamera("reader",handle);</script>');