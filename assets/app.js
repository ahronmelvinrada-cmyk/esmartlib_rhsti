function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML}
function renderQR(){document.querySelectorAll('.qr').forEach(e=>new QRCode(e,{text:e.dataset.code,width:110,height:110}))}
function bindScanner(input,cb){ // USB scanners type the code then press Enter
  input.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();const v=input.value.trim();input.value='';if(v)cb(v)}});
  document.addEventListener('click',e=>{if(!/BUTTON|A|SELECT/.test(e.target.tagName))input.focus()});input.focus()}
function startCamera(id,cb){const s=new Html5Qrcode(id);let last='',t=0;
  s.start({facingMode:'environment'},{fps:10,qrbox:220},txt=>{const n=Date.now();if(txt===last&&n-t<3000)return;last=txt;t=n;cb(txt)})}
async function api(action,data){const r=await fetch('api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action,...data})});return r.json()}
function flash(ok,msg){const l=document.getElementById('log');const d=document.createElement('div');d.className='msg '+(ok?'good':'bad');
  d.textContent=new Date().toLocaleTimeString()+'  '+msg;l.prepend(d);try{new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAESsAACJWAAACABAAZGF0YQAAAAA=').play()}catch(e){}}
