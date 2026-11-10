<?php header('Location: account.php?tab=bookings&booking_status='.urlencode($_GET['status']??'pending'));exit;
require 'config.php';<?php header('Location: account.php?tab=bookings&booking_status='.urlencode($_GET['status']??'pending'));exit;
require 'config.php';
require 'Ui2026.PHP';
auth(['admin']);
ensure_book_request_schema($pdo);

$me=(int)$_SESSION['u']['id'];
$message='';
$messageType='success';

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_token_valid($_POST['csrf_token']??null)){
    $message='Your session expired. Refresh the page and try again.';
    $messageType='error';
  }else{
    $requestId=filter_var($_POST['request_id']??null,FILTER_VALIDATE_INT);
    $action=isset($_POST['approve'])?'approve':(isset($_POST['decline'])?'decline':(isset($_POST['reply'])?'reply':''));
    $reply=trim((string)($_POST['admin_reply']??''));
    $replyLength=function_exists('mb_strlen')?mb_strlen($reply,'UTF-8'):strlen($reply);
    if(!$requestId||$action===''){
      $message='Invalid booking request action.';
      $messageType='error';
    }elseif($replyLength>500){
      $message='Admin reply must be 500 characters or fewer.';
      $messageType='error';
    }elseif($action==='reply'&&$reply===''){
      $message='Write a reply before sending it to the student.';
      $messageType='error';
    }else{
      $pdo->beginTransaction();
      try{
        $requestQuery=$pdo->prepare("SELECT r.id,r.student_id,r.book_id,r.status,b.title,b.available
          FROM book_requests r JOIN books b ON b.id=r.book_id WHERE r.id=? FOR UPDATE");
        $requestQuery->execute([$requestId]);
        $request=$requestQuery->fetch();
        if(!$request){
          $pdo->rollBack();
          $message='Booking request not found.';
          $messageType='error';
        }elseif($action==='reply'){
          $pdo->prepare("UPDATE book_requests SET admin_reply=?,replied_at=NOW(),replied_by=? WHERE id=?")
            ->execute([$reply,$me,$requestId]);
          $pdo->commit();
          $message='Reply sent to the student.';
        }elseif($request['status']!=='pending'){
          $pdo->rollBack();
          $message='This request has already been handled.';
          $messageType='error';
        }elseif($action==='approve'){
          $pdo->prepare("SELECT id FROM books WHERE id=? FOR UPDATE")->execute([(int)$request['book_id']]);
          $held=$pdo->prepare("SELECT COUNT(*) FROM book_requests WHERE book_id=? AND status='approved'");
          $held->execute([(int)$request['book_id']]);
          $borrowed=$pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE student_id=? AND book_id=? AND returned_at IS NULL");
          $borrowed->execute([(int)$request['student_id'],(int)$request['book_id']]);
          if((int)$request['available']-(int)$held->fetchColumn()<1){
            $pdo->rollBack();
            $message='No unreserved copy is available for this booking.';
            $messageType='error';
          }elseif((int)$borrowed->fetchColumn()>0){
            $pdo->rollBack();
            $message='The student already has this book borrowed.';
            $messageType='error';
          }else{
            $response=$reply!==''?$reply:'Your booking is approved. Please visit the library to borrow the book.';
            $pdo->prepare("UPDATE book_requests SET status='approved',admin_reply=?,reviewed_at=NOW(),reviewed_by=?,replied_at=NOW(),replied_by=? WHERE id=?")
              ->execute([$response,$me,$me,$requestId]);
            $pdo->commit();
            $message='Booking approved. The copy is reserved until the student borrows it.';
          }
        }else{
          $response=$reply!==''?$reply:'This booking request was declined by the library admin.';
          $pdo->prepare("UPDATE book_requests SET status='declined',admin_reply=?,reviewed_at=NOW(),reviewed_by=?,replied_at=NOW(),replied_by=? WHERE id=?")
            ->execute([$response,$me,$me,$requestId]);
          $pdo->commit();
          $message='Booking request declined.';
        }
      }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
      }
    }
  }
}

$filter=$_GET['status']??'pending';
$validFilters=['pending','approved','declined','cancelled','fulfilled','all'];
if(!in_array($filter,$validFilters,true))$filter='pending';
$query="SELECT r.id,r.student_id,r.book_id,r.message,r.admin_reply,r.status,r.requested_at,r.reviewed_at,r.replied_at,
  s.name,s.student_no,b.title,b.author,b.accession_no,b.available
  FROM book_requests r JOIN students s ON s.id=r.student_id JOIN books b ON b.id=r.book_id";
