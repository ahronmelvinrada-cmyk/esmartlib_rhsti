<?php require 'config.php';require 'ui2026.php';auth();
$rows=$pdo->query("SELECT s.name,s.grade,s.section,a.time_in,a.time_out FROM attendance a JOIN students s ON s.id=a.student_id WHERE DATE(a.time_in)=CURDATE() ORDER BY a.id DESC")->fetchAll();
head('Time-In / Time-Out');ui_css();?>
<div class="pg">

<div class="card scan">
  <div class="ph"><span class="pico"><?=ic('scan')?></span><div><h3>Library attendance</h3><p>Student scans their QR. First scan = <b>Time-In</b>, next scan = <b>Time-Out</b>.</p></div></div>
  <div class="scanwrap"><?=ic('scan')?><input id="scan" class="big" placeholder="Scan student QR here..." autocomplete="off" autofocus></div>
  <div class="fact"><button id="cam" type="button" class="ghost"><?=ic('camera')?>Use Camera</button></div>
  <div id="reader"></div>
</div>

<div id="log"></div>

<div class="card">
  <div class="ph"><span class="pico"><?=ic('clock')?></span><div><h3>Today's attendance</h3><p><?=date('l, F j, Y')?></p></div><span class="badge"><?=count($rows)?> visit<?=count($rows)==1?'':'s'?></span></div>
  <div class="tw">
  <table id="t">
    <tr><th>Student</th><th>Section</th><th>Time-In</th><th>Time-Out</th><th>Status</th></tr>
    <?php foreach($rows as $r):?>
      <tr>
        <td><?=h($r['name'])?></td>
        <td class="muted"><?=h($r['grade'].' '.$r['section'])?></td>
        <td><?=h(date('h:i A',strtotime($r['time_in'])))?></td>
        <td><?=$r['time_out']?h(date('h:i A',strtotime($r['time_out']))):'-'?></td>
        <td><span class="pill <?=$r['time_out']?'out':'in'?>"><?=$r['time_out']?'Left':'Inside'?></span></td>
      </tr>
    <?php endforeach;?>
    <?php if(!$rows):?><tr><td colspan="5" class="muted" style="text-align:center;padding:28px">No visits recorded yet today.</td></tr><?php endif;?>
  </table>
  </div>
</div>

</div>
<?php foot('<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script><script>
async function handle(v){const r=await api("attendance",{code:v});flash(r.ok,r.msg);if(r.ok)setTimeout(()=>location.reload(),1500);}
bindScanner(scan,handle);cam.onclick=()=>startCamera("reader",handle);</script>');