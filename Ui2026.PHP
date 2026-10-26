<?php
/* Shared 2026 UI kit: icons + styles for inner pages.
   Usage: require 'ui2026.php' (after config.php), then call ui_css() right after head(...). */

function ic($k){
    static $p = [
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'upload'   => '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5M4 20h16"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'print'    => '<path d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2"/><path d="M6 14h12v7H6z"/>',
        'scan'     => '<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M4 12h16"/>',
        'camera'   => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13.5" r="3.5"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'book'     => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Z"/><path d="M19 19v2H6"/>',
        'file'     => '<path d="M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7l-4-4Z"/><path d="M14 3v4h4"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'return'   => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$k] ?? '') . '</svg>';
}

/* ---------- Admin settings (key/value table) ---------- */
function settings_init($pdo){
    static $d=false; if($d) return; $d=true;
    try{ $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k VARCHAR(64) NOT NULL PRIMARY KEY, v TEXT NULL)"); }catch(Exception $e){}
}
function setting($pdo,$k,$def=''){
    static $c=null;
    if($c===null){ $c=[]; settings_init($pdo); try{ foreach($pdo->query("SELECT k,v FROM settings") as $r) $c[$r['k']]=$r['v']; }catch(Exception $e){} }
    return (isset($c[$k])&&$c[$k]!=='') ? $c[$k] : $def;
}
function setting_set($pdo,$k,$v){
    settings_init($pdo);
    $pdo->prepare("INSERT INTO settings(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute([$k,(string)$v]);
}

/* ---------- Student profile photos ---------- */
function photo_col($pdo){
    static $done=false; if($done) return; $done=true;
    try{
        $r=$pdo->query("SHOW COLUMNS FROM students LIKE 'photo'");
        if(!$r||!$r->fetch()) $pdo->exec("ALTER TABLE students ADD COLUMN photo VARCHAR(255) NULL");
    }catch(Exception $e){}
}
function photo_path_ok($p){ return $p && strpos($p,'uploads/students/')===0 && strpos($p,'..')===false; }
function photo_delete_file($p){ if(photo_path_ok($p)){ $f=__DIR__.'/'.$p; if(is_file($f)) @unlink($f); } }
function photo_remove($pdo,$sid){
    $s=$pdo->prepare("SELECT photo FROM students WHERE id=?"); $s->execute([$sid]); $old=$s->fetchColumn();
    $pdo->prepare("UPDATE students SET photo=NULL WHERE id=?")->execute([$sid]);
    photo_delete_file($old);
}
/* returns '' on success, or an error message */
function photo_save($pdo,$sid,$f){
    $err=$f['error']??UPLOAD_ERR_NO_FILE;
    if($err===UPLOAD_ERR_NO_FILE) return 'Choose a photo first.';
    if($err===UPLOAD_ERR_INI_SIZE||$err===UPLOAD_ERR_FORM_SIZE) return 'That photo is too large for the server.';
    if($err!==UPLOAD_ERR_OK) return 'Upload failed. Please try again.';
    if(($f['size']??0)>8*1024*1024) return 'Photo is too large (max 8 MB).';
    $info=@getimagesize($f['tmp_name']);
    $okTypes=[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP];
    if(!$info||!in_array($info[2],$okTypes,true)) return 'Only JPG, PNG or WEBP images are allowed.';
    $dir=__DIR__.'/uploads/students';
    if(!is_dir($dir)&&!@mkdir($dir,0755,true)) return 'Cannot create the upload folder.';
    if(!is_file($dir.'/.htaccess')) @file_put_contents($dir.'/.htaccess',"<FilesMatch \"\\.(php|phtml|phar)\$\">\nRequire all denied\n</FilesMatch>\n");
    $name='s'.(int)$sid.'_'.time().'.jpg'; $dest=$dir.'/'.$name;
    if(function_exists('imagecreatetruecolor')){
        $src=false;
        if($info[2]===IMAGETYPE_JPEG) $src=@imagecreatefromjpeg($f['tmp_name']);
        elseif($info[2]===IMAGETYPE_PNG) $src=@imagecreatefrompng($f['tmp_name']);
        elseif(function_exists('imagecreatefromwebp')) $src=@imagecreatefromwebp($f['tmp_name']);
        if(!$src) return 'Could not read that image.';
        if($info[2]===IMAGETYPE_JPEG&&function_exists('exif_read_data')){
            $ex=@exif_read_data($f['tmp_name']); $o=$ex['Orientation']??1;
            $rot=[3=>180,6=>-90,8=>90][$o]??0;
            if($rot){ $r=@imagerotate($src,$rot,0); if($r) $src=$r; }
        }
        $w=imagesx($src); $h=imagesy($src); $s=min($w,$h);
        $out=imagecreatetruecolor(480,480);
        imagefill($out,0,0,imagecolorallocate($out,255,255,255));
        imagecopyresampled($out,$src,0,0,intdiv($w-$s,2),intdiv($h-$s,2),480,480,$s,$s);
        if(!imagejpeg($out,$dest,86)) return 'Could not save the photo.';
    }else{
        $ext=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'][$info[2]];
        $name='s'.(int)$sid.'_'.time().'.'.$ext; $dest=$dir.'/'.$name;
        if(!move_uploaded_file($f['tmp_name'],$dest)) return 'Could not save the photo.';
    }
    $s=$pdo->prepare("SELECT photo FROM students WHERE id=?"); $s->execute([$sid]); $old=$s->fetchColumn();
    $pdo->prepare("UPDATE students SET photo=? WHERE id=?")->execute(['uploads/students/'.$name,$sid]);
    if($old&&$old!=='uploads/students/'.$name) photo_delete_file($old);
    return '';
}
function avatar($r,$size=''){
    $p=$r['photo']??'';
    if(photo_path_ok($p)&&is_file(__DIR__.'/'.$p))
        return '<img class="pav '.$size.'" src="'.h($p).'" alt="'.h($r['name']??'').'" loading="lazy">';
    $t=trim($r['name']??'?'); $ini=function_exists('mb_substr')?mb_strtoupper(mb_substr($t,0,1)):strtoupper(substr($t,0,1));
    return '<span class="pav '.$size.'">'.h($ini).'</span>';
}

/* Sidebar behaviour only (avatar + mobile hamburger). Sidebar CSS lives in style.css. */
function ui_side(){ static $done=false; if($done) return; $done=true; ?>
<script>
(function(){
  var a=document.querySelector('aside'); if(!a) return;
  var w=a.querySelector('.who');
  if(w && !w.querySelector('.av2')){
    var d=document.createElement('div'); d.className='av2';
    d.textContent=(w.textContent||'').trim().charAt(0).toUpperCase();
    w.insertBefore(d,w.firstChild);
  }
  if(!a.querySelector('.burger')){
    var b=document.createElement('button'); b.type='button'; b.className='burger';
    b.setAttribute('aria-label','Menu'); b.innerHTML='<span></span><span></span><span></span>';
    var br=a.querySelector('.brand'); a.insertBefore(b,br?br.nextSibling:a.firstChild);
    b.onclick=function(){a.classList.toggle('open');};
  }
})();
</script>
<?php }

function ui_css(){ ui_side(); ?>
<style>
.pg{display:grid;gap:18px;--r:20px}
.pg *{box-sizing:border-box}
.pg .ic{width:18px;height:18px;flex:none}

/* Cards */
.pg .card{margin:0;border-radius:var(--r);padding:22px;overflow:visible}
.pg .ph{display:flex;align-items:center;gap:14px;margin-bottom:16px;flex-wrap:wrap}
.pg .ph h3{margin:0;font-size:17px;font-weight:800;letter-spacing:-.02em}
.pg .ph p{margin:2px 0 0;color:var(--mute);font-size:13px}
.pg .ph .badge{margin-left:auto}
.pg .pico{width:44px;height:44px;border-radius:14px;background:var(--leaf);color:var(--g);display:grid;place-items:center}
.pg .pico .ic{width:22px;height:22px}

/* Forms */
.pg .fgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}
.pg input,.pg select{width:100%;margin:0;height:46px;padding:0 14px;border-radius:13px;background:#f8fbf9;border:1px solid var(--line);transition:border-color .2s,box-shadow .2s,background .2s}
.pg input:hover,.pg select:hover{border-color:#c4d4c8}
.pg input:focus,.pg select:focus{background:#fff;border-color:var(--g);outline:0;box-shadow:0 0 0 4px rgba(31,122,70,.15)}
.pg .fact{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:16px}

/* Buttons */
.pg button,.pg .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;margin:0;height:46px;padding:0 20px;border-radius:13px;font-weight:700;transition:transform .15s,box-shadow .2s,background .2s}
.pg button{background:linear-gradient(135deg,#2a9d5c,var(--g));color:#fff;box-shadow:0 10px 22px -12px var(--g)}
.pg button:hover{transform:translateY(-1px);background:linear-gradient(135deg,#2fae66,#1a6d3d)}
.pg button:active{transform:translateY(0)}
.pg .ghost{background:#fff;color:var(--gd);border:1px solid var(--line);box-shadow:none}
.pg .ghost:hover{background:var(--leaf);border-color:#c4d4c8}
.pg .danger{background:var(--redbg);color:var(--red);box-shadow:none;height:38px;padding:0 14px;font-size:13px;width:100%}
.pg .danger:hover{background:#fbd3cf}
.pg .link{display:inline-flex;align-items:center;gap:6px;color:var(--g);font-weight:700;text-decoration:none;font-size:14px}
.pg .link:hover{text-decoration:underline}

/* File picker */
.pg input[type=file]{height:auto;padding:10px;border:2px dashed #b9d3c0;background:#f6fbf7;color:var(--mute);cursor:pointer}
.pg input[type=file]:hover{border-color:var(--g);background:#eef8f1}
.pg input[type=file]::file-selector-button{margin-right:12px;border:0;border-radius:10px;padding:10px 16px;background:var(--g);color:#fff;font:inherit;font-weight:700;cursor:pointer}
.pg .hint{color:var(--mute);font-size:12.5px;margin:12px 0 0}
.pg .importrow{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center}
@media(max-width:640px){.pg .importrow{grid-template-columns:1fr}}

/* Search bar */
.pg .bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.pg .sw{position:relative;flex:1 1 260px}
.pg .sw .ic{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:var(--mute);pointer-events:none}
.pg .sw input{padding-left:44px}
.pg .badge{display:inline-block;font-size:12px;font-weight:700;padding:6px 12px;border-radius:999px;background:#eef3ef;color:var(--mute)}

/* Avatars / profile photo */
.pg .pav{width:48px;height:48px;border-radius:50%;object-fit:cover;display:grid;place-items:center;flex:none;background:linear-gradient(135deg,#2a9d5c,var(--g));color:#fff;font-weight:800;font-size:18px;border:3px solid #fff;box-shadow:0 8px 18px -8px rgba(15,61,38,.55)}
.pg img.pav{display:block}
.pg .pav.xl{width:124px;height:124px;font-size:46px;border-width:4px}
.pg .qrcard{position:relative}
.pg .qrcard .pav{position:absolute;top:12px;right:12px;width:46px;height:46px;font-size:17px}
.pg .pcol{display:flex;flex-direction:column;align-items:center;gap:10px;min-width:150px}
.pg .pcol button{height:40px;font-size:14px;padding:0 16px}
.pg .pwrap{position:relative}
.pg .camb{position:absolute;right:2px;bottom:2px;width:38px;height:38px;border-radius:50%;background:var(--g);color:#fff;display:grid;place-items:center;border:3px solid #fff;cursor:pointer;transition:transform .15s,background .2s}
.pg .camb:hover{transform:scale(1.08);background:#17653a}
.pg input.hid{display:none}
.pg .lbtn{display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 16px;border-radius:13px;border:1px solid var(--line);background:#fff;color:var(--gd);font-weight:700;font-size:14px;cursor:pointer;transition:background .2s,border-color .2s}
.pg .lbtn:hover{background:var(--leaf);border-color:#c4d4c8}
.pg .ghost.rm{color:var(--red)}
.pg .ghost.rm:hover{background:var(--redbg);border-color:#f4c7c2}
.pg .sm{height:34px;padding:0 12px;font-size:12.5px;width:100%}
.pg a.vbtn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;height:38px;margin-top:6px;border-radius:13px;font-weight:700;font-size:13px;text-decoration:none;border:1px solid var(--line);transition:background .2s,border-color .2s}
.pg .phint{color:var(--mute);font-size:11.5px}

/* Notices */
.pg .note{display:flex;align-items:center;gap:10px;background:#ecfdf5;border:1px solid #bbf7d0;color:#14532d;border-radius:14px;padding:12px 16px;font-weight:600;animation:rise .35s ease both}
.pg .msg{display:block;border-radius:14px;padding:14px 18px;font-weight:700;font-size:15px;border-left:5px solid currentColor;animation:rise .3s ease both;margin:0}
.pg .msg.good{background:#ecfdf5;color:#166534}
.pg .msg.bad{background:var(--redbg);color:var(--red)}
#log:empty{display:none}
@keyframes rise{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

/* QR grid */
.pg .qrgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:16px}
.pg .qrcard{padding:18px;gap:8px;align-items:center;text-align:center;transition:transform .25s,box-shadow .25s,border-color .25s}
.pg .qrcard:hover{transform:translateY(-4px);box-shadow:0 18px 40px -20px rgba(15,31,23,.3);border-color:#b9d3c0}
.pg .qr{display:grid;place-items:center;padding:12px;border:1px solid var(--line);border-radius:16px;background:#fff;margin-bottom:4px}
.pg .qr img,.pg .qr canvas{border-radius:6px}
.pg .qrcard b{font-size:15px;line-height:1.3;letter-spacing:-.01em}
.pg .qrcard small{color:var(--mute);font-size:12.5px}
.pg .chips{display:flex;gap:6px;flex-wrap:wrap;justify-content:center}
.pg .chip{display:inline-block;padding:3px 10px;border-radius:999px;background:var(--leaf);color:var(--gd);font-size:12px;font-weight:700}
.pg .chip.id{background:#f1f5f3;color:var(--mute);font-feature-settings:"tnum"}
.pg .qrcard form{width:100%;margin-top:6px}
.pg .meter{width:100%;height:7px;border-radius:99px;background:#e9efea;overflow:hidden;margin-top:2px}
.pg .meter i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#34d399,var(--g))}
.pg .meter.mid i{background:linear-gradient(90deg,#fbbf24,var(--amber))}
.pg .meter.low i{background:linear-gradient(90deg,#fb7185,var(--red))}
.pg .empty{grid-column:1/-1;text-align:center;padding:48px 10px;color:var(--mute)}
.pg .empty b{display:block;color:var(--ink);font-size:17px;margin-bottom:4px}

/* Scanner pages */
.pg .scan{text-align:center;padding:32px 22px}
.pg .scan .ph{justify-content:center;text-align:left}
.pg .scanwrap{position:relative;max-width:640px;margin:6px auto 0}
.pg .scanwrap .ic{position:absolute;left:20px;top:50%;transform:translateY(-50%);width:24px;height:24px;color:var(--g);pointer-events:none}
.pg .big{height:68px;max-width:none;font-size:20px;font-weight:600;padding:0 20px 0 58px;border:2px solid var(--g);border-radius:18px;background:#f8fcf9}
.pg .big:focus{box-shadow:0 0 0 6px rgba(31,122,70,.14)}
.pg .scan .fact{justify-content:center}
.pg #reader{max-width:440px;margin:16px auto 0;border-radius:18px;overflow:hidden}
.pg #reader:empty{display:none}

/* Tables */
.pg .tw{overflow-x:auto;margin:0 -6px;padding:0 6px}
.pg table{border:0;border-radius:0;background:transparent;overflow:visible;border-collapse:separate;border-spacing:0 6px;min-width:560px}
.pg th{background:transparent;color:var(--mute);font-size:11.5px;text-transform:uppercase;letter-spacing:.07em;border-bottom:0;padding:6px 14px}
.pg td{background:#f8fbf9;border-bottom:0;padding:14px;font-size:14px}
.pg td:first-child{border-radius:14px 0 0 14px;font-weight:700}
.pg td:last-child{border-radius:0 14px 14px 0}
.pg tr:hover td{background:#eef8f1}
.pg .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:999px;font-weight:700;font-size:12px}
.pg .pill.ok{background:#d1fae5;color:#065f46}
.pg .pill.late{background:#ffe4e6;color:#be123c}
.pg .pill.in{background:#dbeafe;color:#1d4ed8}
.pg .pill.out{background:#eef3ef;color:var(--mute)}
.pg .muted{color:var(--mute)}

@media(max-width:640px){.pg .card{padding:16px}.pg .big{height:60px;font-size:17px}}
@media print{
  .pg{display:block}
  .pg .card,.pg .qrcard{box-shadow:none!important;transform:none!important;border:1px solid #ccc;border-radius:10px}
  .pg .qrgrid{grid-template-columns:repeat(4,1fr);gap:10px}
  .pg .meter,.pg .chip{border:1px solid #ccc;background:#fff}
}
@media(prefers-reduced-motion:reduce){.pg *{animation:none!important;transition:none!important}}
</style>
<?php }