$params=[];
if($filter!=='all'){$query.=" WHERE r.status=?";$params[]=$filter;}
$query.=" ORDER BY FIELD(r.status,'pending','approved','declined','cancelled','fulfilled'),r.requested_at DESC LIMIT 200";
$requestsQuery=$pdo->prepare($query);$requestsQuery->execute($params);$requests=$requestsQuery->fetchAll();

$counts=$pdo->query("SELECT status,COUNT(*) AS total FROM book_requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
head('Online book bookings');ui_css();?>
<style>
.pg .request-filter{display:flex;gap:8px;flex-wrap:wrap}
.pg .request-filter a{padding:9px 13px;border:1px solid var(--line);border-radius:12px;color:var(--mute);font-size:13px;font-weight:700;text-decoration:none}
.pg .request-filter a.on{background:var(--leaf);border-color:#b9d3c0;color:var(--gd)}
.pg .request-message{max-width:260px;white-space:pre-wrap;overflow-wrap:anywhere}
.pg .reply-form{display:grid;min-width:220px;gap:7px}
.pg .reply-form textarea{min-height:64px;resize:vertical;padding:10px 12px;border:1px solid var(--line);border-radius:12px;font:inherit}
.pg .request-actions{display:flex;gap:6px;flex-wrap:wrap}
.pg .request-actions button{height:38px;padding:0 12px;font-size:12px}
.pg .request-actions .danger{width:auto}
</style>
<div class="pg">
  <?php if($message!==''):?><div class="note<?=$messageType==='error'?' bad':''?>"><?=ic('info')?><span><?=h($message)?></span></div><?php endif;?>
  <div class="card">
    <div class="ph"><span class="pico"><?=ic('book')?></span><div><h3>Student booking requests</h3><p>Review student messages, reply, and approve or decline bookings.</p></div><span class="badge"><?=count($requests)?> shown</span></div>
    <nav class="request-filter" aria-label="Filter booking requests">
      <?php foreach($validFilters as $status):?>
        <a class="<?=$filter===$status?'on':''?>" href="?status=<?=h($status)?>"><?=h(ucfirst($status))?><?=$status!=='all'?' ('.(int)($counts[$status]??0).')':''?></a>
      <?php endforeach;?>
    </nav>
    <?php if(!$requests):?><p class="help">No booking requests in this view.</p>
    <?php else:?>
      <div class="tw" style="margin-top:14px"><table class="rt">
        <tr><th>Student</th><th>Book</th><th>Message</th><th>Status</th><th>Requested</th><th>Reply / action</th></tr>
        <?php foreach($requests as $request):
          $statusClass=['approved'=>'ok','fulfilled'=>'ok','pending'=>'warn','declined'=>'late','cancelled'=>'late'][$request['status']]??'';
        ?>
          <tr>
            <td data-label="Student"><?=h($request['name'])?><small class="muted" style="display:block"><?=h($request['student_no'])?></small></td>
            <td data-label="Book"><?=h($request['title'])?><small class="muted" style="display:block"><?=h($request['author']??'')?> · <?=h($request['accession_no'])?></small></td>
            <td data-label="Message"><div class="request-message"><?=h($request['message']?:'No message')?></div><?php if($request['admin_reply']):?><small class="muted" style="display:block;margin-top:8px">Previous reply: <?=h($request['admin_reply'])?></small><?php endif;?></td>
            <td data-label="Status"><span class="pill <?=$statusClass?>"><?=h(ucfirst($request['status']))?></span></td>
            <td data-label="Requested"><?=h(date('M j, Y · h:i A',strtotime($request['requested_at'])))?></td>
            <td data-label="Reply / action">
              <form method="post" class="reply-form">
                <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
                <input type="hidden" name="request_id" value="<?=(int)$request['id']?>">
                <textarea name="admin_reply" maxlength="500" placeholder="Reply to student (optional)"><?=h($request['admin_reply']??'')?></textarea>
                <div class="request-actions">
                  <button name="reply" value="1" class="ghost">Send reply</button>
                  <?php if($request['status']==='pending'):?>
                    <button name="approve" value="1">Approve</button>
                    <button name="decline" value="1" class="danger">Decline</button>
                  <?php endif;?>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach;?>
      </table></div>
    <?php endif;?>
  </div>
</div>
<?php foot('');
require 'Ui2026.PHP';
auth(['admin']);
ensure_book_request_schema($pdo);

$me=(int)$_SESSION['u']['id'];
$message='';
$messageType='success';

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!csrf_token_valid($_POST['csrf_token']??null)){
    $message='Your session expired. Refresh the page and try again.';
    $messageType='error';
  }else{
    $requestId=filter_var($_POST['request_id']??null,FILTER_VALIDATE_INT);
    $action=isset($_POST['approve'])?'approve':(isset($_POST['decline'])?'decline':(isset($_POST['reply'])?'reply':''));
    $reply=trim((string)($_POST['admin_reply']??''));
    $replyLength=function_exists('mb_strlen')?mb_strlen($reply,'UTF-8'):strlen($reply);
    if(!$requestId||$action===''){
      $message='Invalid booking request action.';
      $messageType='error';
    }elseif($replyLength>500){
      $message='Admin reply must be 500 characters or fewer.';
      $messageType='error';
    }elseif($action==='reply'&&$reply===''){
      $message='Write a reply before sending it to the student.';
      $messageType='error';
    }else{
      $pdo->beginTransaction();
      try{
        $requestQuery=$pdo->prepare("SELECT r.id,r.student_id,r.book_id,r.status,b.title,b.available
          FROM book_requests r JOIN books b ON b.id=r.book_id WHERE r.id=? FOR UPDATE");
        $requestQuery->execute([$requestId]);
        $request=$requestQuery->fetch();
        if(!$request){
          $pdo->rollBack();
          $message='Booking request not found.';
          $messageType='error';
        }elseif($action==='reply'){
          $pdo->prepare("UPDATE book_requests SET admin_reply=?,replied_at=NOW(),replied_by=? WHERE id=?")
            ->execute([$reply,$me,$requestId]);
          $pdo->commit();
          $message='Reply sent to the student.';
        }elseif($request['status']!=='pending'){
          $pdo->rollBack();
          $message='This request has already been handled.';
          $messageType='error';
        }elseif($action==='approve'){
          $pdo->prepare("SELECT id FROM books WHERE id=? FOR UPDATE")->execute([(int)$request['book_id']]);
          $held=$pdo->prepare("SELECT COUNT(*) FROM book_requests WHERE book_id=? AND status='approved'");
          $held->execute([(int)$request['book_id']]);
          $borrowed=$pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE student_id=? AND book_id=? AND returned_at IS NULL");
          $borrowed->execute([(int)$request['student_id'],(int)$request['book_id']]);
          if((int)$request['available']-(int)$held->fetchColumn()<1){
            $pdo->rollBack();
            $message='No unreserved copy is available for this booking.';
            $messageType='error';
          }elseif((int)$borrowed->fetchColumn()>0){
            $pdo->rollBack();
            $message='The student already has this book borrowed.';
            $messageType='error';
          }else{
            $response=$reply!==''?$reply:'Your booking is approved. Please visit the library to borrow the book.';
            $pdo->prepare("UPDATE book_requests SET status='approved',admin_reply=?,reviewed_at=NOW(),reviewed_by=?,replied_at=NOW(),replied_by=? WHERE id=?")
              ->execute([$response,$me,$me,$requestId]);
            $pdo->commit();
            $message='Booking approved. The copy is reserved until the student borrows it.';
          }
        }else{
          $response=$reply!==''?$reply:'This booking request was declined by the library admin.';
          $pdo->prepare("UPDATE book_requests SET status='declined',admin_reply=?,reviewed_at=NOW(),reviewed_by=?,replied_at=NOW(),replied_by=? WHERE id=?")
            ->execute([$response,$me,$me,$requestId]);
          $pdo->commit();
          $message='Booking request declined.';
        }
      }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
      }
    }
  }
}

