<?php
session_start();
date_default_timezone_set('Asia/Manila');
const LOAN_DAYS=7, MAX_LOANS=3, SCAN_COOLDOWN=10;
$pdo=new PDO('mysql:host=localhost;dbname=database;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function auth($roles=['admin','librarian']){
  if(empty($_SESSION['u'])){header('Location: login.php');exit;}
  if(!in_array($_SESSION['u']['role'],$roles)){
    if($_SESSION['u']['role']=='student'){header('Location: student.php');exit;}
    http_response_code(403);exit('Forbidden');}
}
function head($title){
  $p=basename($_SERVER['PHP_SELF']);$role=$_SESSION['u']['role']??'';
  $nav=['index.php'=>'Dashboard','students.php'=>'Students','books.php'=>'Books','borrow.php'=>'Borrow','return.php'=>'Return','attendance.php'=>'Time-In / Time-Out'];
  if($role=='student')$nav=['student.php'=>'My Dashboard'];
  echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).' - eSmartlib RHSTI</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"><link rel="stylesheet" href="assets/style.css"></head><body><div class="app"><aside><div class="brand"><i>e</i><span>eSmartlib<small>RHSTI Library</small></span></div><nav>';
  foreach($nav as $f=>$l) echo '<a href="'.$f.'"'.($p==$f?' class="on"':'').'>'.$l.'</a>';
  echo '</nav><div class="who"><b>'.h($_SESSION['u']['username']).'</b><small>'.h(ucfirst($role)).'</small><a href="logout.php">Log out</a></div></aside><main><h1>'.h($title).'</h1>';
}
function foot($scripts=''){echo '</main></div><script src="assets/app.js"></script>'.$scripts.'</body></html>';}