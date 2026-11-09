<?php
require 'config.php';
auth(['admin','librarian','student']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function chat_response($data,$status=200){
  http_response_code($status);
  echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}

$me=(int)$_SESSION['u']['id'];
$action=(string)($_POST['action']??'');
if($_SERVER['REQUEST_METHOD']!=='POST'||!csrf_token_valid($_POST['csrf_token']??null)){
  chat_response(['error'=>'Your session expired. Refresh the page and try again.'],403);
}

try{
  ensure_chat_schema($pdo);
  if($action==='contacts'){
    $search=trim((string)($_POST['search']??''));
    if(strlen($search)>80)chat_response(['error'=>'Search must be 80 characters or fewer.'],422);
    $like='%'.$search.'%';
    $staffQuery=$pdo->prepare("SELECT id,username,role,NULL AS student_id,username AS display_name
      FROM users
      WHERE id<>? AND role IN ('admin','librarian')
        AND (?='' OR username LIKE ?)
      ORDER BY CASE WHEN role='admin' THEN 0 ELSE 1 END,username
      LIMIT 100");
    $staffQuery->execute([$me,$search,$like]);
    $studentQuery=$pdo->prepare("SELECT u.id,u.username,s.id AS student_id,s.student_no,
        COALESCE(NULLIF(s.name,''),s.student_no) AS display_name
      FROM students s
      LEFT JOIN users u ON u.student_id=s.id AND u.role='student'
      WHERE (u.id IS NULL OR u.id<>?)
        AND (?='' OR s.name LIKE ? OR s.student_no LIKE ? OR u.username LIKE ?)
      ORDER BY display_name");
    $studentQuery->execute([$me,$search,$like,$like,$like]);
    $contacts=[];
    foreach($staffQuery->fetchAll(PDO::FETCH_ASSOC) as $row){
      $contacts[]=['user_id'=>(int)$row['id'],'student_id'=>null,'name'=>(string)$row['display_name'],'role'=>(string)$row['role']];
    }
    foreach($studentQuery->fetchAll(PDO::FETCH_ASSOC) as $row){
      $contacts[]=['user_id'=>$row['id']===null?null:(int)$row['id'],'student_id'=>(int)$row['student_id'],
        'name'=>(string)$row['display_name'],'role'=>'student'];
    }
    chat_response(['contacts'=>$contacts]);
  }

  if($action==='conversations'){
    ensure_student_account_status_schema($pdo);
    $query=$pdo->prepare("SELECT c.id,other.id AS user_id,other.username,other.role,s.name,
      latest.message AS last_message,latest.created_at AS last_at,
      (SELECT COUNT(*) FROM chat_messages unread
       WHERE unread.conversation_id=c.id AND unread.sender_id<>? AND unread.read_at IS NULL) AS unread
      FROM chat_conversations c
      JOIN users other ON other.id=IF(c.user_one=?,c.user_two,c.user_one)
      LEFT JOIN students s ON s.id=other.student_id
      LEFT JOIN chat_messages latest ON latest.id=(SELECT MAX(m.id) FROM chat_messages m WHERE m.conversation_id=c.id)
      WHERE (c.user_one=? OR c.user_two=?) AND (other.role<>'student' OR s.account_active=1)
      ORDER BY latest.id DESC,c.id DESC");
    $query->execute([$me,$me,$me,$me]);
    $conversations=array_map(function($row){
      return ['id'=>(int)$row['id'],'user_id'=>(int)$row['user_id'],
        'name'=>(string)($row['name']?:$row['username']),'role'=>(string)$row['role'],
        'last_message'=>(string)($row['last_message']??''),'last_at'=>(string)($row['last_at']??''),
        'unread'=>(int)$row['unread']];
    },$query->fetchAll(PDO::FETCH_ASSOC));
    chat_response(['conversations'=>$conversations]);
  }

  if($action==='start'){
    ensure_student_account_status_schema($pdo);
    $other=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);
    $studentId=filter_var($_POST['student_id']??null,FILTER_VALIDATE_INT);
    if($studentId){
      $statusQuery=$pdo->prepare("SELECT id,student_no,account_active FROM students WHERE id=?");
      $statusQuery->execute([$studentId]);$student=$statusQuery->fetch(PDO::FETCH_ASSOC);
      if(!$student||(int)$student['account_active']!==1)chat_response(['error'=>'That student account is not active.'],404);
      if(!$other){
        $existing=$pdo->prepare("SELECT id FROM users WHERE student_id=? AND role='student' LIMIT 1");
        $existing->execute([$studentId]);$other=(int)$existing->fetchColumn();
        if(!$other){
          $username=$student['student_no'];
          $collision=$pdo->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
          $collision->execute([$username]);
          if($collision->fetchColumn())chat_response(['error'=>'This student ID is already linked to a different account. Ask the administrator to check the account.'],409);
          $columns=$pdo->query("SHOW COLUMNS FROM users LIKE 'must_change'");
          if(!$columns->fetch())$pdo->exec("ALTER TABLE users ADD COLUMN must_change TINYINT(1) NOT NULL DEFAULT 0");
          $createUser=$pdo->prepare("INSERT INTO users(username,password,role,student_id,must_change) VALUES(?,?, 'student',?,0)");
          $createUser->execute([$username,password_hash($username,PASSWORD_DEFAULT),$studentId]);
          $other=(int)$pdo->lastInsertId();
        }
      }
    }
    if(!$other||$other===$me)chat_response(['error'=>'Choose another valid account.'],422);
    $target=$pdo->prepare("SELECT u.id FROM users u LEFT JOIN students s ON s.id=u.student_id
      WHERE u.id=? AND (u.role<>'student' OR s.account_active=1)");
    $target->execute([$other]);
    if(!$target->fetchColumn())chat_response(['error'=>'That account is unavailable.'],404);
    $one=min($me,$other);$two=max($me,$other);
    $create=$pdo->prepare("INSERT INTO chat_conversations(user_one,user_two,created_at)
      VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $create->execute([$one,$two]);
    $conversationId=(int)$pdo->lastInsertId();
    if(!$conversationId){
      $find=$pdo->prepare("SELECT id FROM chat_conversations WHERE user_one=? AND user_two=?");
      $find->execute([$one,$two]);$conversationId=(int)$find->fetchColumn();
    }
    chat_response(['conversation_id'=>$conversationId]);
  }

  $conversationId=filter_var($_POST['conversation_id']??null,FILTER_VALIDATE_INT);
  if(!$conversationId)chat_response(['error'=>'Choose a conversation first.'],422);
  $member=$pdo->prepare("SELECT id FROM chat_conversations WHERE id=? AND (user_one=? OR user_two=?)");
  $member->execute([$conversationId,$me,$me]);
  if(!$member->fetchColumn())chat_response(['error'=>'Conversation not found.'],404);

  if($action==='send'){
    $text=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',trim((string)($_POST['message']??'')));
    if($text===null||$text===false)chat_response(['error'=>'Message contains invalid text.'],422);
    $text=trim($text);
    $length=function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):strlen($text);
    if($text===''||$length>2000)chat_response(['error'=>'Messages must be 1-2000 characters.'],422);
    $throttle=$pdo->prepare("SELECT created_at FROM chat_messages WHERE sender_id=? ORDER BY id DESC LIMIT 1");
    $throttle->execute([$me]);$lastSent=$throttle->fetchColumn();
    if($lastSent&&strtotime($lastSent)>time()-1)chat_response(['error'=>'Please wait a moment before sending another message.'],429);
    $send=$pdo->prepare("INSERT INTO chat_messages(conversation_id,sender_id,message,created_at) VALUES(?,?,?,NOW())");
    $send->execute([$conversationId,$me,$text]);
    chat_response(['message_id'=>(int)$pdo->lastInsertId()]);
  }

  if($action==='sync'){
    $after=max(0,(int)($_POST['after_id']??0));
    if($after===0){
      $query=$pdo->prepare("SELECT id,sender_id,message,created_at FROM chat_messages
        WHERE conversation_id=? ORDER BY id DESC LIMIT 50");
      $query->execute([$conversationId]);$messages=array_reverse($query->fetchAll(PDO::FETCH_ASSOC));
    }else{
      $query=$pdo->prepare("SELECT id,sender_id,message,created_at FROM chat_messages
        WHERE conversation_id=? AND id>? ORDER BY id ASC LIMIT 100");
      $query->execute([$conversationId,$after]);$messages=$query->fetchAll(PDO::FETCH_ASSOC);
    }
    $mark=$pdo->prepare("UPDATE chat_messages SET read_at=NOW()
      WHERE conversation_id=? AND sender_id<>? AND read_at IS NULL");
    $mark->execute([$conversationId,$me]);
    $unread=$pdo->prepare("SELECT COALESCE(SUM(unread_count),0) FROM (
      SELECT COUNT(*) AS unread_count FROM chat_messages m
      JOIN chat_conversations c ON c.id=m.conversation_id
      WHERE (c.user_one=? OR c.user_two=?) AND m.sender_id<>? AND m.read_at IS NULL
      GROUP BY m.conversation_id) unread_messages");
    $unread->execute([$me,$me,$me]);
    chat_response(['messages'=>array_map(function($row){
      return ['id'=>(int)$row['id'],'sender_id'=>(int)$row['sender_id'],'message'=>$row['message'],'created_at'=>$row['created_at']];
    },$messages),'unread'=>(int)$unread->fetchColumn()]);
  }
  chat_response(['error'=>'Unknown chat action.'],422);
}catch(Throwable $error){
  error_log('Chat API failed: '.$error->getMessage());
  $sqlState=$error instanceof PDOException&&isset($error->errorInfo[0])?(string)$error->errorInfo[0]:'';
  if($sqlState==='42S02'||$sqlState==='42S22'){
    $message='Chat database tables or columns are missing. Run the chat SQL setup in InfinityFree phpMyAdmin.';
  }elseif($sqlState==='42000'||$sqlState==='28000'){
    $message='InfinityFree denied access to the chat database. Check database permissions and the hosting database settings.';
  }else{
    $message='Chat server/database error. Check that the latest messages.php is uploaded and the chat tables exist in InfinityFree.';
  }
  chat_response(['error'=>$message],500);
}
