<?php require 'config.php';require 'Ui2026.PHP';auth();
$rows=$pdo->query("SELECT s.student_no,s.name,b.title,r.borrowed_at,r.due_date,r.returned_at FROM borrowings r JOIN students s ON s.id=r.student_id JOIN books b ON b.id=r.book_id WHERE r.returned_at IS NOT NULL ORDER BY r.returned_at DESC,r.id DESC")->fetchAll();
head('Return Books (QR Scan)');ui_css();?>
<style>
.pg .history-heading{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.pg .history-heading .ph{margin:0}
.pg .history-heading .badge{margin-left:auto}
.pg .print-heading{display:none}
@media print{
  .pg>.noprint{display:none!important}
  .pg .print-history{display:block!important;overflow:visible!important;border:0!important;padding:0!important;break-inside:auto!important}
  .pg .print-history .ph{display:none!important}
  .pg .print-history .tw{overflow:visible!important;margin:0;padding:0}
  .pg .print-history table{width:100%;min-width:0;font-size:10px;border-collapse:collapse;border-spacing:0}
  .pg .print-history th,.pg .print-history td{padding:6px;border:1px solid #999}
  .pg .print-history th{background:#eee!important;color:#111!important}
  .pg .print-history .pill{background:transparent!important;color:#111!important;padding:0}
  .pg .print-history tr{break-inside:avoid}
  .pg .print-heading{display:block!important;margin:0 0 14px}
  .pg .print-heading h2{margin:0;font-size:20px}
}
</style>
<div class="pg">

<div class="card scan noprint">
  <div class="ph"><span class="pico"><?=ic('return')?></span><div><h3>Return a book</h3><p>Scan the <b>book QR</b> to record the return and update availability.</p></div></div>
  <div class="scanwrap"><?=ic('scan')?><input id="scan" class="big" placeholder="Scan book QR here..." autocomplete="off" autofocus></div>
  <div class="fact"><button id="cam" type="button" class="ghost"><?=ic('camera')?><span class="camtxt">Use Camera</span></button><select id="cam-select" class="cam-select" aria-label="Choose camera" hidden></select></div>
  <div id="reader" hidden></div>
  <p id="cam-status" class="cam-status" role="status" aria-live="polite"></p>
</div>

<div id="log" class="noprint"></div>

<div class="card print-history">
  <div class="history-heading">
    <div class="ph"><span class="pico"><?=ic('clock')?></span><div><h3>Return History</h3><p>All returned books.</p></div></div>
    <span class="badge"><?=count($rows)?> record<?=count($rows)==1?'':'s'?></span>
    <a class="btn ghost noprint" href="history.php?type=return"><?=ic('clock')?>All Return History</a>
    <button class="ghost noprint" type="button" onclick="window.print()"><?=ic('print')?>Print History</button>
  </div>
  <div class="print-heading">
    <h2>Return History</h2>
    <div>Printed <?=h(date('M j, Y · h:i A'))?></div>
  </div>
  <div class="tw">
  <table>
    <tr><th>Student No.</th><th>Student</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th></tr>
    <?php foreach($rows as $r):
      $late=strtotime(substr($r['returned_at'],0,10))>strtotime($r['due_date']);?>
      <tr>
        <td><?=h($r['student_no'])?></td>
        <td><?=h($r['name'])?></td>
        <td><?=h($r['title'])?></td>
        <td class="muted"><?=h(date('M j, Y',strtotime($r['borrowed_at'])))?></td>
        <td class="muted"><?=h(date('M j, Y',strtotime($r['due_date'])))?></td>
        <td><?=h(date('M j, Y · h:i A',strtotime($r['returned_at'])))?></td>
        <td><span class="pill <?=$late?'late':'ok'?>"><?=$late?'Late':'On time'?></span></td>
      </tr>
    <?php endforeach;?>
    <?php if(!$rows):?><tr><td colspan="7" class="muted" style="text-align:center;padding:28px">No returns recorded yet.</td></tr><?php endif;?>
  </table>
  </div>
</div>

</div>
<?php foot('<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script><script>
async function handle(v){const r=await api("return",{code:v});flash(r.ok,r.ok?"QR SCAN COMPLETE — "+r.msg:r.msg);}
bindScanner(scan,handle);cam.onclick=()=>startCamera("reader",handle);</script>');