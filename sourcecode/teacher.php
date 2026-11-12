<?php
require 'config.php';
require 'Ui2026.PHP';
auth(['teacher']);
ensure_teacher_schema($pdo);

$teacherQuery=$pdo->prepare("SELECT * FROM teachers WHERE id=?");
$teacherQuery->execute([(int)$_SESSION['u']['teacher_id']]);
$teacher=$teacherQuery->fetch();
$loansQuery=$pdo->prepare("SELECT b.title,b.author,r.borrowed_at,r.returned_at
  FROM borrowings r JOIN books b ON b.id=r.book_id
  WHERE r.teacher_id=? ORDER BY r.borrowed_at DESC,r.id DESC");
$loansQuery->execute([(int)$teacher['id']]);
$loans=$loansQuery->fetchAll();
$activeLoans=array_filter($loans,static fn($loan)=>empty($loan['returned_at']));
$libraryName=setting($pdo,'library_name','eSmartlib');
$schoolName=setting($pdo,'school_name','RHSTI Library');
$logoPath=setting($pdo,'library_logo','assets/rhsti-school-logo.jpg');
$logoAllowed=$logoPath==='assets/rhsti-school-logo.jpg'||(strpos($logoPath,'uploads/library/')===0&&strpos($logoPath,'..')===false);
$logoPath=$logoAllowed&&is_file(__DIR__.'/'.$logoPath)?$logoPath:'';
head('Teacher Library Pass');
ui_css();
?>
<style>
.pg .teacher-intro{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap}
.pg .teacher-intro p{margin:0;color:var(--mute);font-size:14px}
.pg .teacher-intro .badge{display:inline-flex;align-items:center;gap:7px}
.pg .teacher-intro .badge i{width:8px;height:8px;border-radius:50%;background:#10b981}
.pg .teacher-hero{position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:28px;align-items:center;overflow:hidden;padding:clamp(22px,4vw,36px);border-color:#d6e8da;background:radial-gradient(ellipse at 100% 0%,rgba(42,157,92,.13),transparent 42%),linear-gradient(135deg,#f1f9f3,#fff 70%)}
.pg .teacher-hero:after{position:absolute;right:180px;bottom:-130px;width:260px;height:260px;border:1px solid rgba(31,122,70,.08);border-radius:50%;content:"";pointer-events:none}
.pg .teacher-copy,.pg .teacher-pass{position:relative;z-index:1}
.pg .teacher-eyebrow{display:inline-flex;align-items:center;gap:7px;margin-bottom:12px;color:var(--gd);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.pg .teacher-eyebrow .ic{width:16px;height:16px}
.pg .teacher-hero h2{margin:0;font-size:clamp(26px,4vw,38px);font-weight:800;letter-spacing:-.045em;line-height:1.12}
.pg .teacher-hero .teacher-school{margin:7px 0 0;color:var(--mute);font-size:14px;font-weight:600}
.pg .teacher-id{display:inline-flex;align-items:center;gap:8px;margin-top:18px;padding:7px 12px;border:1px solid #dce9df;border-radius:999px;background:#fff;color:var(--mute);font-size:13px}
.pg .teacher-id strong{color:var(--ink);font-feature-settings:"tnum"}
.pg .teacher-help{max-width:560px;margin:14px 0 0;color:var(--mute);font-size:13px;line-height:1.6}
.pg .teacher-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:20px}
.pg .teacher-actions .btn{min-height:42px}
.pg .teacher-pass{display:grid;justify-items:center;gap:10px;width:190px;padding:17px;border:1px solid #dce9df;border-radius:20px;background:rgba(255,255,255,.92);box-shadow:0 16px 34px -24px rgba(15,61,38,.45);text-align:center}
.pg .teacher-pass-title{color:var(--mute);font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.pg .teacher-pass .qr{width:132px;height:132px;margin:0;padding:6px;border:1px solid #e8eee9;border-radius:14px}
.pg .teacher-pass .qr img,.pg .teacher-pass .qr canvas{width:118px!important;height:118px!important}
.pg .teacher-pass small{color:var(--mute);font-size:11px}
.pg .teacher-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.pg .teacher-stat{position:relative;display:flex;align-items:center;gap:14px;min-width:0;padding:18px;border:1px solid var(--line);border-radius:18px;background:var(--card);overflow:hidden}
.pg .teacher-stat:after{position:absolute;right:-30px;bottom:-50px;width:110px;height:110px;border-radius:50%;background:var(--tone-bg);opacity:.48;filter:blur(20px);content:"";pointer-events:none}
.pg .teacher-stat-icon{position:relative;z-index:1;display:grid;place-items:center;flex:none;width:44px;height:44px;border-radius:14px;background:var(--tone-bg);color:var(--tone)}
.pg .teacher-stat-icon .ic{width:22px;height:22px}
.pg .teacher-stat-copy{position:relative;z-index:1;min-width:0}
.pg .teacher-stat-copy span{display:block;color:var(--mute);font-size:12px;font-weight:600;line-height:1.4}
.pg .teacher-stat-copy strong{display:block;margin-top:3px;font-size:25px;font-weight:800;letter-spacing:-.04em;line-height:1.1}
.pg .teacher-stat-copy strong.policy{font-size:14px;letter-spacing:-.01em}
.pg .teacher-stat.green{--tone:#059669;--tone-bg:#d1fae5}
.pg .teacher-stat.blue{--tone:#2563eb;--tone-bg:#dbeafe}
.pg .teacher-stat.violet{--tone:#7c3aed;--tone-bg:#ede9fe}
.pg [data-teacher-section][hidden]{display:none!important}
.pg [data-teacher-section]{scroll-margin-top:18px}
.pg .teacher-empty{padding:25px 16px!important;text-align:center!important;color:var(--mute);font-weight:600}
.pg .teacher-id-section{display:grid;gap:14px}
.pg .teacher-id-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.pg .teacher-id-heading h3{margin:0 0 3px;font-size:17px}
.pg .teacher-id-heading p{margin:0;color:var(--mute);font-size:12px}
.pg .teacher-id-heading button{display:inline-flex;align-items:center;justify-content:center;gap:8px;margin:0}
.pg .teacher-id-sides{display:grid;grid-template-columns:repeat(2,minmax(0,324px));gap:14px}
.pg .teacher-id-card{position:relative;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden;isolation:isolate;width:100%;aspect-ratio:1.586;padding:13px 15px;border:1px solid #cde3d2;border-radius:14px;background:radial-gradient(ellipse at 8% 12%,rgba(77,177,111,.22),transparent 34%),radial-gradient(ellipse at 100% 58%,rgba(139,205,158,.24),transparent 35%),linear-gradient(155deg,#e7f4e9 0%,#f2f7f1 42%,#edf5ee 100%);box-shadow:0 14px 28px -22px #1f7a4640;color:#0f3d26}
.pg .teacher-id-card:before{position:absolute;z-index:-1;inset:0;opacity:.035;background-image:linear-gradient(#0f3d26 1px,transparent 1px),linear-gradient(90deg,#0f3d26 1px,transparent 1px);background-size:22px 22px;content:""}
.pg .teacher-id-card:after{position:absolute;z-index:-1;right:9%;bottom:-62px;width:160px;height:160px;border:1px solid rgba(31,122,70,.14);border-radius:50%;box-shadow:0 0 0 14px rgba(31,122,70,.035),0 0 0 29px rgba(31,122,70,.025);content:""}
.pg .teacher-id-head,.pg .teacher-id-brand,.pg .teacher-id-main{display:flex;align-items:center}
.pg .teacher-id-head{justify-content:space-between;gap:8px}
.pg .teacher-id-brand{gap:7px;min-width:0;font-size:10px;font-weight:800;line-height:1.1}
.pg .teacher-id-brand img{width:27px;height:27px;flex:none;object-fit:contain;padding:2px;border-radius:7px;background:#fff}
.pg .teacher-id-brand small{display:block;margin-top:3px;color:#557663;font-size:7px;font-weight:600}
.pg .teacher-id-tag{flex:none;padding:5px 7px;border:1px solid rgba(31,122,70,.24);border-radius:999px;background:#ffffff90;color:#17653a;font-size:6px;font-weight:800;letter-spacing:.1em}
.pg .teacher-id-main{flex:1;gap:10px;min-height:0;padding:8px 0}
.pg .teacher-id-avatar{display:grid;place-items:center;overflow:hidden;flex:none;width:49px;height:49px;border:3px solid #ffffffcc;border-radius:50%;background:linear-gradient(145deg,#2a9d5c,#17653a);box-shadow:0 7px 15px #052d1c30;color:#fff;font-size:20px;font-weight:800}
.pg .teacher-id-avatar img{width:100%;height:100%;object-fit:cover}
.pg .teacher-id-person{flex:1;min-width:0}
.pg .teacher-id-person small,.pg .teacher-id-number-label{display:block;color:#557663;font-size:6px;font-weight:800;letter-spacing:.12em}
.pg .teacher-id-person h4{overflow:hidden;margin:3px 0 7px;font-size:13px;font-weight:800;line-height:1.15;text-overflow:ellipsis}
.pg .teacher-id-number-label{font-size:6px}
.pg .teacher-id-number{display:block;margin-top:2px;font-size:9px;font-weight:800;letter-spacing:.05em}
.pg .teacher-id-qr{display:grid;place-items:center;flex:none;width:60px;height:60px;padding:3px;border:0;border-radius:8px;background:#fff}
.pg .teacher-id-qr img,.pg .teacher-id-qr canvas{display:block;width:54px!important;height:54px!important}
.pg .teacher-id-footer{display:flex;justify-content:space-between;gap:8px;padding-top:5px;border-top:1px solid rgba(31,122,70,.2);color:#557663;font-size:5px;font-weight:700;letter-spacing:.08em}
.pg .teacher-id-back{border-color:#b8d5c9;background:radial-gradient(ellipse at 92% 10%,rgba(83,171,135,.2),transparent 36%),linear-gradient(145deg,#f3f8f4,#e8f2ed);color:#163d32}
.pg .teacher-id-back .teacher-id-brand small{color:#688779}
.pg .teacher-id-back-body{display:grid;gap:6px;margin:5px 0;color:#557663;font-size:7px;line-height:1.5}
.pg .teacher-id-back-body strong{color:#163d32;font-size:8px}
.pg .teacher-id-signature{margin-top:auto;padding-top:5px;border-top:1px solid #6f9180;color:#688779;font-size:6px;font-weight:800;letter-spacing:.1em}
.pg .teacher-download-status{min-height:1em;margin:0;color:var(--mute);font-size:13px}
@media(max-width:760px){.pg .teacher-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.pg .teacher-stat:last-child{grid-column:1/-1}}
@media(max-width:560px){
  .pg .teacher-intro{align-items:flex-start}
  .pg .teacher-hero{grid-template-columns:1fr;justify-items:center;gap:20px;text-align:center}
  .pg .teacher-copy{display:grid;justify-items:center}
  .pg .teacher-help{max-width:420px}
  .pg .teacher-actions{justify-content:center;width:100%}
  .pg .teacher-actions .btn{flex:1}
  .pg .teacher-pass{width:min(100%,230px)}
  .pg .teacher-stats{grid-template-columns:1fr 1fr;gap:10px}
  .pg .teacher-stat{align-items:flex-start;flex-direction:column;gap:10px;padding:14px}
  .pg .teacher-stat:last-child{grid-column:1/-1;align-items:center;flex-direction:row}
  .pg .teacher-stat-copy strong{font-size:22px}
  .pg .teacher-id-sides{grid-template-columns:minmax(0,324px)}
  .pg .teacher-id-heading button{width:100%}
}
</style>
<div class="pg">
  <div class="teacher-intro">
    <p>Your library account and borrowing activity at a glance.</p>
    <span class="badge"><i aria-hidden="true"></i> Account active</span>
  </div>
  <nav class="student-bottom-nav teacher-section-nav" aria-label="Teacher dashboard sections">
    <a class="active" href="#dashboard"><?=ic('dashboard')?><span> Dashboard</span></a>
    <a href="#pass"><?=ic('id-card')?><span>Teacher Pass</span></a>
    <a href="#borrowed"><?=ic('books')?><span> Borrowed</span></a>
  </nav>
  <div id="dashboard" class="teacher-view" data-teacher-section>
  <section class="card teacher-hero">
    <div class="teacher-copy">
      <span class="teacher-eyebrow"><?=ic('books')?>Personal library account</span>
      <h2><?=h($teacher['name'])?></h2>
      <p class="teacher-school"><?=h($schoolName)?> · <?=h($libraryName)?></p>
      <span class="teacher-id">Teacher ID <strong><?=h($teacher['teacher_no'])?></strong></span>
      <p class="teacher-help">Show your digital pass to library staff to borrow a book. Your teacher loans have no item limit and no due date.</p>
      <div class="teacher-actions">
        <a class="btn" href="#pass"><?=ic('id-card')?>View my ID pass</a>
        <a class="btn ghost" href="account.php"><?=ic('user-circle')?>My account</a>
      </div>
    </div>
    <div class="teacher-pass">
      <span class="teacher-pass-title">Teacher library pass</span>
      <div class="qr" data-code="TCH:<?=h($teacher['teacher_no'])?>"></div>
      <small>Present this QR at the library desk</small>
    </div>
  </section>

  <div class="teacher-stats">
    <article class="teacher-stat green">
      <span class="teacher-stat-icon"><?=ic('books')?></span>
      <div class="teacher-stat-copy"><span>Books currently borrowed</span><strong><?=count($activeLoans)?></strong></div>
    </article>
    <article class="teacher-stat blue">
      <span class="teacher-stat-icon"><?=ic('clock')?></span>
      <div class="teacher-stat-copy"><span>Total borrowing records</span><strong><?=count($loans)?></strong></div>
    </article>
    <article class="teacher-stat violet">
      <span class="teacher-stat-icon"><?=ic('info')?></span>
      <div class="teacher-stat-copy"><span>Teacher loan policy</span><strong class="policy">No limit · No due date</strong></div>
    </article>
  </div>
  </div>

  <section class="card teacher-id-section teacher-view" id="pass" data-teacher-section hidden>
    <div class="teacher-id-heading">
      <div><h3>Teacher ID pass</h3><p>Download a high-resolution image showing both sides of your pass.</p></div>
      <button type="button" id="download-teacher-id"><?=ic('download')?>Download ID</button>
    </div>
    <p id="teacher-download-status" class="teacher-download-status" role="status" aria-live="polite"></p>
    <div class="teacher-id-sides" data-teacher-name="<?=h($teacher['name'])?>" data-teacher-no="<?=h($teacher['teacher_no'])?>" data-library-name="<?=h($libraryName)?>" data-school-name="<?=h($schoolName)?>" data-teacher-photo="<?=h(teacher_photo_path_ok($teacher['photo']??'')?$teacher['photo']:'')?>">
      <article class="teacher-id-card">
        <div class="teacher-id-head">
          <span class="teacher-id-brand"><?php if($logoPath):?><img src="<?=h($logoPath)?>?v=<?=filemtime(__DIR__.'/'.$logoPath)?>" alt=""><?php else:?><i class="teacher-id-avatar" style="width:27px;height:27px;font-size:13px">e</i><?php endif;?><span><?=h($libraryName)?><small><?=h($schoolName)?></small></span></span>
          <span class="teacher-id-tag">TEACHER LIBRARY PASS</span>
        </div>
        <div class="teacher-id-main">
          <span class="teacher-id-avatar"><?php if(teacher_photo_path_ok($teacher['photo']??'')&&is_file(__DIR__.'/'.$teacher['photo'])):?><img src="<?=h($teacher['photo'])?>" alt="<?=h($teacher['name'])?>"><?php else:?><?=h(function_exists('mb_substr')?mb_strtoupper(mb_substr(trim($teacher['name']),0,1,'UTF-8'),'UTF-8'):strtoupper(substr(trim($teacher['name']),0,1)))?><?php endif;?></span>
          <div class="teacher-id-person">
            <small>TEACHER NAME</small>
            <h4><?=h($teacher['name'])?></h4>
            <span class="teacher-id-number-label">TEACHER ID</span>
            <strong class="teacher-id-number"><?=h($teacher['teacher_no'])?></strong>
          </div>
          <div class="teacher-id-qr qr" data-code="TCH:<?=h($teacher['teacher_no'])?>"></div>
        </div>
        <div class="teacher-id-footer"><span>TEACHER IDENTIFICATION</span><span>SCAN FOR LIBRARY LOANS</span></div>
      </article>
      <article class="teacher-id-card teacher-id-back">
        <div class="teacher-id-head">
          <span class="teacher-id-brand"><?php if($logoPath):?><img src="<?=h($logoPath)?>?v=<?=filemtime(__DIR__.'/'.$logoPath)?>" alt=""><?php else:?><i class="teacher-id-avatar" style="width:27px;height:27px;font-size:13px">e</i><?php endif;?><span><?=h($schoolName)?><small>TEACHER IDENTIFICATION</small></span></span>
          <span class="teacher-id-tag">TEACHER PASS</span>
        </div>
        <div class="teacher-id-back-body">
          <strong>This pass belongs to <?=h($teacher['name'])?>.</strong>
          <span>Present this pass to library staff when borrowing or returning books.</span>
          <span>Teacher loans have no item limit and no due date. All loans must be returned to the library.</span>
        </div>
        <div class="teacher-id-signature">TEACHER SIGNATURE</div>
        <div class="teacher-id-footer"><span>PROPERTY OF <?=h(strtoupper($schoolName))?></span><span>IF FOUND, PLEASE RETURN TO THE LIBRARY</span></div>
      </article>
    </div>
  </section>

  <section class="card teacher-loan-card teacher-view" id="borrowed" data-teacher-section hidden>
    <div class="ph"><span class="pico"><?=ic('books')?></span><div><h3>My borrowed books</h3><p>Track your active and returned library books.</p></div><span class="badge"><?=count($activeLoans)?> active</span></div>
    <div class="tw"><table class="rt">
      <tr><th>Book</th><th>Date borrowed</th><th>Due date</th><th>Date returned</th><th>Status</th></tr>
      <?php foreach($loans as $loan):$returned=!empty($loan['returned_at']);?>
        <tr>
          <td data-label="Book"><?=h($loan['title'])?><?php if($loan['author']):?><small class="muted" style="display:block"><?=h($loan['author'])?></small><?php endif;?></td>
          <td data-label="Date borrowed"><?=h(date('M j, Y · h:i A',strtotime($loan['borrowed_at'])))?></td>
          <td data-label="Due date">No due date</td>
          <td data-label="Date returned"><?=$returned?h(date('M j, Y · h:i A',strtotime($loan['returned_at']))):'Not yet returned'?></td>
          <td data-label="Status"><span class="pill <?=$returned?'ok':'out'?>"><?=$returned?'Returned':'Borrowed'?></span></td>
        </tr>
      <?php endforeach;?>
      <?php if(!$loans):?><tr><td class="teacher-empty" colspan="5">No borrowing records yet. Your book loans will appear here.</td></tr><?php endif;?>
    </table></div>
  </section>
</div>
<?php foot('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>
(function(){
  const sections=Array.from(document.querySelectorAll("[data-teacher-section]"));
  const links=Array.from(document.querySelectorAll(".teacher-section-nav a[href^=\\"#\\"]"));
  function showSection(){
    const id=location.hash.slice(1)||"dashboard";
    const active=sections.find(section=>section.id===id)?id:"dashboard";
    sections.forEach(section=>{section.hidden=section.id!==active;});
    links.forEach(link=>link.classList.toggle("active",link.hash==="#"+active));
    if(active==="pass")renderQR(true);
  }
  window.addEventListener("hashchange",showSection);
  showSection();

  const downloadButton=document.getElementById("download-teacher-id");
  const status=document.getElementById("teacher-download-status");
  const cards=document.querySelector(".teacher-id-sides");
  function roundedRect(ctx,x,y,width,height,radius){
    ctx.beginPath();
    ctx.roundRect(x,y,width,height,radius);
  }
  function drawText(ctx,text,x,y,maxWidth,font,color){
    ctx.font=font;
    ctx.fillStyle=color;
    let line="";
    const words=String(text).split(/\\s+/);
    for(let i=0;i<words.length;i++){
      const next=line?line+" "+words[i]:words[i];
      if(ctx.measureText(next).width>maxWidth&&line){
        ctx.fillText(line,x,y);
        line=words[i];
        y+=34;
      }else line=next;
    }
    if(line)ctx.fillText(line,x,y);
    return y;
  }
  async function downloadId(){
    downloadButton.disabled=true;
    status.textContent="Preparing your ID download…";
    try{
      renderQR(true);
      const qr=document.querySelector(".teacher-id-qr canvas,.teacher-id-qr img");
      if(!qr)throw new Error("The ID QR code could not be generated. Refresh the page and try again.");
      const width=1012,height=638,gap=24,canvas=document.createElement("canvas");
      canvas.width=width*2+gap;
      canvas.height=height;
      const ctx=canvas.getContext("2d");
      if(!ctx)throw new Error("Your browser could not create the ID image.");
      const teacherName=cards.dataset.teacherName;
      const teacherNo=cards.dataset.teacherNo;
      const libraryName=cards.dataset.libraryName;
      const schoolName=cards.dataset.schoolName;
      let teacherPhoto=null;
      if(cards.dataset.teacherPhoto){
        teacherPhoto=new Image();
        teacherPhoto.src=cards.dataset.teacherPhoto;
        try{await teacherPhoto.decode();}catch(error){teacherPhoto=null;}
      }
      function drawCard(x,back){
        ctx.save();
        roundedRect(ctx,x,0,width,height,40);
        ctx.clip();
        const bg=ctx.createLinearGradient(x,0,x+width,height);
        bg.addColorStop(0,back?"#f3f8f4":"#e7f4e9");
        bg.addColorStop(1,back?"#e8f2ed":"#edf5ee");
        ctx.fillStyle=bg;
        ctx.fillRect(x,0,width,height);
        ctx.strokeStyle="#cde3d2";
        ctx.lineWidth=4;
        roundedRect(ctx,x+2,2,width-4,height-4,38);
        ctx.stroke();
        ctx.fillStyle="rgba(31,122,70,.055)";
        for(let gx=x+22;gx<width+x;gx+=44)for(let gy=18;gy<height;gy+=44)ctx.fillRect(gx,gy,2,2);
        const logo=document.querySelector(".teacher-id-brand img");
        if(logo&&logo.complete&&logo.naturalWidth){
          ctx.save();
          ctx.beginPath();ctx.roundRect(x+34,24,66,66,14);ctx.clip();
          ctx.fillStyle="#fff";ctx.fillRect(x+34,24,66,66);
          ctx.drawImage(logo,x+40,30,54,54);
          ctx.restore();
        }
        ctx.fillStyle="#17653a";
        ctx.font="800 34px Arial";
        ctx.fillText(back?schoolName:libraryName,x+118,65);
        ctx.fillStyle="#557663";
        ctx.font="600 21px Arial";
        ctx.fillText(back?"TEACHER IDENTIFICATION":schoolName,x+118,96);
        ctx.fillStyle="#ffffff";
        roundedRect(ctx,x+width-275,42,235,42,21);
        ctx.fill();
        ctx.fillStyle="#17653a";
        ctx.font="800 16px Arial";
        ctx.fillText(back?"TEACHER PASS":"TEACHER LIBRARY PASS",x+width-258,69);
        if(!back){
          const cx=x+88,cy=height/2+20;
          ctx.save();
          ctx.beginPath();ctx.arc(cx,cy,57,0,Math.PI*2);ctx.clip();
          if(teacherPhoto)ctx.drawImage(teacherPhoto,cx-57,cy-57,114,114);
          else{
            ctx.fillStyle="#1f7a46";ctx.fillRect(cx-57,cy-57,114,114);
            ctx.fillStyle="#fff";ctx.font="800 46px Arial";ctx.textAlign="center";
            ctx.fillText((teacherName.trim().charAt(0)||"?").toLocaleUpperCase(),cx,cy+16);
            ctx.textAlign="left";
          }
          ctx.restore();
          ctx.fillStyle="#557663";
          ctx.font="800 17px Arial";
          ctx.fillText("TEACHER NAME",x+170,245);
          drawText(ctx,teacherName,x+170,292,width-450,"800 35px Arial","#0f3d26");
          ctx.fillStyle="#557663";
          ctx.font="800 17px Arial";
          ctx.fillText("TEACHER ID",x+170,390);
          ctx.fillStyle="#0f3d26";
          ctx.font="800 27px Arial";
          ctx.fillText(teacherNo,x+170,430);
          ctx.fillStyle="#fff";
          roundedRect(ctx,x+width-215,165,164,164,16);ctx.fill();
          ctx.drawImage(qr,x+width-207,173,148,148);
          ctx.fillStyle="#557663";
          ctx.font="700 15px Arial";
          ctx.fillText("SCAN FOR LIBRARY LOANS",x+width-237,356);
          ctx.fillStyle="#557663";
          ctx.font="700 13px Arial";
          ctx.fillText("TEACHER IDENTIFICATION",x+36,height-30);
          ctx.textAlign="right";
          ctx.fillText("PROPERTY OF "+schoolName.toLocaleUpperCase(),x+width-36,height-30);
          ctx.textAlign="left";
        }else{
          ctx.fillStyle="#163d32";
          ctx.font="800 25px Arial";
          ctx.fillText("This pass belongs to "+teacherName+".",x+42,205);
          drawText(ctx,"Present this pass to library staff when borrowing or returning books.",x+42,260,width-84,"500 22px Arial","#557663");
          drawText(ctx,"Teacher loans have no item limit and no due date. All loans must be returned to the library.",x+42,330,width-84,"500 22px Arial","#557663");
          ctx.strokeStyle="#6f9180";ctx.lineWidth=2;
          ctx.beginPath();ctx.moveTo(x+42,height-112);ctx.lineTo(x+width-42,height-112);ctx.stroke();
          ctx.fillStyle="#688779";
          ctx.font="800 16px Arial";
          ctx.fillText("TEACHER SIGNATURE",x+42,height-82);
          ctx.font="700 13px Arial";
          ctx.fillText("IF FOUND, PLEASE RETURN TO THE LIBRARY",x+42,height-30);
        }
        ctx.restore();
      }
      drawCard(0,false);
      drawCard(width+gap,true);
      const blob=await new Promise(resolve=>canvas.toBlob(resolve,"image/png"));
      if(!blob)throw new Error("The ID image could not be downloaded.");
      const link=document.createElement("a");
      link.href=URL.createObjectURL(blob);
      link.download="Teacher-ID-"+teacherNo.replace(/[^A-Za-z0-9_-]/g,"_")+".png";
      link.click();
      setTimeout(()=>URL.revokeObjectURL(link.href),1000);
      status.textContent="Teacher ID downloaded. The PNG includes both front and back.";
    }catch(error){
      status.textContent=error.message||"Could not download the Teacher ID.";
      console.error("Teacher ID download failed:",error);
    }finally{
      downloadButton.disabled=false;
    }
  }
  downloadButton.addEventListener("click",downloadId);
})();
</script>'); ?>
