  <?php require 'config.php';require 'Ui2026.PHP';auth();
  ensure_student_account_status_schema($pdo);
  if(isset($_GET['template'])){header('Content-Type: text/csv');header('Content-Disposition: attachment; filename=students_template.csv');echo "student_no,name,gender,track,STRAND/ COURSE,grade,section\n2024-0001,Juan Dela Cruz,Male,Academic,STEM,11,A\n";exit;}
  if(isset($_POST['import'])){
    $f=$_FILES['csv']['tmp_name']??'';
    if(!$f||$_FILES['csv']['error']){$m='Choose a CSV file first.';}
    else{
      $fh=fopen($f,'r');$first=preg_replace('/^\xEF\xBB\xBF/','',fgets($fh));rewind($fh);
      $d=substr_count($first,';')>substr_count($first,',')?';':',';
      $map=['student_no'=>0,'name'=>1,'gender'=>2,'track'=>3,'strand'=>4,'grade'=>5,'section'=>6];
      $alias=['student_id'=>'student_no','student_no'=>'student_no','studentno'=>'student_no','id'=>'student_no','lrn'=>'student_no','name'=>'name','full_name'=>'name','student_name'=>'name','gender'=>'gender','sex'=>'gender','track'=>'track','strand'=>'strand','strand_course'=>'strand','grade'=>'grade','grade_level'=>'grade','section'=>'section'];
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
  if(isset($_POST['toggle_account'])){
    auth(['admin']);
    $studentId=filter_var($_POST['toggle_account'],FILTER_VALIDATE_INT);
    $active=isset($_POST['activate_account'])?1:0;
    if($studentId===false||$studentId<1){$m='Invalid student account.';}
    else{
      $account=$pdo->prepare("SELECT id FROM students WHERE id=?");$account->execute([$studentId]);
      if(!$account->fetch()){$m='Student not found.';}
      else{
        $pdo->prepare("UPDATE students SET account_active=? WHERE id=?")->execute([$active,$studentId]);
        $m=$active?'Student account activated.':'Student account deactivated.';
      }
    }
  }

  photo_col($pdo);
  if(isset($_POST['bulk_del'])){
    auth(['admin']);
    $selectedIds=isset($_POST['delete_ids'])&&is_array($_POST['delete_ids'])?$_POST['delete_ids']:[];
    $studentIds=[];
    foreach($selectedIds as $selectedId){
      $studentId=filter_var($selectedId,FILTER_VALIDATE_INT);
      if($studentId!==false&&$studentId>0)$studentIds[$studentId]=$studentId;
    }
    if(!$studentIds){$m='Select at least one student to delete.';}
    else{
      $deletedCount=0;$skippedCount=0;
      $getPhoto=$pdo->prepare("SELECT photo FROM students WHERE id=?");
      $deleteUsers=$pdo->prepare("DELETE FROM users WHERE student_id=?");
      $deleteStudent=$pdo->prepare("DELETE FROM students WHERE id=?");
      foreach($studentIds as $studentId){
        try{
          $pdo->beginTransaction();
          $getPhoto->execute([$studentId]);$photo=$getPhoto->fetchColumn();
          $deleteUsers->execute([$studentId]);
          $deleteStudent->execute([$studentId]);
          if($deleteStudent->rowCount()!==1){
            $pdo->rollBack();$skippedCount++;continue;
          }
          $pdo->commit();
          if($photo)photo_delete_file($photo);
          $deletedCount++;
        }catch(Exception $e){
          if($pdo->inTransaction())$pdo->rollBack();
          error_log('Bulk student deletion failed for student '.$studentId.': '.$e->getMessage());
          $skippedCount++;
        }
      }
      $m='Deleted '.$deletedCount.' student'.($deletedCount===1?'':'s').'.';
      if($skippedCount)$m.=' '.$skippedCount.' could not be deleted because they have borrow, attendance, or other linked records.';
    }
  }
  if(isset($_POST['rmphoto'])){auth(['admin']);photo_remove($pdo,(int)$_POST['rmphoto']);$m='Photo removed.';}
  $sections=$pdo->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL AND TRIM(section)<>'' ORDER BY section")->fetchAll(PDO::FETCH_COLUMN);
  $q=is_string($_GET['q']??null)?trim($_GET['q']):'';
  $section=is_string($_GET['section']??null)?$_GET['section']:'';
  if(!in_array($section,$sections,true))$section='';
  $where=[];$params=[];
  if($q!==''){$where[]='(name LIKE ? OR student_no LIKE ?)';$like='%'.$q.'%';$params[]=$like;$params[]=$like;}
  if($section!==''){$where[]='section = ?';$params[]=$section;}
  $sql='SELECT * FROM students'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY name';
  $s=$pdo->prepare($sql);$s->execute($params);$rows=$s->fetchAll();

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
    <p class="hint">Columns: student_no, name, gender, track, STRAND/ COURSE, grade, section. The strand column can also be named “strand”. Required: student_no and name. Existing Student IDs are skipped. In Excel use File &gt; Save As &gt; CSV.</p>
  </form>

  <form class="bar">
    <div class="sw"><?=ic('search')?><input name="q" placeholder="Search name or Student ID..." value="<?=h($q)?>"></div>
    <label class="section-filter"><span>Section</span><select name="section" aria-label="Filter students by section" onchange="this.form.submit()">
      <option value="">All sections</option>
      <?php foreach($sections as $sectionOption):?><option value="<?=h($sectionOption)?>" <?=$section===$sectionOption?'selected':''?>><?=h($sectionOption)?></option><?php endforeach;?>
    </select></label>
    <button><?=ic('search')?>Search</button>
    <button type="button" class="ghost" onclick="print()"><?=ic('print')?>Print QR Codes</button>
    <span class="badge"><?=count($rows)?> student<?=count($rows)==1?'':'s'?><?=($section!=='')?' in '.h($section):''?></span>
  </form>

  <?php if($_SESSION['u']['role']==='admin'):?>
  <div class="bulk-actions noprint">
    <label><input type="checkbox" id="select-all-students"> Select all students</label>
    <span id="selected-student-count">0 selected</span>
    <form method="post" id="bulk-delete-students-form" onsubmit="return confirmSelectedStudentDeletion()">
      <button class="danger" name="bulk_del" value="1"><?=ic('trash')?>Delete selected</button>
    </form>
  </div>
  <?php endif;?>

  <div class="qrgrid">
  <?php if(!$rows):?><div class="empty"><b>No students found</b>Try another name, Student ID, or section filter.</div><?php endif;?>
  <?php foreach($rows as $r):?>
    <div class="card qrcard">
      <?php if($_SESSION['u']['role']==='admin'):?>
        <label class="student-select-label noprint"><input class="student-select" type="checkbox" name="delete_ids[]" value="<?=$r['id']?>" form="bulk-delete-students-form"><span>Select</span></label>
      <?php endif;?>
      <?=avatar($r)?>
      <div class="qr" data-code="STU:<?=h($r['student_no'])?>"></div>
      <b><?=h($r['name'])?></b>
      <div class="chips">
        <span class="chip id"><?=h($r['student_no'])?></span>
        <?php if($r['grade']!==''):?><span class="chip">G<?=h($r['grade'])?></span><?php endif;?>
        <?php if($r['strand']!==''):?><span class="chip"><?=h($r['strand'])?></span><?php endif;?>
        <?php if($r['section']!==''):?><span class="chip"><?=h($r['section'])?></span><?php endif;?>
      </div>
      <a class="ghost vbtn noprint" href="Student_view.php?id=<?=$r['id']?>"><?=ic('user')?><?=$_SESSION['u']['role']=='admin'?'View / Edit':'View'?></a>
      <?php if($_SESSION['u']['role']=='admin'):?>
        <span class="account-status <?=$r['account_active']?'active':'inactive'?>"><?=$r['account_active']?'Account active':'Account deactivated'?></span>
        <form method="post" class="noprint" onsubmit="return confirm('<?=$r['account_active']?'Deactivate':'Activate'?> this student account?')">
          <input type="hidden" name="toggle_account" value="<?=$r['id']?>">
          <?php if($r['account_active']):?>
            <button class="ghost sm" name="deactivate_account" value="1">Deactivate account</button>
          <?php else:?>
            <button class="ghost sm" name="activate_account" value="1">Activate account</button>
          <?php endif;?>
        </form>
        <?php if(!empty($r['photo'])):?><form method="post" class="noprint" onsubmit="return confirm('Remove this student photo?')"><button class="ghost sm" name="rmphoto" value="<?=$r['id']?>"><?=ic('camera')?>Remove photo</button></form><?php endif;?>
        <form method="post" class="noprint" onsubmit="return confirm('Delete?')"><button class="danger" name="del" value="<?=$r['id']?>"><?=ic('trash')?>Delete</button></form>
      <?php endif;?>
    </div>
  <?php endforeach;?>
  </div>

  </div>
  <style>
  .pg .bulk-actions{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:12px 16px;border:1px solid var(--line);border-radius:14px;background:var(--card)}
  .pg .bulk-actions>label{display:flex;align-items:center;gap:8px;font-weight:700}
  .pg .bulk-actions input[type=checkbox]{width:18px;height:18px;margin:0;accent-color:var(--g)}
  .pg .bulk-actions form{margin-left:auto}
  .pg .bulk-actions button{background:var(--redbg);color:var(--red);box-shadow:none}
  .pg .bulk-actions button:hover{background:#fbd3cf}
  .pg #selected-student-count{color:var(--mute);font-size:13px}
  .pg .qrcard .student-select-label{position:absolute;top:12px;left:12px;display:flex;align-items:center;gap:5px;color:var(--mute);font-size:12px;font-weight:700}
  .pg .qrcard .student-select{width:18px;height:18px;margin:0;accent-color:var(--g)}
  .pg .account-status{padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700}
  .pg .account-status.active{background:#d1fae5;color:#065f46}
  .pg .account-status.inactive{background:#ffe4e6;color:#be123c}
  </style>
  <?php foot('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>
  renderQR();
  var studentChecks=document.querySelectorAll(".student-select");
  var selectAllStudents=document.getElementById("select-all-students");
  var selectedStudentCount=document.getElementById("selected-student-count");
  function updateSelectedStudentCount(){
    var selected=document.querySelectorAll(".student-select:checked").length;
    if(selectedStudentCount)selectedStudentCount.textContent=selected+" selected";
    if(selectAllStudents)selectAllStudents.checked=studentChecks.length>0&&selected===studentChecks.length;
  }
  if(selectAllStudents)selectAllStudents.addEventListener("change",function(){
    studentChecks.forEach(function(box){box.checked=selectAllStudents.checked});
    updateSelectedStudentCount();
  });
  studentChecks.forEach(function(box){box.addEventListener("change",updateSelectedStudentCount)});
  function confirmSelectedStudentDeletion(){
    var selected=document.querySelectorAll(".student-select:checked").length;
    if(!selected){alert("Select at least one student first.");return false}
    return confirm("Delete "+selected+" selected student"+(selected===1?"":"s")+"? Students with borrow or attendance records cannot be deleted.");
  }
  </script>');