<?php require 'config.php';require 'ui2026.php';auth();
if(isset($_GET['template'])){header('Content-Type: text/csv');header('Content-Disposition: attachment; filename=students_template.csv');echo "student_no,name,gender,track,strand,grade,section\n2024-0001,Juan Dela Cruz,Male,Academic,STEM,11,A\n";exit;}
if(isset($_POST['import'])){
  $f=$_FILES['csv']['tmp_name']??'';
  if(!$f||$_FILES['csv']['error']){$m='Choose a CSV file first.';}
  else{
    $fh=fopen($f,'r');$first=preg_replace('/^\xEF\xBB\xBF/','',fgets($fh));rewind($fh);
    $d=substr_count($first,';')>substr_count($first,',')?';':',';
    $map=['student_no'=>0,'name'=>1,'gender'=>2,'track'=>3,'strand'=>4,'grade'=>5,'section'=>6];
    $alias=['student_id'=>'student_no','student_no'=>'student_no','studentno'=>'student_no','id'=>'student_no','lrn'=>'student_no','name'=>'name','full_name'=>'name','student_name'=>'name','gender'=>'gender','sex'=>'gender','track'=>'track','strand'=>'strand','grade'=>'grade','grade_level'=>'grade','section'=>'section'];
    $add=$skip=$bad=0;$i=0;
    $ins=$pdo->prepare("INSERT IGNORE INTO students(student_no,name,gender,track,strand,grade,section) VALUES(?,?,?,?,?,?,?)");
    $pdo->beginTransaction();
    while(($r=fgetcsv($fh,0,$d))!==false){
      $r=array_map(fn($x)=>mb_check_encoding((string)$x,'UTF-8')?(string)$x:mb_convert_encoding((string)$x,'UTF-8','Windows-1252'),$r);
      if($i++==0){
        $r[0]=preg_replace('/^\xEF\xBB\xBF/','',$r[0]);
        $keys=array_map(fn($x)=>$alias[trim(preg_replace('/[^a-z]+/','_',strtolower($x)),'_')]??null,$r);
        if(in_array('student_no',$keys,true)&&in_array('name',$keys,true)){$map=[];foreach($keys as $c=>$k)if($k)$map[$k]=$c;continue;}
      }
      if(!array_filter($r,fn($x)=>trim($x)!==''))continue;
      $g=fn($k)=>isset($map[$k],$r[$map[$k]])?trim($r[$map[$k]]):'';
      $no=$g('student_no');$nm=$g('name');
      if($no===''||$nm===''){$bad++;continue;}
      $ins->execute([$no,$nm,$g('gender'),$g('track'),$g('strand'),$g('grade'),$g('section')]);
      $ins->rowCount()?$add++:$skip++;
    }
    $pdo->commit();fclose($fh);
    $m="Import finished: $add added, $skip skipped (Student ID already exists), $bad invalid row(s).";
  }
}
if(isset($_POST['add'])){try{$pdo->prepare("INSERT INTO students(student_no,name,gender,track,strand,grade,section) VALUES(?,?,?,?,?,?,?)")->execute([trim($_POST['student_no']),$_POST['name'],$_POST['gender'],$_POST['track'],$_POST['strand'],$_POST['grade'],$_POST['section']]);$m='Student added.';}catch(Exception $e){$m='Error: Student ID already exists.';}}
if(isset($_POST['del'])){auth(['admin']);try{$pdo->prepare("DELETE FROM students WHERE id=?")->execute([$_POST['del']]);$pdo->prepare("DELETE FROM users WHERE student_id=?")->execute([$_POST['del']]);}catch(Exception $e){$m='Cannot delete: student has records.';}}

photo_col($pdo);
if(isset($_POST['rmphoto'])){auth(['admin']);photo_remove($pdo,(int)$_POST['rmphoto']);$m='Photo removed.';}
$s=$pdo->prepare("SELECT * FROM students WHERE name LIKE ? OR student_no LIKE ? ORDER BY name");
$l='%'.($_GET['q']??'').'%';$s->execute([$l,$l]);$rows=$s->fetchAll();

