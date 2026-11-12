<?php
require 'config.php';
require 'Ui2026.PHP';
auth();
ensure_teacher_schema($pdo);
$history=$pdo->query("SELECT COALESCE(s.student_no,t.teacher_no) AS borrower_no,
  COALESCE(s.name,t.name) AS borrower_name,CASE WHEN r.teacher_id IS NULL THEN 'Student' ELSE 'Teacher' END AS borrower_type,
  b.title,r.borrowed_at,r.due_date,r.returned_at
  FROM borrowings r
  LEFT JOIN students s ON s.id=r.student_id
  LEFT JOIN teachers t ON t.id=r.teacher_id
  JOIN books b ON b.id=r.book_id
  ORDER BY r.borrowed_at DESC,r.id DESC")->fetchAll();
if (($_GET['download'] ?? '') === 'history') {
  require_once 'export_helper.php';
  $exportRows=[];
  foreach($history as $r){
    $returned=!empty($r['returned_at']);
    $late=!empty($r['due_date'])&&($returned
      ? substr($r['returned_at'],0,10)>$r['due_date']
      : $r['due_date']<date('Y-m-d'));
    $status=$returned?($late?'Returned late':'Returned'):($late?'Overdue':'Borrowed');
    $exportRows[]=[
      $r['borrower_no'],$r['borrower_name'].' ('.$r['borrower_type'].')',$r['title'],
      date('M j, Y · h:i A',strtotime($r['borrowed_at'])),
      $r['due_date']?date('M j, Y',strtotime($r['due_date'])):'',
      $returned?date('M j, Y · h:i A',strtotime($r['returned_at'])):'',$status
    ];
  }
  export_excel_download('Borrow_History',
    ['Borrower ID','Borrower','Book','Borrowed','Due','Returned','Status'],$exportRows);
}
head('Borrow Books (QR Scan)');
ui_css();
?>
<style>
.pg .steps{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin:0 0 18px}
.pg .step{display:inline-flex;align-items:center;gap:10px;padding:8px 16px 8px 8px;border-radius:999px;background:#f1f5f3;color:var(--mute);font-weight:700;font-size:13px;transition:all .3s}
.pg .step i{width:26px;height:26px;border-radius:50%;background:#dfe8e2;color:var(--mute);display:grid;place-items:center;font-style:normal;font-size:13px}
.pg .step.on{background:var(--leaf);color:var(--gd)}
.pg .step.on i{background:var(--g);color:#fff}
.pg .step.done i{background:#10b981;color:#fff}
.pg .whocard{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.pg .av{width:56px;height:56px;border-radius:18px;background:linear-gradient(135deg,#2a9d5c,var(--g));color:#fff;display:grid;place-items:center;font-size:22px;font-weight:800;flex:none}
.pg .av.off{background:#e3eae5;color:var(--mute)}
.pg .whocard .nm{font-size:18px;font-weight:800;letter-spacing:-.02em;line-height:1.2}
.pg .whocard .sub{color:var(--mute);font-size:13px;margin-top:2px}
.pg .loans{margin-left:auto;text-align:center;background:var(--leaf);border-radius:16px;padding:10px 20px}
.pg .loans b{display:block;font-size:26px;line-height:1;font-weight:800;color:var(--gd)}
.pg .loans small{color:var(--mute);font-size:12px;font-weight:600}
.pg .history-heading{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.pg .history-heading .ph{margin:0}
.pg .history-heading .badge{margin-left:auto}
.pg .print-heading{display:none}
.pg .pill{display:inline-block}
@media print{
  .pg>.noprint{display:none!important}
  .pg .print-history{display:block!important;overflow:visible!important;border:0!important;padding:0!important;break-inside:auto!important}
  .pg .print-history .ph{display:none!important}
  .pg .print-history .tw{overflow:visible!important;margin:0;padding:0}
  .pg .print-heading{display:block!important;margin:0 0 14px}
  .pg .print-heading h2{margin:0;font-size:20px}
  .pg .print-history table{width:100%;min-width:0;font-size:10px;border-collapse:collapse;border-spacing:0}
  .pg .print-history th,.pg .print-history td{padding:6px;border:1px solid #999}
  .pg .print-history th{background:#eee!important;color:#111!important}
  .pg .print-history .pill{background:transparent!important;color:#111!important;padding:0}
  .pg .print-history tr{break-inside:avoid}
}
</style>
<div class="pg">

<div class="card scan noprint">
  <div class="ph"><span class="pico"><?=ic('books')?></span><div><h3>Borrow books</h3><p>Use a USB scanner (cursor stays in the box) or the camera.</p></div></div>
  <div class="steps">
    <span class="step on" id="s1"><i>1</i>Scan student QR or teacher pass</span>
    <span class="step" id="s2"><i>2</i>Scan each book QR</span>
  </div>
  <div class="scanwrap"><?=ic('scan')?><input id="scan" class="big" placeholder="Scan QR here..." autocomplete="off" autofocus></div>
  <div class="fact">
    <button id="cam" type="button" class="ghost"><?=ic('camera')?><span class="camtxt">Use Camera</span></button>
    <select id="cam-select" class="cam-select" aria-label="Choose camera" hidden></select>
    <button id="reset" type="button" class="ghost"><?=ic('user')?>New Borrower</button>
  </div>
  <div id="reader" hidden></div>
  <p id="cam-status" class="cam-status" role="status" aria-live="polite"></p>
</div>

<div class="card noprint" id="who"><div class="whocard"><div class="av off"><?=ic('user')?></div><div><div class="nm">No borrower scanned yet.</div><div class="sub">Scan a student QR or teacher pass to begin.</div></div></div></div>

<div id="log" class="noprint"></div>
<div id="scan-feedback" class="scan-feedback" role="dialog" aria-modal="true" aria-labelledby="scan-feedback-title" hidden>
  <div class="scan-feedback-card">
    <div class="scan-feedback-icon" aria-hidden="true">✓</div>
    <h2 id="scan-feedback-title"></h2>
    <p id="scan-feedback-message"></p>
    <div id="scan-feedback-profile" class="scan-feedback-profile" hidden>
      <div id="scan-feedback-photo" class="scan-feedback-photo"></div>
      <div class="scan-feedback-profile-copy">
        <strong id="scan-feedback-name"></strong>
        <span id="scan-feedback-id"></span>
        <small id="scan-feedback-meta"></small>
      </div>
    </div>
    <strong id="scan-feedback-details" class="scan-feedback-details"></strong>
    <p id="scan-feedback-status" class="scan-feedback-status" role="status" aria-live="polite"></p>
    <button id="scan-feedback-close" class="scan-feedback-close" type="button">Continue scanning</button>
  </div>
</div>

<div class="card print-history">
  <div class="history-heading">
    <div class="ph"><span class="pico"><?=ic('history')?></span><div><h3>Borrow History</h3><p>All borrowed books and their return status.</p></div></div>
    <span class="badge" id="history-count"><?=count($history)?> record<?=count($history)==1?'':'s'?></span>
    <a class="btn ghost noprint" href="history.php?type=borrow"><?=ic('history')?>All History</a>
    <a class="btn ghost noprint" href="?download=history"><?=ic('download')?>Download History (Excel)</a>
  </div>
  <div class="print-heading">
    <h2>Borrow History</h2>
    <div>Printed <?=h(date('M j, Y · h:i A'))?></div>
  </div>
  <div class="tw">
    <table>
      <tr><th>Borrower ID</th><th>Borrower</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th></tr>
      <tbody id="history-rows">
      <?php foreach($history as $r):
        $returned=!empty($r['returned_at']);
        $late=!empty($r['due_date'])&&($returned
          ? substr($r['returned_at'],0,10)>$r['due_date']
          : $r['due_date']<date('Y-m-d'));
        $status=$returned?($late?'Returned late':'Returned'):($late?'Overdue':'Borrowed');
        $statusClass=$late?'late':($returned?'ok':'out');
      ?>
      <tr>
        <td><?=h($r['borrower_no'])?></td>
        <td><?=h($r['borrower_name'])?> <small class="muted">(<?=h($r['borrower_type'])?>)</small></td>
        <td><?=h($r['title'])?></td>
        <td class="muted"><?=h(date('M j, Y · h:i A',strtotime($r['borrowed_at'])))?></td>
        <td class="muted"><?= $r['due_date']?h(date('M j, Y',strtotime($r['due_date']))):'—' ?></td>
        <td><?= $returned?h(date('M j, Y · h:i A',strtotime($r['returned_at']))):'—' ?></td>
        <td><span class="pill <?=$statusClass?>"><?=h($status)?></span></td>
      </tr>
      <?php endforeach;?>
      <?php if(!$history):?><tr><td colspan="7" class="muted" style="text-align:center;padding:28px">No borrow history recorded yet.</td></tr><?php endif;?>
      </tbody>
    </table>
  </div>
</div>

</div>
<?php foot('<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script><script>
let borrower=null;
const EMPTY=\'<div class="whocard"><div class="av off">?</div><div><div class="nm">No borrower scanned yet.</div><div class="sub">Scan a student QR or teacher pass to begin.</div></div></div>\';
function steps(on){s1.className="step"+(on?" done":" on");s2.className="step"+(on?" on":"");}
async function handle(v){
 if(window.scanFeedbackOpen)return;
 prepareScanFeedbackAudio();
 if(v.startsWith("STU:")||v.startsWith("TCH:")||!borrower&&!v.startsWith("BK:")){const r=await api("student",{code:v});flash(r.ok,r.msg);
  if(r.ok){borrower=(r.borrower_type==="teacher"?"TCH:":"STU:")+r.borrower_code;
   who.innerHTML=\'<div class="whocard"><div class="av">\'+esc((r.student.name||"?").trim().charAt(0).toUpperCase())+\'</div><div><div class="nm">\'+esc(r.student.name)+\'</div><div class="sub">\'+esc(r.borrower_type==="teacher"?"Teacher pass · ":"Student ID · ")+esc(r.borrower_code)+\'</div></div><div class="loans"><b>\'+esc(String(r.active))+\'</b><small>Active loans</small></div></div>\';
   steps(true);
   showScanFeedback("Borrower identified","Ready to scan books for this borrower.",r.profile,"",()=>scan.focus());}return;}
 if(!borrower){flash(false,"Scan a student QR or teacher pass first.");return;}
 const r=await api("borrow",{student:borrower,code:v});
 flash(r.ok,r.ok?"QR SCAN COMPLETE — "+r.msg:r.msg);
 if(r.ok){
  const body=document.getElementById("history-rows");
  const empty=body.querySelector("td[colspan]");
  if(empty)empty.parentElement.remove();
  const row=document.createElement("tr");
  const values=[
   [r.student_no,""],[r.student_name+" ("+(r.borrower_type==="teacher"?"Teacher":"Student")+")",""],[r.title,""],
   [r.borrowed_display,"muted"],[r.due_display,"muted"],["—",""],
   ["Borrowed","pill out"]
  ];
  values.forEach(([value,className])=>{const cell=document.createElement("td");cell.textContent=value;cell.className=className;row.appendChild(cell);});
  body.prepend(row);
  const count=body.querySelectorAll("tr").length;
  document.getElementById("history-count").textContent=count+" record"+(count===1?"":"s");
  showScanFeedback("Book borrowed","Loan recorded successfully.",r.profile,r.title,()=>scan.focus());
 }}
bindScanner(scan,handle);cam.onclick=()=>{prepareScanFeedbackAudio();startCamera("reader",handle);};
reset.onclick=()=>{borrower=null;who.innerHTML=EMPTY;steps(false);scan.focus();};
</script>');
