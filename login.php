<?php require 'config.php';
if(!$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()){ // first run: seed staff accounts
  $s=$pdo->prepare("INSERT INTO users(username,password,role) VALUES(?,?,?)");
  $s->execute(['admin',password_hash('admin123',PASSWORD_DEFAULT),'admin']);
  $s->execute(['librarian',password_hash('lib123',PASSWORD_DEFAULT),'librarian']);
}
$err='';
if($_POST){
  $user=trim($_POST['username']);$s=$pdo->prepare("SELECT * FROM users WHERE username=?");$s->execute([$user]);$u=$s->fetch();
  if(!$u){ // student without account yet: Student ID + default password (= Student ID) creates it
    $st=$pdo->prepare("SELECT * FROM students WHERE student_no=?");$st->execute([$user]);$st=$st->fetch();
    if($st&&$_POST['password']===$st['student_no']){
      $pdo->prepare("INSERT INTO users(username,password,role,student_id) VALUES(?,?, 'student',?)")->execute([$user,password_hash($st['student_no'],PASSWORD_DEFAULT),$st['id']]);
      $s->execute([$user]);$u=$s->fetch();}
  }
  if($u&&password_verify($_POST['password'],$u['password'])){
    $_SESSION['u']=['id'=>$u['id'],'username'=>$u['username'],'role'=>$u['role'],'student_id'=>$u['student_id']];
    header('Location: '.($u['role']=='student'?'student.php':'index.php'));exit;}
  $err='Invalid username/Student ID or password.';
}?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login - eSmartlib RHSTI</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<style>
body.lg{display:block;min-height:100vh;margin:0;background:#f4f7f3;font-family:'Plus Jakarta Sans',system-ui,sans-serif}
.lg *{box-sizing:border-box}
.lg .shell{display:grid;grid-template-columns:1.05fr 1fr;min-height:100vh}

/* ---------- Left: brand panel ---------- */
.lg .hero{position:relative;overflow:hidden;color:#fff;padding:48px 56px;display:flex;flex-direction:column;justify-content:space-between;
  background:radial-gradient(90% 70% at 0% 0%,#2a9d5c 0%,transparent 60%),radial-gradient(70% 60% at 100% 100%,#14653a 0%,transparent 65%),linear-gradient(160deg,#0f3d26,#092a19)}
.lg .hero::before{content:"";position:absolute;inset:0;opacity:.07;pointer-events:none;
  background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:44px 44px;
  -webkit-mask-image:radial-gradient(80% 80% at 30% 30%,#000,transparent);mask-image:radial-gradient(80% 80% at 30% 30%,#000,transparent)}
.lg .hero>*{position:relative}
.lg .brand2{display:flex;align-items:center;gap:12px;font-weight:800;font-size:19px;letter-spacing:-.02em}
.lg .brand2 i{width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,#fff,#d4f0de);color:#0f3d26;display:grid;place-items:center;font-style:normal;font-size:27px;font-weight:800;box-shadow:0 12px 26px -10px rgba(0,0,0,.6)}
.lg .brand2 small{display:block;font-weight:500;font-size:12.5px;color:#a9d1b7;letter-spacing:0}
.lg .hero h2{margin:0 0 14px;font-size:clamp(30px,3.6vw,46px);line-height:1.08;font-weight:800;letter-spacing:-.035em;max-width:520px}
.lg .hero h2 span{color:#7ee2a8}
.lg .hero p.lead{margin:0 0 30px;color:#bcd8c6;font-size:16px;max-width:460px;line-height:1.6}
.lg .feats{display:grid;gap:12px;max-width:460px}
.lg .feat{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:18px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(6px);font-weight:600;font-size:14.5px}
.lg .feat svg{width:38px;height:38px;padding:9px;border-radius:12px;background:rgba(126,226,168,.16);color:#7ee2a8;flex:none}
.lg .feat small{display:block;font-weight:500;color:#a9d1b7;font-size:12.5px;margin-top:1px}
.lg .foot{color:#8fbba0;font-size:12.5px}

/* ---------- Right: form ---------- */
.lg .panel{display:grid;place-items:center;padding:32px 24px}
.lg .lcard{width:100%;max-width:410px;margin:0;padding:0;background:transparent;border:0;display:block;animation:rise .5s ease both}
@keyframes rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
.lg .mbrand{display:none}
.lg .lcard h1{margin:0 0 6px;font-size:32px;font-weight:800;letter-spacing:-.035em;color:#16231b}
.lg .sub{margin:0 0 26px;color:#667467;font-size:15px}
.lg .err{display:flex;align-items:center;gap:10px;margin:0 0 18px;padding:12px 14px;border-radius:14px;background:#fde8e6;border:1px solid #f4c7c2;color:#b42318;font-weight:600;font-size:14px;animation:shake .4s ease}
.lg .err svg{width:20px;height:20px;flex:none}
@keyframes shake{20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}
.lg label.f{display:block;margin:0 0 16px}
.lg label.f>span{display:block;margin:0 0 7px;font-size:13px;font-weight:700;color:#16231b}
.lg .inp{position:relative}
.lg .inp>svg.l{position:absolute;left:16px;top:50%;transform:translateY(-50%);width:20px;height:20px;color:#8a998f;pointer-events:none;transition:color .2s}
.lg .inp input{width:100%;height:54px;margin:0;padding:0 16px 0 48px;font:inherit;font-size:15px;font-weight:500;color:#16231b;background:#fff;border:1.5px solid #dde6dd;border-radius:16px;transition:border-color .2s,box-shadow .2s}
.lg .inp input::placeholder{color:#9aa9a0;font-weight:400}
.lg .inp input:hover{border-color:#c4d4c8}
.lg .inp input:focus{outline:0;border-color:#1f7a46;box-shadow:0 0 0 5px rgba(31,122,70,.14)}
.lg .inp:focus-within>svg.l{color:#1f7a46}
.lg .inp.pw input{padding-right:52px}
.lg .eye{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:40px;height:40px;margin:0;padding:0;border:0;border-radius:12px;background:transparent;color:#667467;display:grid;place-items:center;cursor:pointer;box-shadow:none}
.lg .eye:hover{background:#eef3ef;color:#16231b}
.lg .eye svg{width:20px;height:20px}
.lg .go{width:100%;height:56px;margin:8px 0 0;border:0;border-radius:16px;font:inherit;font-size:16px;font-weight:800;color:#fff;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(135deg,#2a9d5c,#1f7a46);box-shadow:0 16px 30px -14px #1f7a46;transition:transform .15s,box-shadow .2s,filter .2s}
.lg .go:hover{transform:translateY(-2px);filter:brightness(1.06);box-shadow:0 20px 34px -14px #1f7a46}
.lg .go:active{transform:none}
.lg .go svg{width:20px;height:20px;transition:transform .2s}
.lg .go:hover svg{transform:translateX(4px)}
.lg .go.busy{pointer-events:none;opacity:.85}
.lg .help{margin-top:22px;display:grid;gap:10px}
.lg .hl{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border-radius:14px;background:#eef6f0;color:#2b4a38;font-size:13px;line-height:1.5}
.lg .hl b{color:#0f3d26}
.lg .hl svg{width:20px;height:20px;flex:none;color:#1f7a46;margin-top:1px}

/* ---------- Responsive ---------- */
@media(max-width:900px){
  .lg .shell{grid-template-columns:1fr}
  .lg .hero{display:none}
  .lg .panel{min-height:100vh;align-items:start;padding:36px 20px;background:linear-gradient(180deg,#e6f1e8 0,#f4f7f3 260px)}
  .lg .mbrand{display:flex;align-items:center;gap:12px;margin:0 0 30px;font-weight:800;font-size:19px;letter-spacing:-.02em;color:#0f3d26}
  .lg .mbrand i{width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,#2a9d5c,#1f7a46);color:#fff;display:grid;place-items:center;font-style:normal;font-size:27px;font-weight:800;box-shadow:0 12px 24px -10px #1f7a46}
  .lg .mbrand small{display:block;font-weight:500;font-size:12.5px;color:#667467;letter-spacing:0}
  .lg .lcard h1{font-size:28px}
}
@media(prefers-reduced-motion:reduce){.lg *{animation:none!important;transition:none!important}}
</style></head>
<body class="lg">
<div class="shell">

  <!-- Brand panel -->
  <section class="hero" aria-hidden="true">
    <div class="brand2"><i>e</i><div>eSmartlib<small>RHSTI Library</small></div></div>
    <div>
      <h2>Your school library, <span>one scan away.</span></h2>
      <p class="lead">Borrow, return and time-in with a QR code. Everything the library needs, in one clean dashboard.</p>
      <div class="feats">
        <div class="feat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M4 12h16"/></svg><div>QR borrow &amp; return<small>Scan the student, then the book. Done.</small></div></div>
        <div class="feat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><div>Library attendance<small>Automatic time-in and time-out.</small></div></div>
        <div class="feat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg><div>Live dashboard<small>Overdue alerts and weekly activity.</small></div></div>
      </div>
    </div>
    <div class="foot">&copy; <?=date('Y')?> RHSTI Library</div>
  </section>

  <!-- Form -->
  <section class="panel">
    <form method="post" class="lcard" autocomplete="on">
      <div class="mbrand"><i>e</i><div>eSmartlib<small>RHSTI Library</small></div></div>
      <h1>Welcome back</h1>
      <p class="sub">Sign in to your library account.</p>

      <?php if($err)echo '<div class="err" role="alert"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg><span>'.h($err).'</span></div>';?>

      <label class="f"><span>Username / Student ID</span>
        <div class="inp"><svg class="l" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        <input name="username" placeholder="e.g. admin or 230630" value="<?=h($_POST['username']??'')?>" autocomplete="username" required <?=empty($_POST['username'])?'autofocus':''?>></div></label>

      <label class="f"><span>Password</span>
        <div class="inp pw"><svg class="l" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        <input id="pw" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required <?=!empty($_POST['username'])?'autofocus':''?>>
        <button type="button" class="eye" id="eye" aria-label="Show password"><svg id="eyei" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></label>

      <button class="go" id="go">Sign in <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>

      <div class="help">
        <div class="hl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/></svg><div><b>Admin / Librarian:</b> use your username.</div></div>
        <div class="hl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Z"/><path d="M19 19v2H6"/></svg><div><b>Students:</b> use your Student ID. First time? Your password is also your Student ID.</div></div>
      </div>
    </form>
  </section>
</div>
<script>
(function(){
  var pw=document.getElementById("pw"),eye=document.getElementById("eye"),open='<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
      shut='<path d="M3 3l18 18M10.6 6.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4M6.5 6.9C3.8 8.7 2 12 2 12s3.5 7 10 7c1.6 0 3-.4 4.3-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>';
  eye.onclick=function(){var s=pw.type==="password";pw.type=s?"text":"password";document.getElementById("eyei").innerHTML=s?shut:open;eye.setAttribute("aria-label",s?"Hide password":"Show password");pw.focus();};
  document.querySelector("form.lcard").addEventListener("submit",function(){var g=document.getElementById("go");g.classList.add("busy");g.firstChild.nodeValue="Signing in… ";});
})();
</script>
</body></html>