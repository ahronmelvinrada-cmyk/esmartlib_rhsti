<?php 
      $is_local = in_array(strtolower($_SERVER['HTTP_HOST'] ?? ''), ['localhost', '127.0.0.1'], true);
      define('DEBUG', $is_local);
      ini_set('display_errors',DEBUG?'1':'0');
      session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
      session_start();
      date_default_timezone_set('Asia/Manila');
      
      // Keep local XAMPP defaults separate from hosting credentials.
      if ($is_local) {
          $db_host = 'localhost';
          $db_name = 'database';
          $db_user = 'root';
          $db_pass = '';
      } else {
          $hosting_config = __DIR__ . '/hosting-config.php';
          $settings = is_file($hosting_config) ? require $hosting_config : [];
          if (!is_array($settings)) {
              exit('hosting-config.php must return an array of database settings.');
          }

          $db_host = $settings['host'] ?? (getenv('DB_HOST') ?: '');
          $db_name = $settings['name'] ?? (getenv('DB_NAME') ?: '');
          $db_user = $settings['user'] ?? (getenv('DB_USER') ?: '');
          $db_pass = $settings['pass'] ?? (getenv('DB_PASS') ?: '');

          if ($db_host === '' || $db_name === '' || $db_user === '') {
              exit('Create hosting-config.php from hosting-config.example.php and enter the database values from your hosting control panel.');
          }
          if ($db_pass === '' || $db_pass === 'ENTER_YOUR_NEW_MYSQL_PASSWORD_HERE') {
              exit('Enter your current InfinityFree MySQL password in hosting-config.php before connecting.');
          }
      }
      const LOAN_DAYS=7, MAX_LOANS=3, SCAN_COOLDOWN=10;
      
      try {
          $pdo=new PDO('mysql:host='.$db_host.';dbname='.$db_name.';charset=utf8mb4',$db_user,$db_pass,[
              PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
              PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
          ]);
      } catch(Exception $e) {
          error_log('Database connection failed: '.$e->getMessage());
          exit(DEBUG?$e->getMessage():'Could not connect to the database. Check the MySQL host, database name, username, password, and server error log.');
      }
      
      function h($s){return htmlspecialchars((string)$s,ENT_QUOTES);}

      function ensure_user_email_schema($pdo){
        static $done=false;
        if($done)return;
        $column=$pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
        if(!$column||!$column->fetch()){
          $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(254) DEFAULT NULL");
        }
        $done=true;
      }

      function ensure_recovery_schema($pdo){
        static $done=false;
        if($done)return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_codes (
          user_id INT NOT NULL PRIMARY KEY,
          code_hash VARCHAR(255) DEFAULT NULL,
          requested_at DATETIME NOT NULL,
          expires_at DATETIME DEFAULT NULL,
          attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
          approved_by INT DEFAULT NULL,
          approved_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $columns=$pdo->query("SHOW COLUMNS FROM password_reset_codes");
        $columnMap=[];
        foreach($columns as $column)$columnMap[$column['Field']]=$column;
        if(($columnMap['code_hash']['Null']??'')!=='YES'){
          $pdo->exec("ALTER TABLE password_reset_codes MODIFY code_hash VARCHAR(255) DEFAULT NULL");
        }
        if(($columnMap['expires_at']['Null']??'')!=='YES'){
          $pdo->exec("ALTER TABLE password_reset_codes MODIFY expires_at DATETIME DEFAULT NULL");
        }
        if(!isset($columnMap['approved_by'])){
          $pdo->exec("ALTER TABLE password_reset_codes ADD COLUMN approved_by INT DEFAULT NULL");
        }
        if(!isset($columnMap['approved_at'])){
          $pdo->exec("ALTER TABLE password_reset_codes ADD COLUMN approved_at DATETIME DEFAULT NULL");
        }
        $done=true;
      }

      function ensure_book_request_schema($pdo){
        static $done=false;
        if($done)return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS book_requests (
          id INT AUTO_INCREMENT PRIMARY KEY,
          student_id INT NOT NULL,
          book_id INT NOT NULL,
          message VARCHAR(500) NOT NULL DEFAULT '',
          admin_reply VARCHAR(500) DEFAULT NULL,
          status ENUM('pending','approved','declined','cancelled','fulfilled') NOT NULL DEFAULT 'pending',
          requested_at DATETIME NOT NULL,
          reviewed_at DATETIME DEFAULT NULL,
          reviewed_by INT DEFAULT NULL,
          replied_at DATETIME DEFAULT NULL,
          replied_by INT DEFAULT NULL,
          KEY k_student_status (student_id,status),
          KEY k_book_status (book_id,status),
          KEY k_requested_at (requested_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done=true;
      }

      function ensure_chat_schema($pdo){
        static $done=false;
        if($done)return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_conversations (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_one INT NOT NULL,
          user_two INT NOT NULL,
          created_at DATETIME NOT NULL,
          UNIQUE KEY uq_chat_pair (user_one,user_two)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
          id BIGINT AUTO_INCREMENT PRIMARY KEY,
          conversation_id INT NOT NULL,
          sender_id INT NOT NULL,
          message TEXT NOT NULL,
          created_at DATETIME NOT NULL,
          read_at DATETIME DEFAULT NULL,
          KEY k_chat_messages (conversation_id,id),
          KEY k_chat_unread (conversation_id,sender_id,read_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done=true;
      }

      function csrf_token(){
        if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
      }

      function csrf_token_valid($value){
        return is_string($value)&&isset($_SESSION['csrf_token'])&&hash_equals($_SESSION['csrf_token'],$value);
      }

      function pwa_head_assets(){
        return '<link rel="manifest" href="manifest.json"><meta name="theme-color" content="#0f3d26"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><link rel="apple-touch-icon" href="assets/app-icon-180.png"><link rel="icon" href="assets/app-icon.svg" type="image/svg+xml"><script>window.addEventListener("load",function(){if("serviceWorker" in navigator&&window.isSecureContext){navigator.serviceWorker.register("sw.js").catch(function(error){console.error("Service worker registration failed:",error);});}});</script>';
      }

      function ensure_student_account_status_schema($pdo){
        static $done=false;
        if($done)return;
        $column=$pdo->query("SHOW COLUMNS FROM students LIKE 'account_active'");
        if(!$column||!$column->fetch()){
          $pdo->exec("ALTER TABLE students ADD COLUMN account_active TINYINT(1) NOT NULL DEFAULT 1");
        }
        $done=true;
      }

      function ensure_teacher_schema($pdo){
        static $done=false;
        if($done)return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS teachers (
          id INT AUTO_INCREMENT PRIMARY KEY,
          teacher_no VARCHAR(30) NOT NULL UNIQUE,
          name VARCHAR(120) NOT NULL,
          photo VARCHAR(255) DEFAULT NULL,
          account_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $teacherColumns=$pdo->query("SHOW COLUMNS FROM teachers LIKE 'photo'");
        if(!$teacherColumns||!$teacherColumns->fetch())$pdo->exec("ALTER TABLE teachers ADD COLUMN photo VARCHAR(255) DEFAULT NULL");
        $userColumns=$pdo->query("SHOW COLUMNS FROM users");
        $userMap=[];
        foreach($userColumns as $column)$userMap[$column['Field']]=$column;
        if(!isset($userMap['teacher_id']))$pdo->exec("ALTER TABLE users ADD COLUMN teacher_id INT DEFAULT NULL");
        if(strpos((string)($userMap['role']['Type']??''),"'teacher'")===false){
          $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','librarian','student','teacher') DEFAULT 'librarian'");
        }
        $borrowColumns=$pdo->query("SHOW COLUMNS FROM borrowings");
        $borrowMap=[];
        foreach($borrowColumns as $column)$borrowMap[$column['Field']]=$column;
        if(!isset($borrowMap['teacher_id']))$pdo->exec("ALTER TABLE borrowings ADD COLUMN teacher_id INT DEFAULT NULL AFTER student_id");
        if(($borrowMap['student_id']['Null']??'')!=='YES')$pdo->exec("ALTER TABLE borrowings MODIFY student_id INT DEFAULT NULL");
        if(($borrowMap['due_date']['Null']??'')!=='YES')$pdo->exec("ALTER TABLE borrowings MODIFY due_date DATE DEFAULT NULL");
        $done=true;
      }
      
      function auth($roles=['admin','librarian']){
        if(empty($_SESSION['u'])){header('Location: login.php');exit;}
        if(($_SESSION['u']['role']??'')==='student'){
          ensure_student_account_status_schema($GLOBALS['pdo']);
          $status=$GLOBALS['pdo']->prepare("SELECT account_active FROM students WHERE id=?");
          $status->execute([(int)($_SESSION['u']['student_id']??0)]);
          if((int)$status->fetchColumn()!==1){
            session_unset();session_destroy();
            header('Location: login.php?deactivated=1');exit;
          }
        }elseif(($_SESSION['u']['role']??'')==='teacher'){
          ensure_teacher_schema($GLOBALS['pdo']);
          $status=$GLOBALS['pdo']->prepare("SELECT account_active FROM teachers WHERE id=?");
          $status->execute([(int)($_SESSION['u']['teacher_id']??0)]);
          if((int)$status->fetchColumn()!==1){
            session_unset();session_destroy();
            header('Location: login.php?deactivated=1');exit;
          }
        }
        if(!in_array($_SESSION['u']['role'],$roles)){
          if($_SESSION['u']['role']=='student'){header('Location: student.php');exit;}
          if($_SESSION['u']['role']=='teacher'){header('Location: teacher.php');exit;}
          http_response_code(403);exit('Forbidden');
        }
      }
      
      function head($title){
        $p=basename($_SERVER['PHP_SELF']);$role=$_SESSION['u']['role']??'';
        $notificationCount=0;
        if($role==='admin'){
          ensure_recovery_schema($GLOBALS['pdo']);
          ensure_book_request_schema($GLOBALS['pdo']);
          $notificationCount=(int)$GLOBALS['pdo']->query("SELECT
            (SELECT COUNT(*) FROM password_reset_codes WHERE code_hash IS NULL AND requested_at>=DATE_SUB(NOW(),INTERVAL 1 DAY))
            +(SELECT COUNT(*) FROM book_requests WHERE status='pending')")->fetchColumn();
        }
        $libraryName=function_exists('setting')?setting($GLOBALS['pdo'],'library_name','eSmartlib'):'eSmartlib';
        $schoolName=function_exists('setting')?setting($GLOBALS['pdo'],'school_name','RHSTI Library'):'RHSTI Library';
        $logoPath=function_exists('setting')?setting($GLOBALS['pdo'],'library_logo','assets/rhsti-school-logo.jpg'):'assets/rhsti-school-logo.jpg';
        $logoAllowed=$logoPath==='assets/rhsti-school-logo.jpg'||(strpos($logoPath,'uploads/library/')===0&&strpos($logoPath,'..')===false);
        $logoFile=$logoAllowed&&is_file(__DIR__.'/'.$logoPath)?$logoPath:'';
        $brandMark=$logoFile?'<img src="'.h($logoFile).'?v='.filemtime(__DIR__.'/'.$logoFile).'" alt="" style="width:42px;height:42px;object-fit:contain;flex:none;border-radius:12px;background:#fff;padding:3px">':'<i>e</i>';
        $I=['grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'dashboard'=>'<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/>',
        'users'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'book'=>'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'id-card'=>'<rect x="3" y="5" width="18" height="15" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 17a3 3 0 0 1 6 0m3-6h3m-3 4h3M9 5V3h6v2"/>',
        'books'=>'<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H10v17H5.5A1.5 1.5 0 0 1 4 18.5zM10 5h4.5A1.5 1.5 0 0 1 16 6.5v13a1.5 1.5 0 0 1-1.5 1.5H10m6-14h2.5A1.5 1.5 0 0 1 20 8.5v10a1.5 1.5 0 0 1-1.5 1.5H16"/>',
        'out'=>'<path d="M7 17 17 7M7 7h10v10"/>','in'=>'<path d="M17 7 7 17M17 17H7V7"/>','clock'=>'<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'history'=>'<path d="M3 12a9 9 0 1 0 2.64-6.36L3 8"/><path d="M3 3v5h5m4-1v5l3 2"/>',
        'send'=>'<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'clipboard-list'=>'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5M9 10h6m-6 4h6m-6 4h3"/>',
        'settings'=>'<path d="M9.6 2h4.8l.7 3.1a7.4 7.4 0 0 1 1.8 1l2.9-1.2 2.4 4.2-2.3 2.1v2l2.3 2.1-2.4 4.2-2.9-1.2a7.4 7.4 0 0 1-1.8 1l-.7 3.1H9.6l-.7-3.1a7.4 7.4 0 0 1-1.8-1l-2.9 1.2-2.4-4.2 2.3-2.1v-2L1.8 9.1l2.4-4.2 2.9 1.2a7.4 7.4 0 0 1 1.8-1L9.6 2Z"/><circle cx="12" cy="12" r="3.2"/>',
        'user-circle'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="9" r="3"/><path d="M6.5 19a5.5 5.5 0 0 1 11 0"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'];
        
        $nav=['index.php'=>['Dashboard','dashboard'],'students.php'=>['Students','users']];
        if($role==='admin'||$role==='librarian')$nav['teachers.php']=['Teachers','users'];
        $nav+=['books.php'=>['Books','books'],'borrow.php'=>['Borrow','out'],'return.php'=>['Return','in'],'attendance.php'=>['Attendance','clock'],'history.php'=>['History','history'],'account.php'=>['My Account','user-circle']];
        if($role==='admin')$nav['account.php']=['Account & Requests','send'];
        if($role=='student'){
          $nav=$p==='student.php'
            ? ['#dashboard'=>['My Dashboard','dashboard'],'#pass'=>['My Pass','id-card'],'#booking'=>['Booking','send'],'#borrowed'=>['My Borrowed','books'],'#history'=>['My History','history'],'#attendance'=>['Attendance','clock'],'account.php'=>['My Profile','user-circle']]
            : ['student.php'=>['My Dashboard','dashboard'],'account.php'=>['My Profile','user-circle']];
        }
        if($role==='teacher'){
          $nav=$p==='teacher.php'
            ? ['#dashboard'=>['My Dashboard','dashboard'],'#pass'=>['Teacher Pass','id-card'],'#borrowed'=>['My Borrowed','books'],'account.php'=>['My Account','user-circle']]
            : ['teacher.php'=>['My Dashboard','dashboard'],'account.php'=>['My Account','user-circle']];
        }

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.h($title).' - '.h($libraryName).' | '.h($schoolName).'</title>'.pwa_head_assets().'<script>try{var t=localStorage.getItem("theme");if(t)document.documentElement.dataset.theme=t}catch(e){}</script><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"><link rel="stylesheet" href="assets/style.css?v='.filemtime(__DIR__.'/assets/style.css').'"></head><body><div class="app'.($role==='student'?' student-app'.($p==='student.php'?' student-dashboard':''):($role==='teacher'?' teacher-app':($role==='admin'||$role==='librarian'?' staff-app':''))).'"><aside><div class="brand">'.$brandMark.'<span>'.h($libraryName).'<small>'.h($schoolName).'</small></span></div>'.($role==='student'?'<a class="student-logout" href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span>Log out</span></a>':($role==='teacher'?'<a class="student-logout" href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span>Log out</span></a>':($role==='admin'||$role==='librarian'?'<a class="staff-logout" href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span>Log out</span></a>':'' ))).'<nav>';
        foreach($nav as $f=>[$l,$i]){
          $active=($p==='account.php'&&strpos($f,'account.php')===0)||($p===basename(parse_url($f,PHP_URL_PATH)?:$f)&&(!$f||$f[0]!=='#'))||($p==='student.php'&&$f==='#dashboard');
          echo '<a href="'.$f.'"'.($active?' class="on"':'').'><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$I[$i].'</svg>'.$l.($role==='admin'&&$l==='Account & Requests'&&$notificationCount>0?'<i class="nav-alert-dot" aria-label="'.$notificationCount.' pending requests"></i>':'').'</a>';
        }
        echo '</nav><div class="who"><div class="who-info"><b>'.h($_SESSION['u']['username']).'</b><small><span class="who-dot"></span>'.h(ucfirst($role)).' account</small></div><div class="who-actions"><button type="button" class="tg" title="Switch to dark mode" aria-label="Switch to dark mode" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l-1.42-1.42m11.3-11.3 1.42-1.42"/></svg></button><a class="logout" href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span>Log out</span></a></div></div></aside><main><h1>'.h($title).'</h1>';
      }
      
      function foot($scripts=''){
          $chat='';
          if(!empty($_SESSION['u']['id'])){
            ensure_chat_schema($GLOBALS['pdo']);
            $chat='<div class="chat-widget" id="chat-widget" data-user-id="'.(int)$_SESSION['u']['id'].'" data-csrf="'.h(csrf_token()).'">
              <section class="chat-panel" id="chat-panel" hidden aria-label="Messages">
                <header class="chat-head"><div><strong>Messages</strong><small>Library community chat</small></div><button type="button" class="chat-close" data-chat-close aria-label="Close chat" onclick="document.querySelector(\'[data-chat-open]\').click()">×</button></header>
                <div class="chat-layout"><aside class="chat-people"><button type="button" class="chat-new" data-chat-new onclick="var d=this.parentNode.querySelector(\'[data-chat-directory]\');d.hidden=false;d.dispatchEvent(new CustomEvent(\'chat:new\'))">+ New chat</button><div data-chat-list></div><div class="chat-directory" data-chat-directory hidden><label class="chat-search-label"><span>Search students or staff</span><input type="search" data-chat-search maxlength="80" placeholder="Name, student ID, or username" autocomplete="off"></label><div data-chat-results></div></div></aside>
                  <section class="chat-room"><div class="chat-room-title" data-chat-title>Select a conversation</div><div class="chat-messages" data-chat-messages aria-live="polite"></div><form class="chat-compose" data-chat-form><input name="message" maxlength="2000" placeholder="Write a message..." autocomplete="off" disabled required><button type="submit" disabled>Send</button></form></section>
                </div><p class="chat-error" data-chat-error role="status"></p>
              </section>
              <button type="button" class="chat-launcher" data-chat-open aria-label="Open messages" onclick="var p=document.getElementById(\'chat-panel\');p.hidden=!p.hidden;this.setAttribute(\'aria-expanded\',String(!p.hidden));this.dispatchEvent(new CustomEvent(\'chat:toggle\',{detail:{open:!p.hidden}}))">Chat<span class="chat-unread-dot" data-chat-unread hidden></span></button>
            </div>';
          }
          echo '</main></div>'.$chat.'<script src="assets/app.js?v='.filemtime(__DIR__.'/assets/app.js').'"></script>'.($chat?'<script src="assets/messenger.js?v='.filemtime(__DIR__.'/assets/messenger.js').'"></script>':'').$scripts.'</body></html>';
      }
