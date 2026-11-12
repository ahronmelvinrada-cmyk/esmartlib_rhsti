function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML}

function setTheme(theme){
  const dark=theme==='dark';
  document.documentElement.dataset.theme=dark?'dark':'light';
  document.querySelectorAll('.tg').forEach(button=>{
    button.setAttribute('aria-pressed',dark?'true':'false');
    button.setAttribute('aria-label',dark?'Switch to light mode':'Switch to dark mode');
    button.title=dark?'Switch to light mode':'Switch to dark mode';
    button.innerHTML=dark
      ?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/></svg>'
      :'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>';
  });
}

function toggleTheme(){
  const next=document.documentElement.dataset.theme==='dark'?'light':'dark';
  try{localStorage.setItem('theme',next)}catch(e){}
  setTheme(next);
}

window.toggleTheme=toggleTheme;
setTheme(document.documentElement.dataset.theme==='dark'?'dark':'light');
document.addEventListener('click',event=>{
  if(event.target.closest('.tg'))toggleTheme();
});

let scanFeedbackOnClose=null;
let scanFeedbackAudioUnlocked=false;
const scanFeedbackAudio=new Audio('assets/applepay.mp3');
scanFeedbackAudio.preload='auto';

window.prepareScanFeedbackAudio=async function(){
  if(scanFeedbackAudioUnlocked)return;
  try{
    scanFeedbackAudio.muted=true;
    await scanFeedbackAudio.play();
    scanFeedbackAudio.pause();
    scanFeedbackAudio.currentTime=0;
    scanFeedbackAudio.muted=false;
    scanFeedbackAudioUnlocked=true;
  }catch(error){
    scanFeedbackAudio.muted=false;
    const status=document.getElementById('scan-feedback-status');
    if(status)status.textContent='Could not enable scan sounds. Check that sound is enabled and assets/applepay.mp3 is available.';
    console.error('Scan feedback audio could not be enabled:',error);
  }
};

async function playScanFeedbackAudio(){
  try{
    scanFeedbackAudio.currentTime=0;
    await scanFeedbackAudio.play();
  }catch(error){
    const status=document.getElementById('scan-feedback-status');
    if(status)status.textContent='Could not play the scan sound. Check that sound is enabled and assets/applepay.mp3 is available.';
    console.error('Scan feedback audio playback failed:',error);
  }
}

function closeScanFeedback(){
  const popup=document.getElementById('scan-feedback');
  if(!popup||popup.hidden)return;
  popup.hidden=true;
  window.scanFeedbackOpen=false;
  const onClose=scanFeedbackOnClose;
  scanFeedbackOnClose=null;
  if(onClose)onClose();
}

window.showScanFeedback=function(title,message,profile,bookTitle,onClose){
  const popup=document.getElementById('scan-feedback');
  if(!popup)return;
  if(window.scanFeedbackOpen)return;
  window.scanFeedbackOpen=true;
  scanFeedbackOnClose=onClose||null;
  document.getElementById('scan-feedback-title').textContent=title;
  document.getElementById('scan-feedback-message').textContent=message;
  const profileBox=document.getElementById('scan-feedback-profile');
  const photoBox=document.getElementById('scan-feedback-photo');
  if(profileBox&&profile){
    profileBox.hidden=false;
    photoBox.replaceChildren();
    const photoPath=profile.photo||'';
    if(photoPath&&(/^(uploads\/students|uploads\/teachers)\//).test(photoPath)&&!photoPath.includes('..')){
      const photo=document.createElement('img');
      photo.src=photoPath;
      photo.alt=profile.name||'Borrower profile';
      photoBox.appendChild(photo);
    }else{
      const initials=document.createElement('span');
      initials.textContent=(profile.name||'?').trim().charAt(0).toUpperCase()||'?';
      photoBox.appendChild(initials);
    }
    document.getElementById('scan-feedback-name').textContent=profile.name||'';
    document.getElementById('scan-feedback-id').textContent=(profile.type||'Borrower')+' ID: '+(profile.id||'');
    const meta=[profile.grade,profile.strand,profile.section].filter(Boolean);
    document.getElementById('scan-feedback-meta').textContent=meta.join(' · ');
  }else if(profileBox){
    profileBox.hidden=true;
  }
  document.getElementById('scan-feedback-details').textContent=bookTitle||'';
  const status=document.getElementById('scan-feedback-status');
  if(status)status.textContent='';
  popup.hidden=false;
  document.getElementById('scan-feedback-close').focus();
  playScanFeedbackAudio();
};

function initScanFeedback(){
  const popup=document.getElementById('scan-feedback');
  const close=document.getElementById('scan-feedback-close');
  if(close)close.addEventListener('click',closeScanFeedback);
  if(popup)popup.addEventListener('click',event=>{
    if(event.target===popup)closeScanFeedback();
  });
  document.addEventListener('keydown',event=>{
    if(event.key==='Escape')closeScanFeedback();
  });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initScanFeedback,{once:true});
else initScanFeedback();

function drawQR(e){
  if(e.dataset.qrRendered==='true')return;
  e.dataset.qrRendered='true';
  new QRCode(e,{text:e.dataset.code,width:110,height:110});
}

function renderQR(force){
  const pending=Array.from(document.querySelectorAll('.qr')).filter(e=>e.dataset.qrRendered!=='true');
  if(force){
    pending.forEach(drawQR);
    return;
  }
  if(!('IntersectionObserver' in window)){
    let index=0;
    const drawBatch=()=>{
      const end=Math.min(index+4,pending.length);
      for(;index<end;index++)drawQR(pending[index]);
      if(index<pending.length)setTimeout(drawBatch,0);
    };
    drawBatch();
    return;
  }

  const observer=new IntersectionObserver(entries=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        observer.unobserve(entry.target);
        drawQR(entry.target);
      }
    });
  },{rootMargin:'200px'});
  pending.forEach(e=>observer.observe(e));
}