head('Students');ui_css();?>
<div class="pg">

<?php if(!empty($m))echo '<div class="note">'.ic('info').'<span>'.h($m).'</span></div>';?>

<form method="post" class="card">
  <div class="ph"><span class="pico"><?=ic('user')?></span><div><h3>Add student</h3><p>Register a single student and generate their QR code.</p></div></div>
  <div class="fgrid">
    <input name="student_no" placeholder="Student ID" required>
    <input name="name" placeholder="Full name" required>
    <select name="gender"><option>Male</option><option>Female</option></select>
    <input name="track" placeholder="Track">
    <input name="strand" placeholder="Strand">
    <input name="grade" placeholder="Grade">
    <input name="section" placeholder="Section">
  </div>
  <div class="fact"><button name="add" value="1"><?=ic('plus')?>Add Student</button></div>
</form>

<form method="post" enctype="multipart/form-data" class="card">
  <div class="ph"><span class="pico"><?=ic('upload')?></span><div><h3>Import from CSV</h3><p>Add many students at once.</p></div>
    <a class="link" style="margin-left:auto" href="?template=1"><?=ic('download')?>Download CSV template</a></div>
  <div class="importrow">
    <input type="file" name="csv" accept=".csv,text/csv" required>
    <button name="import" value="1"><?=ic('upload')?>Import Students</button>
  </div>
  <p class="hint">Columns: student_no, name, gender, track, strand, grade, section. Required: student_no and name. Existing Student IDs are skipped. In Excel use File &gt; Save As &gt; CSV.</p>
</form>

<form class="bar">
  <div class="sw"><?=ic('search')?><input name="q" placeholder="Search name or Student ID..." value="<?=h($_GET['q']??'')?>"></div>
  <button><?=ic('search')?>Search</button>
  <button type="button" class="ghost" onclick="print()"><?=ic('print')?>Print QR Codes</button>
  <span class="badge"><?=count($rows)?> student<?=count($rows)==1?'':'s'?></span>
</form>

<div class="qrgrid">
<?php if(!$rows):?><div class="empty"><b>No students found</b>Try a different search or add a new student above.</div><?php endif;?>
<?php foreach($rows as $r):?>
  <div class="card qrcard">
    <?=avatar($r)?>
    <div class="qr" data-code="STU:<?=h($r['student_no'])?>"></div>
    <b><?=h($r['name'])?></b>
    <div class="chips">
      <span class="chip id"><?=h($r['student_no'])?></span>
      <?php if($r['grade']!==''):?><span class="chip">G<?=h($r['grade'])?></span><?php endif;?>
      <?php if($r['strand']!==''):?><span class="chip"><?=h($r['strand'])?></span><?php endif;?>
      <?php if($r['section']!==''):?><span class="chip"><?=h($r['section'])?></span><?php endif;?>
    </div>
    <a class="ghost vbtn noprint" href="student_view.php?id=<?=$r['id']?>"><?=ic('user')?><?=$_SESSION['u']['role']=='admin'?'View / Edit':'View'?></a>
    <?php if($_SESSION['u']['role']=='admin'):?>
      <?php if(!empty($r['photo'])):?><form method="post" class="noprint" onsubmit="return confirm('Remove this student photo?')"><button class="ghost sm" name="rmphoto" value="<?=$r['id']?>"><?=ic('camera')?>Remove photo</button></form><?php endif;?>
      <form method="post" class="noprint" onsubmit="return confirm('Delete?')"><button class="danger" name="del" value="<?=$r['id']?>"><?=ic('trash')?>Delete</button></form>
    <?php endif;?>
  </div>
<?php endforeach;?>
</div>

</div>
<?php foot('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>renderQR()</script>');