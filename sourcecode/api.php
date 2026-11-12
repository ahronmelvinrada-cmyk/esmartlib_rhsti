    <?php 
    require 'config.php';
    require 'Ui2026.PHP';
    header('Content-Type: application/json');

    if (empty($_SESSION['u'])) {
        echo json_encode(['ok' => false, 'msg' => 'Not logged in']);
        exit;
    }

    function out($ok, $msg, $x = []) {
        echo json_encode(['ok' => $ok, 'msg' => $msg] + $x);
        exit;
    }

    function code($v, $p) {
        $v = trim($v);
        return strpos($v, $p) === 0 ? substr($v, strlen($p)) : $v;
    }

    function student($pdo, $c) {
        $s = $pdo->prepare("SELECT * FROM students WHERE student_no = ?");
        $s->execute([code($c, 'STU:')]);
        return $s->fetch();
    }

    function teacher($pdo, $c) {
        $s = $pdo->prepare("SELECT * FROM teachers WHERE teacher_no = ?");
        $s->execute([code($c, 'TCH:')]);
        return $s->fetch();
    }

    function borrower_profile($person, $isTeacher) {
        $photo=$person['photo']??'';
        $photoOk=$isTeacher
            ? teacher_photo_path_ok($photo)
            : photo_path_ok($photo);
        if(!$photoOk||!is_file(__DIR__.'/'.$photo))$photo='';
        return [
            'name'=>$person['name'],
            'id'=>$isTeacher?$person['teacher_no']:$person['student_no'],
            'type'=>$isTeacher?'Teacher':'Student',
            'photo'=>$photo,
            'grade'=>$isTeacher?'':($person['grade']??''),
            'strand'=>$isTeacher?'':($person['strand']??''),
            'section'=>$isTeacher?'':($person['section']??'')
        ];
    }

    function book($pdo, $c) {
        $s = $pdo->prepare("SELECT * FROM books WHERE accession_no = ?");
        $s->execute([code($c, 'BK:')]);
        return $s->fetch();
    }

    if (!in_array($_SESSION['u']['role']??'', ['admin','librarian'], true)) {
        out(false, 'Not allowed.');
    }

    ensure_teacher_schema($pdo);
    photo_col($pdo);
    ensure_book_request_schema($pdo);

    $a = $_POST['action'] ?? '';
    $now = date('Y-m-d H:i:s');

    if ($a == 'student') {
        ensure_student_account_status_schema($pdo);
        $codeValue=trim((string)($_POST['code']??''));
        $isTeacher=strpos($codeValue,'TCH:')===0;
        $person=$isTeacher?teacher($pdo,$codeValue):student($pdo,$codeValue);
        if (!$person) {
            out(false, $isTeacher?'Teacher pass not recognized.':'Student QR not recognized.');
        }
        if ((int)$person['account_active'] !== 1) out(false, $isTeacher?'Teacher account is inactive.':'Student account is inactive.');

        $n = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE ".($isTeacher?'teacher_id':'student_id')." = ? AND returned_at IS NULL");
        $n->execute([(int)$person['id']]);
        out(true, $isTeacher?'Teacher identified.':'Student identified.', [
            'student' => ['name'=>$person['name']],
            'profile'=>borrower_profile($person,$isTeacher),
            'borrower_type'=>$isTeacher?'teacher':'student',
            'borrower_code'=>$isTeacher?$person['teacher_no']:$person['student_no'],
            'active' => (int)$n->fetchColumn()
        ]);
    }

    if ($a == 'borrow') {
        ensure_student_account_status_schema($pdo);
        $borrowerCode=trim((string)($_POST['student']??''));
        $isTeacher=strpos($borrowerCode,'TCH:')===0;
        $person=$isTeacher?teacher($pdo,$borrowerCode):student($pdo,$borrowerCode);
        if (!$person) out(false, 'Scan a valid student QR or teacher pass first.');
        if ((int)$person['account_active'] !== 1) out(false, $isTeacher?'Teacher account is inactive.':'Student account is inactive.');
        $b = book($pdo, $_POST['code'] ?? '');
        if (!$b) out(false, 'Book QR not recognized.');

        $reservation = null;
        $failure = '';
        $due = $isTeacher?null:date('Y-m-d', strtotime('+' . LOAN_DAYS . ' days'));
        try {
            $pdo->beginTransaction();
            $personTable=$isTeacher?'teachers':'students';
            $personLock = $pdo->prepare("SELECT id FROM $personTable WHERE id=? FOR UPDATE");
            $personLock->execute([(int)$person['id']]);
            $bookLock = $pdo->prepare("SELECT * FROM books WHERE id=? FOR UPDATE");
            $bookLock->execute([(int)$b['id']]);
            $b = $bookLock->fetch();
            if (!$b) {
                $failure = 'Book QR not recognized.';
            } else {
                if(!$isTeacher){
                    $reservationQuery = $pdo->prepare("SELECT id FROM book_requests WHERE student_id=? AND book_id=? AND status='approved' FOR UPDATE");
                    $reservationQuery->execute([(int)$person['id'], (int)$b['id']]);
                    $reservation = $reservationQuery->fetch();
                }

                $heldQuery = $pdo->prepare("SELECT COUNT(*) FROM book_requests WHERE book_id=? AND status='approved'");
                $heldQuery->execute([(int)$b['id']]);
                $held = (int)$heldQuery->fetchColumn();
                if ((int)$b['available'] < 1 || (!$reservation && (int)$b['available'] - $held < 1)) {
                    $failure = '"' . $b['title'] . '" has no available copies (copies may be reserved).';
                } else {
                    $borrowerColumn=$isTeacher?'teacher_id':'student_id';
                    $count = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE $borrowerColumn=? AND returned_at IS NULL");
                    $count->execute([(int)$person['id']]);
                    if (!$isTeacher&&(int)$count->fetchColumn() >= MAX_LOANS) {
                        $failure = $person['name'] . ' has reached the limit of ' . MAX_LOANS . ' books.';
                    } else {
                        $duplicate = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE $borrowerColumn=? AND book_id=? AND returned_at IS NULL");
                        $duplicate->execute([(int)$person['id'], (int)$b['id']]);
                        if ((int)$duplicate->fetchColumn()) {
                            $failure = $isTeacher?'Teacher already borrowed this book.':'Student already borrowed this book.';
                        } else {
                            $studentId=$isTeacher?null:(int)$person['id'];
                            $teacherId=$isTeacher?(int)$person['id']:null;
                            $pdo->prepare("INSERT INTO borrowings(student_id,teacher_id,book_id,borrowed_at,due_date) VALUES(?,?,?,?,?)")
                                ->execute([$studentId,$teacherId,(int)$b['id'],$now,$due]);
                            $pdo->prepare("UPDATE books SET available=available-1 WHERE id=?")->execute([(int)$b['id']]);
                            if ($reservation) {
                                $pdo->prepare("UPDATE book_requests SET status='fulfilled',reviewed_at=NOW() WHERE id=?")
                                    ->execute([(int)$reservation['id']]);
                            }
                        }
                    }
                }
            }
            if ($failure !== '') {
                $pdo->rollBack();
                out(false, $failure);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        
        out(true, 'Borrowed: ' . $b['title'] . ($due?' (due ' . $due . ')':' (no due date)'), [
            'title' => $b['title'], 
            'due' => $due,
            'student_no' => $isTeacher?$person['teacher_no']:$person['student_no'],
            'student_name' => $person['name'],
            'borrower_type'=>$isTeacher?'teacher':'student',
            'profile'=>borrower_profile($person,$isTeacher),
            'borrowed_display' => date('M j, Y · h:i A', strtotime($now)),
            'due_display' => $due?date('M j, Y', strtotime($due)):'No due date'
        ]);
    }

    if ($a == 'return') {
        $b = book($pdo, $_POST['code'] ?? '');
        if (!$b) {
            out(false, 'Book QR not recognized.');
        }
        
        $r = $pdo->prepare("SELECT r.*, COALESCE(s.name,t.name) AS name,
          COALESCE(s.student_no,t.teacher_no) AS borrower_no,
          CASE WHEN r.teacher_id IS NOT NULL THEN 'Teacher' ELSE 'Student' END AS borrower_type,
          COALESCE(s.photo,t.photo) AS borrower_photo,s.grade,s.strand,s.section
          FROM borrowings r LEFT JOIN students s ON s.id = r.student_id
          LEFT JOIN teachers t ON t.id = r.teacher_id
          WHERE r.book_id = ? AND r.returned_at IS NULL ORDER BY r.borrowed_at LIMIT 1");
        $r->execute([$b['id']]);
        $r = $r->fetch();
        
        if (!$r) {
            out(false, 'No active borrowing record for "' . $b['title'] . '".');
        }
        
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE borrowings SET returned_at = ? WHERE id = ?")
            ->execute([$now, $r['id']]);
        $pdo->prepare("UPDATE books SET available = available + 1 WHERE id = ?")
            ->execute([$b['id']]);
        $pdo->commit();
        
        $late = !empty($r['due_date']) && $r['due_date'] < date('Y-m-d');
        
        out(true, 'Returned: ' . $b['title'] . ' from ' . $r['borrower_type'] . ' ' . $r['name'] . ($late ? ' (OVERDUE, due ' . $r['due_date'] . ')' : ''), [
            'late' => $late, 
            'title' => $b['title'], 
            'name' => $r['name'],
            'borrower_type' => $r['borrower_type'],
            'borrower_no' => $r['borrower_no'],
            'profile'=>borrower_profile([
                'name'=>$r['name'],
                'student_no'=>$r['borrower_no'],
                'teacher_no'=>$r['borrower_no'],
                'photo'=>$r['borrower_photo'],
                'grade'=>$r['grade'],
                'strand'=>$r['strand'],
                'section'=>$r['section']
            ],$r['borrower_type']==='Teacher')
        ]);
    }

    if ($a == 'attendance') {
        photo_col($pdo);
        ensure_student_account_status_schema($pdo);
        $s = student($pdo, $_POST['code'] ?? '');
        if (!$s) {
            out(false, 'Student QR not recognized.');
        }
        if ((int)$s['account_active'] !== 1) {
            out(false, 'Student account is inactive.');
        }
        
        $l = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND DATE(time_in) = CURDATE() ORDER BY id DESC LIMIT 1");
        $l->execute([$s['id']]);
        $l = $l->fetch();
        
        $last = $l ? strtotime($l['time_out'] ?: $l['time_in']) : 0;
        if (time() - $last < SCAN_COOLDOWN) {
            out(false, $s['name'] . ' was just scanned. Please wait.');
        }
        
        if ($l && !$l['time_out']) {
            $pdo->prepare("UPDATE attendance SET time_out = ? WHERE id = ?")
                ->execute([$now, $l['id']]);
            $t = 'TIME-OUT';
        } else {
            $pdo->prepare("INSERT INTO attendance(student_id, time_in) VALUES(?, ?)")
                ->execute([$s['id'], $now]);
            $t = 'TIME-IN';
        }
        $photo = !empty($s['photo']) && photo_path_ok($s['photo']) && is_file(__DIR__ . '/' . $s['photo'])
            ? $s['photo']
            : '';
        out(true, $t . ': ' . $s['name'] . ' at ' . date('h:i:s A'), [
            'type' => $t, 
            'name' => $s['name'], 
            'student_no' => $s['student_no'],   
            'photo' => $photo,
            'time' => date('h:i A')
        ]);
    }

    out(false, 'Unknown action.');
    ?>
