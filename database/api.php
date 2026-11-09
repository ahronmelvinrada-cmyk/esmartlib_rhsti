<?php 
require 'config.php';
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

function book($pdo, $c) {
    $s = $pdo->prepare("SELECT * FROM books WHERE accession_no = ?");
    $s->execute([code($c, 'BK:')]);
    return $s->fetch();
}

if ($_SESSION['u']['role'] == 'student') {
    out(false, 'Not allowed.');
}

ensure_book_request_schema($pdo);

$a = $_POST['action'] ?? '';
$now = date('Y-m-d H:i:s');

if ($a == 'student') {
    ensure_student_account_status_schema($pdo);
    $s = student($pdo, $_POST['code'] ?? '');
    if (!$s) {
        out(false, 'Student QR not recognized.');
    }
    
    $n = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE student_id = ? AND returned_at IS NULL");
    $n->execute([$s['id']]);
    
    if ((int)$s['account_active'] !== 1) {
        out(false, 'Student account is inactive.');
    }

    out(true, 'Student identified.', [
        'student' => $s, 
        'active' => (int)$n->fetchColumn()
    ]);
}

if ($a == 'borrow') {
    ensure_student_account_status_schema($pdo);
    $s = student($pdo, $_POST['student'] ?? '');
    if (!$s) out(false, 'Scan the student QR first.');
    if ((int)$s['account_active'] !== 1) out(false, 'Student account is inactive.');
    $b = book($pdo, $_POST['code'] ?? '');
    if (!$b) out(false, 'Book QR not recognized.');

    $reservation = null;
    $failure = '';
    $due = date('Y-m-d', strtotime('+' . LOAN_DAYS . ' days'));
    try {
        $pdo->beginTransaction();
        $studentLock = $pdo->prepare("SELECT id FROM students WHERE id=? FOR UPDATE");
        $studentLock->execute([(int)$s['id']]);
        $bookLock = $pdo->prepare("SELECT * FROM books WHERE id=? FOR UPDATE");
        $bookLock->execute([(int)$b['id']]);
        $b = $bookLock->fetch();
        if (!$b) {
            $failure = 'Book QR not recognized.';
        } else {
            $reservationQuery = $pdo->prepare("SELECT id FROM book_requests WHERE student_id=? AND book_id=? AND status='approved' FOR UPDATE");
            $reservationQuery->execute([(int)$s['id'], (int)$b['id']]);
            $reservation = $reservationQuery->fetch();

            $heldQuery = $pdo->prepare("SELECT COUNT(*) FROM book_requests WHERE book_id=? AND status='approved'");
            $heldQuery->execute([(int)$b['id']]);
            $held = (int)$heldQuery->fetchColumn();
            if ((int)$b['available'] < 1 || (!$reservation && (int)$b['available'] - $held < 1)) {
                $failure = '"' . $b['title'] . '" has no available copies (copies may be reserved).';
            } else {
                $count = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE student_id=? AND returned_at IS NULL");
                $count->execute([(int)$s['id']]);
                if ((int)$count->fetchColumn() >= MAX_LOANS) {
                    $failure = $s['name'] . ' has reached the limit of ' . MAX_LOANS . ' books.';
                } else {
                    $duplicate = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE student_id=? AND book_id=? AND returned_at IS NULL");
                    $duplicate->execute([(int)$s['id'], (int)$b['id']]);
                    if ((int)$duplicate->fetchColumn()) {
                        $failure = 'Student already borrowed this book.';
                    } else {
                        $pdo->prepare("INSERT INTO borrowings(student_id,book_id,borrowed_at,due_date) VALUES(?,?,?,?)")
                            ->execute([(int)$s['id'], (int)$b['id'], $now, $due]);
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
    
    out(true, 'Borrowed: ' . $b['title'] . ' (due ' . $due . ')', [
        'title' => $b['title'], 
        'due' => $due,
        'student_no' => $s['student_no'],
        'student_name' => $s['name'],
        'borrowed_display' => date('M j, Y · h:i A', strtotime($now)),
        'due_display' => date('M j, Y', strtotime($due))
    ]);
}

if ($a == 'return') {
    $b = book($pdo, $_POST['code'] ?? '');
    if (!$b) {
        out(false, 'Book QR not recognized.');
    }
    
    $r = $pdo->prepare("SELECT r.*, s.name FROM borrowings r JOIN students s ON s.id = r.student_id WHERE r.book_id = ? AND r.returned_at IS NULL ORDER BY r.borrowed_at LIMIT 1");
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
    
    $late = $r['due_date'] < date('Y-m-d');
    
    out(true, 'Returned: ' . $b['title'] . ' from ' . $r['name'] . ($late ? ' (OVERDUE, due ' . $r['due_date'] . ')' : ''), [
        'late' => $late, 
        'title' => $b['title'], 
        'name' => $r['name']
    ]);
}

if ($a == 'attendance') {
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
    out(true, $t . ': ' . $s['name'] . ' at ' . date('h:i:s A'), [
        'type' => $t, 
        'name' => $s['name'], 
        'time' => date('h:i A')
    ]);
}

out(false, 'Unknown action.');
?>