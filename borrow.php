<?php require 'config.php';require 'ui2026.php';auth();head('Borrow Books (QR Scan)');ui_css();?>
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
</style>
<div class="pg">

<div class="card scan">
  <div class="ph"><span class="pico"><?=ic('book')?></span><div><h3>Borrow books</h3><p>Use a USB scanner (cursor stays in the box) or the camera.</p></div></div>
  <div class="steps">
    <span class="step on" id="s1"><i>1</i>Scan student QR</span>
    <span class="step" id="s2"><i>2</i>Scan each book QR</span>
  </div>
  <div class="scanwrap"><?=ic('scan')?><input id="scan" class="big" placeholder="Scan QR here..." autocomplete="off" autofocus></div>
  <div class="fact">
    <button id="cam" type="button" class="ghost"><?=ic('camera')?>Use Camera</button>
    <button id="reset" type="button" class="ghost"><?=ic('user')?>New Student</button>
  </div>
  <div id="reader"></div>
</div>

<div class="card" id="who"><div class="whocard"><div class="av off"><?=ic('user')?></div><div><div class="nm">No student scanned yet.</div><div class="sub">Scan a student QR to begin.</div></div></div></div>

<div id="log"></div>

</div>
<?php foot('<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script><script>
let stu=null;
const EMPTY=\'<div class="whocard"><div class="av off">?</div><div><div class="nm">No student scanned yet.</div><div class="sub">Scan a student QR to begin.</div></div></div>\';
function steps(on){s1.className="step"+(on?" done":" on");s2.className="step"+(on?" on":"");}
async function handle(v){
 if(v.startsWith("STU:")||!stu&&!v.startsWith("BK:")){const r=await api("student",{code:v});flash(r.ok,r.msg);
  if(r.ok){stu=r.student.student_no;
   who.innerHTML=\'<div class="whocard"><div class="av">\'+esc((r.student.name||"?").trim().charAt(0).toUpperCase())+\'</div><div><div class="nm">\'+esc(r.student.name)+\'</div><div class="sub">\'+esc(r.student.student_no)+\'</div></div><div class="loans"><b>\'+esc(String(r.active))+\'</b><small>Active loans</small></div></div>\';
   steps(true);}return;}
 if(!stu){flash(false,"Scan the student QR first.");return;}
 const r=await api("borrow",{student:stu,code:v});flash(r.ok,r.msg);}
bindScanner(scan,handle);cam.onclick=()=>startCamera("reader",handle);
reset.onclick=()=>{stu=null;who.innerHTML=EMPTY;steps(false);scan.focus();};
</script>');