window.addEventListener('beforeprint',()=>{
  document.querySelectorAll('.qr').forEach(drawQR);
});

/* USB scanner: types the code and presses Enter. */
function bindScanner(input,cb){
  input.addEventListener('keydown',e=>{
    if(e.key==='Enter'){
      e.preventDefault();
      const v=input.value.trim();
      input.value='';
      if(v)cb(v);
    }
  });
  document.addEventListener('click',e=>{
    if(e.target.closest('button,a,select,input,textarea,#reader,#chat-widget,[contenteditable="true"]'))return;
    input.focus();
  });
  input.focus();
}

/* Camera scanner */
let camScanner=null;
let camStarting=false;

function setCameraStatus(message){
  const status=document.getElementById('cam-status');
  if(status)status.textContent=message;
}

async function stopCamera(id){
  const box=document.getElementById(id);
  if(camScanner){
    try{await camScanner.stop();}catch(e){}
    try{camScanner.clear();}catch(e){}
    camScanner=null;
  }
  if(box){box.innerHTML='';box.hidden=true;}
  const btn=document.getElementById('cam');
  const label=btn&&btn.querySelector('.camtxt');
  if(label)label.textContent='Use Camera';
  const cameraSelect=document.getElementById('cam-select');
  if(cameraSelect)cameraSelect.hidden=true;
  setCameraStatus('');
}

async function startCamera(id,cb){
  const box=document.getElementById(id);

  if(camStarting)return;

  // Toggle the camera off when it is already running.
  if(camScanner){await stopCamera(id);return;}

  if(!box){
    setCameraStatus('Camera preview area not found. Reload the page and try again.');
    return;
  }
  if(typeof Html5Qrcode==='undefined'){
    setCameraStatus('The QR scanner could not be loaded. Check your internet connection and reload the page.');
    return;
  }
  if(!window.isSecureContext){
    setCameraStatus('HTTPS or localhost is required to use the camera. Current URL: '+location.origin);
    return;
  }

  camStarting=true;
  let last='',t=0,busy=false;
  const btn=document.getElementById('cam');
  const label=btn&&btn.querySelector('.camtxt');
  const cameraSelect=document.getElementById('cam-select');
  setCameraStatus('Requesting camera permission...');

  try{
    const cameras=await Html5Qrcode.getCameras();
    if(!cameras.length)throw new Error('No camera found.');
    const selected=cameras.find(camera=>camera.id===cameraSelect?.value)
      ||cameras.find(camera=>/back|rear|environment/i.test(camera.label))
      ||cameras[0];
    if(cameraSelect){
      cameraSelect.replaceChildren(...cameras.map((camera,index)=>{
        const option=document.createElement('option');
        option.value=camera.id;
        option.textContent=camera.label||'Camera '+(index+1);
        return option;
      }));
      cameraSelect.value=selected.id;
      cameraSelect.hidden=cameras.length<2;
      cameraSelect.onchange=async()=>{
        if(!camScanner||camStarting)return;
        await stopCamera(id);
        await startCamera(id,cb);
      };
    }
    box.hidden=false;
    camScanner=new Html5Qrcode(id);
    const config={
      fps:10,
      qrbox:(w,h)=>{const s=Math.max(50,Math.floor(Math.min(w,h)*0.7));return {width:s,height:s};}
    };
    const onScan=async txt=>{
      const n=Date.now();
      if(busy||(txt===last&&n-t<3000))return;
      busy=true;last=txt;t=n;
      try{await cb(txt);}
      finally{busy=false;t=Date.now();}
    };
    await camScanner.start(selected.id,config,onScan,()=>{});
    if(label)label.textContent='Stop Camera';
    setCameraStatus('Camera is on. Point the QR code at the frame. If the preview is black, select another camera.');
  }catch(e){
    try{await camScanner.stop();}catch(stopError){}
    try{camScanner.clear();}catch(clearError){}
    camScanner=null;
    box.innerHTML='';
    box.hidden=true;
    if(label)label.textContent='Use Camera';
    if(cameraSelect)cameraSelect.hidden=true;
    const m=String(e&&e.message||e);
    if(/Permission|NotAllowed/i.test(m)) setCameraStatus('Camera permission was blocked. Allow access in the site settings or the browser lock icon.');
    else if(/NotFound|no camera/i.test(m)) setCameraStatus('No camera was detected. Try another camera or check its connection.');
    else if(/NotReadable|in use/i.test(m)) setCameraStatus('The camera is being used by another app or browser tab. Close it and try again.');
    else setCameraStatus('Could not open the camera: '+m);
    console.error(e);
  }finally{
    camStarting=false;
  }
}

/* API */
async function api(action,data){
  const r=await fetch('api.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({action,...data})
  });
  try{return await r.json();}
  catch(e){return {ok:false,msg:'Server error. Check api.php.'};}
}

/* Mensahe + tunog */
function flash(ok,msg){
  const l=document.getElementById('log');
  const d=document.createElement('div');
  d.className='msg '+(ok?'good':'bad');
  d.textContent=new Date().toLocaleTimeString()+'  '+msg;
  l.prepend(d);
  try{new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAESsAACJWAAACABAAZGF0YQAAAAA=').play()}catch(e){}
}