$filter=$_GET['status']??'pending';
$validFilters=['pending','approved','declined','cancelled','fulfilled','all'];
if(!in_array($filter,$validFilters,true))$filter='pending';
$query="SELECT r.id,r.student_id,r.book_id,r.message,r.admin_reply,r.status,r.requested_at,r.reviewed_at,r.replied_at,
  s.name,s.student_no,b.title,b.author,b.accession_no,b.available
  FROM book_requests r JOIN students s ON s.id=r.student_id JOIN books b ON b.id=r.book_id";
$params=[];
if($filter!=='all'){$query.=" WHERE r.status=?";$params[]=$filter;}
$query.=" ORDER BY FIELD(r.status,'pending','approved','declined','cancelled','fulfilled'),r.requested_at DESC LIMIT 200";
$requestsQuery=$pdo->prepare($query);$requestsQuery->execute($params);$requests=$requestsQuery->fetchAll();

$counts=$pdo->query("SELECT status,COUNT(*) AS total FROM book_requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
head('Online book bookings');ui_css();?>
<style>
.pg .request-filter{display:flex;gap:8px;flex-wrap:wrap}
.pg .request-filter a{padding:9px 13px;border:1px solid var(--line);border-radius:12px;color:var(--mute);font-size:13px;font-weight:700;text-decoration:none}
.pg .request-filter a.on{background:var(--leaf);border-color:#b9d3c0;color:var(--gd)}
.pg .request-message{max-width:260px;white-space:pre-wrap;overflow-wrap:anywhere}
.pg .reply-form{display:grid;min-width:220px;gap:7px}
.pg .reply-form textarea{min-height:64px;resize:vertical;padding:10px 12px;border:1px solid var(--line);border-radius:12px;font:inherit}
.pg .request-actions{display:flex;gap:6px;flex-wrap:wrap}
.pg .request-actions button{height:38px;padding:0 12px;font-size:12px}
.pg .request-actions .danger{width:auto}
</style>
<div class="pg">
  <?php if($message!==''):?><div class="note<?=$messageType==='error'?' bad':''?>"><?=ic('info')?><span><?=h($message)?></span></div><?php endif;?>
  <div class="card">
    <div class="ph"><span class="pico"><?=ic('book')?></span><div><h3>Student booking requests</h3><p>Review student messages, reply, and approve or decline bookings.</p></div><span class="badge"><?=count($requests)?> shown</span></div>
    <nav class="request-filter" aria-label="Filter booking requests">
      <?php foreach($validFilters as $status):?>
        <a class="<?=$filter===$status?'on':''?>" href="?status=<?=h($status)?>"><?=h(ucfirst($status))?><?=$status!=='all'?' ('.(int)($counts[$status]??0).')':''?></a>
      <?php endforeach;?>
    </nav>
    <?php if(!$requests):?><p class="help">No booking requests in this view.</p>
    <?php else:?>
      <div class="tw" style="margin-top:14px"><table class="rt">
        <tr><th>Student</th><th>Book</th><th>Message</th><th>Status</th><th>Requested</th><th>Reply / action</th></tr>
        <?php foreach($requests as $request):
          $statusClass=['approved'=>'ok','fulfilled'=>'ok','pending'=>'warn','declined'=>'late','cancelled'=>'late'][$request['status']]??'';
        ?>
          <tr>
            <td data-label="Student"><?=h($request['name'])?><small class="muted" style="display:block"><?=h($request['student_no'])?></small></td>
            <td data-label="Book"><?=h($request['title'])?><small class="muted" style="display:block"><?=h($request['author']??'')?> · <?=h($request['accession_no'])?></small></td>
            <td data-label="Message"><div class="request-message"><?=h($request['message']?:'No message')?></div><?php if($request['admin_reply']):?><small class="muted" style="display:block;margin-top:8px">Previous reply: <?=h($request['admin_reply'])?></small><?php endif;?></td>
            <td data-label="Status"><span class="pill <?=$statusClass?>"><?=h(ucfirst($request['status']))?></span></td>
            <td data-label="Requested"><?=h(date('M j, Y · h:i A',strtotime($request['requested_at'])))?></td>
            <td data-label="Reply / action">
              <form method="post" class="reply-form">
                <input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>">
                <input type="hidden" name="request_id" value="<?=(int)$request['id']?>">
                <textarea name="admin_reply" maxlength="500" placeholder="Reply to student (optional)"><?=h($request['admin_reply']??'')?></textarea>
                <div class="request-actions">
                  <button name="reply" value="1" class="ghost">Send reply</button>
                  <?php if($request['status']==='pending'):?>
                    <button name="approve" value="1">Approve</button>
                    <button name="decline" value="1" class="danger">Decline</button>
                  <?php endif;?>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach;?>
      </table></div>
    <?php endif;?>
  </div>
</div>
<?php foot('');
