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

$a = $_POST['action'] ?? '';
$now = date('Y-m-d H:i:s');

if ($a == 'student') {
    $s = student($pdo, $_POST['code'] ?? '');
    if (!$s) {
        out(false, 'Student QR not recognized.');
    }
    
    $n = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE student_id = ? AND returned_at IS NULL");
    $n->execute([$s['id']]);
    
    out(true, 'Student identified.', [
        'student' => $s, 
        'active' => (int)$n->fetchColumn()
    ]);
}

if ($a == 'borrow') {
    $s = student($pdo, $_POST['student'] ?? '');
    if (!$s) {
        out(false, 'Scan the student QR first.');
    }
    
    $b = book($pdo, $_POST['code'] ?? '');
    if (!$b) {
        out(false, 'Book QR not recognized.');
    }
    
    if ($b['available'] < 1) {
        out(false, '"' . $b['title'] . '" has no available copies.');
    }
    
    $c = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE student_id = ? AND returned_at IS NULL");
    $c->execute([$s['id']]);
    if ($c->fetchColumn() >= MAX_LOANS) {
        out(false, $s['name'] . ' has reached the limit of ' . MAX_LOANS . ' books.');
    }
    
    $d = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE student_id = ? AND book_id = ? AND returned_at IS NULL");
    $d->execute([$s['id'], $b['id']]);
    if ($d->fetchColumn()) {
        out(false, 'Student already borrowed this book.');
    }
    
    $due = date('Y-m-d', strtotime('+' . LOAN_DAYS . ' days'));
    
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO borrowings(student_id, book_id, borrowed_at, due_date) VALUES(?, ?, ?, ?)")
        ->execute([$s['id'], $b['id'], $now, $due]);
    $pdo->prepare("UPDATE books SET available = available - 1 WHERE id = ?")
        ->execute([$b['id']]);
    $pdo->commit();
    
    out(true, 'Borrowed: ' . $b['title'] . ' (due ' . $due . ')', [
        'title' => $b['title'], 
        'due' => $due
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
    $s = student($pdo, $_POST['code'] ?? '');
    if (!$s) {
        out(false, 'Student QR not recognized.');
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