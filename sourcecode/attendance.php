<?php require 'config.php';require 'Ui2026.PHP';auth();
photo_col($pdo);
$todayStart=date('Y-m-d 00:00:00');
$tomorrowStart=date('Y-m-d 00:00:00',strtotime('+1 day'));
$todayRows=$pdo->prepare("SELECT s.name,s.student_no,s.photo,s.grade,s.section,a.time_in,a.time_out FROM attendance a JOIN students s ON s.id=a.student_id WHERE a.time_in>=? AND a.time_in<? ORDER BY a.id DESC");
$todayRows->execute([$todayStart,$tomorrowStart]);
$rows=$todayRows->fetchAll();
head('Time-In / Time-Out');ui_css();?>
<style>
.attendance-student{display:flex;align-items:center;gap:12px;min-width:190px}
.attendance-student .pav{width:42px;height:42px;font-size:16px}
.attendance-popup[hidden]{display:none}
.attendance-popup{position:fixed;inset:0;z-index:1000;display:grid;place-items:center;padding:20px;background:rgba(8,24,15,.72);backdrop-filter:blur(5px)}
.attendance-popup-card{width:min(100%,440px);padding:30px 24px;text-align:center;background:var(--card);border:1px solid var(--line);border-radius:24px;box-shadow:0 24px 80px rgba(0,0,0,.3);animation:attendance-pop .25s ease-out}
.attendance-popup-card .pav{width:112px;height:112px;margin:0 auto 16px;font-size:42px}
.attendance-popup-card h2{margin:0 0 6px;font-size:25px;color:var(--g)}
.attendance-popup-card p{margin:0 0 20px;color:var(--mute)}
.attendance-popup-card .attendance-popup-name{display:block;margin:0 0 8px;font-size:21px;font-weight:800;overflow-wrap:anywhere}
.attendance-popup-id{display:inline-block;padding:8px 14px;border-radius:999px;background:var(--leaf);color:var(--gd);font-weight:700}
.attendance-popup-close{width:100%;margin-top:22px!important}
.attendance-sound-status{min-height:1em;margin:8px 0 0;text-align:center;color:var(--mute);font-size:12px}
@keyframes attendance-pop{from{opacity:0;transform:translateY(12px) scale(.97)}to{opacity:1;transform:none}}
</style>
<div class="pg">

<div class="card scan">
  <div class="ph"><span class="pico"><?=ic('scan')?></span><div><h3>Library attendance</h3><p>Student scans their QR. First scan = <b>Time-In</b>, next scan = <b>Time-Out</b>.</p></div></div>
  <div class="scanwrap"><?=ic('scan')?><input id="scan" class="big" placeholder="Scan student QR here..." autocomplete="off" autofocus></div>
  <div class="fact"><button id="cam" type="button" class="ghost"><?=ic('camera')?><span class="camtxt">Use Camera</span></button><select id="cam-select" class="cam-select" aria-label="Choose camera" hidden></select></div>
  <p id="attendance-sound-status" class="attendance-sound-status" role="status" aria-live="polite"></p>
  <div id="reader" hidden></div>
  <p id="cam-status" class="cam-status" role="status" aria-live="polite"></p>
</div>

<div id="log"></div>
<div id="attendance-popup" class="attendance-popup" role="dialog" aria-modal="true" aria-labelledby="attendance-popup-title" hidden>
  <div class="attendance-popup-card">
    <div id="attendance-popup-photo"></div>
    <h2 id="attendance-popup-title"></h2>
    <p id="attendance-popup-message"></p>
    <strong id="attendance-popup-name" class="attendance-popup-name"></strong>
    <span id="attendance-popup-id" class="attendance-popup-id"></span>
    <button id="attendance-popup-close" type="button" class="attendance-popup-close">Continue</button>
  </div>
</div>

<div class="card">
  <div class="ph"><span class="pico"><?=ic('clock')?></span><div><h3>Today's attendance</h3><p><?=date('l, F j, Y')?></p></div><span class="badge"><?=count($rows)?> visit<?=count($rows)==1?'':'s'?></span><a class="btn ghost" href="history.php?type=attendance"><?=ic('clock')?>Attendance History</a></div>
  <div class="tw">
  <table id="t">
    <tr><th>Student</th><th>Section</th><th>Time-In</th><th>Time-Out</th><th>Status</th></tr>
    <?php foreach($rows as $r):?>
      <tr>
        <td><div class="attendance-student"><?=avatar($r)?><span><?=h($r['name'])?><small class="muted" style="display:block">Student ID: <?=h($r['student_no'])?></small></span></div></td>
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
let attendancePopupOpen=false;
const attendanceSound=new Audio("assets/applepay.mp3");
attendanceSound.preload="auto";
let attendanceSoundUnlocked=false;
const attendanceSoundStatus=document.getElementById("attendance-sound-status");
async function prepareAttendanceAudio(){
  if(attendanceSoundUnlocked)return;
  try{
    attendanceSound.muted=true;
    await attendanceSound.play();
    attendanceSound.pause();
    attendanceSound.currentTime=0;
    attendanceSound.muted=false;
    attendanceSoundUnlocked=true;
    attendanceSoundStatus.textContent="";
  }catch(error){
    attendanceSound.muted=false;
    attendanceSoundStatus.textContent=error.message||"Could not enable attendance sounds.";
    console.error("Attendance audio could not be enabled:",error);
  }
}
async function playAttendanceSound(){
  try{
    attendanceSound.currentTime=0;
    await attendanceSound.play();
  }catch(error){
    attendanceSoundStatus.textContent="Could not play the attendance sound. Check that assets/applepay.mp3 is available and sound is enabled.";
    console.error("Attendance sound playback failed:",error);
  }
}
function closeAttendancePopup(){location.reload();}
async function handle(v){
  if(attendancePopupOpen)return;
  prepareAttendanceAudio();
  const r=await api("attendance",{code:v});
  if(!r.ok){flash(false,r.msg);return;}
  playAttendanceSound();
  attendancePopupOpen=true;
  const initials=(r.name||"?").trim().charAt(0).toUpperCase();
  const isTimeIn=r.type==="TIME-IN";
  const photo=document.getElementById("attendance-popup-photo");
  photo.innerHTML=r.photo
    ?"<img class=\\"pav\\" src=\\""+esc(r.photo)+"\\" alt=\\""+esc(r.name)+"\\">"
    :"<span class=\\"pav\\" aria-hidden=\\"true\\">"+esc(initials)+"</span>";
  document.getElementById("attendance-popup-title").textContent=isTimeIn?"Welcome to the Library!":"Thank you! Come again.";
  document.getElementById("attendance-popup-message").textContent=isTimeIn?"Have a great time studying.":"See you again soon.";
  document.getElementById("attendance-popup-name").textContent=r.name;
  document.getElementById("attendance-popup-id").textContent="School ID: "+r.student_no;
  const popup=document.getElementById("attendance-popup");
  popup.hidden=false;
  document.getElementById("attendance-popup-close").focus();
  setTimeout(closeAttendancePopup,5000);
}
document.getElementById("attendance-popup-close").onclick=closeAttendancePopup;
bindScanner(scan,handle);cam.onclick=()=>{prepareAttendanceAudio();startCamera("reader",handle);};</script>');
