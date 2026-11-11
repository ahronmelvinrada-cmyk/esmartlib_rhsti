<?php
require 'config.php';
require 'Ui2026.PHP';
auth();

$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'borrow', 'return', 'attendance'], true)) {
    $type = 'all';
}
$search = trim(substr((string)($_GET['q'] ?? ''), 0, 100));
$records = [];

if (in_array($type, ['all', 'borrow', 'return'], true)) {
    $sql = "SELECT 'borrow' AS event_type, r.id AS record_id, r.borrowed_at AS occurred_at,
                   s.student_no, s.name, s.grade, s.section, b.title AS book_title,
                   r.due_date, r.returned_at, NULL AS time_in, NULL AS time_out
            FROM borrowings r
            JOIN students s ON s.id = r.student_id
            JOIN books b ON b.id = r.book_id";
    if ($type === 'return') {
        $sql .= " WHERE r.returned_at IS NOT NULL";
    }
    if ($search !== '') {
        $sql .= ($type === 'return' ? " AND" : " WHERE") . " (s.student_no LIKE ? OR s.name LIKE ? OR b.title LIKE ?)";
        $stmt = $pdo->prepare($sql);
        $term = '%' . $search . '%';
        $stmt->execute([$term, $term, $term]);
    } else {
        $stmt = $pdo->query($sql);
    }
    $records = array_merge($records, $stmt->fetchAll());
}

if ($type === 'all' || $type === 'attendance') {
    $sql = "SELECT 'attendance' AS event_type, a.id AS record_id, a.time_in AS occurred_at,
                   s.student_no, s.name, s.grade, s.section, NULL AS book_title,
                   NULL AS due_date, NULL AS returned_at, a.time_in, a.time_out
            FROM attendance a
            JOIN students s ON s.id = a.student_id";
    if ($search !== '') {
        $sql .= " WHERE s.student_no LIKE ? OR s.name LIKE ?";
        $stmt = $pdo->prepare($sql);
        $term = '%' . $search . '%';
        $stmt->execute([$term, $term]);
    } else {
        $stmt = $pdo->query($sql);
    }
    $records = array_merge($records, $stmt->fetchAll());
}

usort($records, static function ($a, $b) {
    $dateOrder = strcmp($b['occurred_at'], $a['occurred_at']);
    return $dateOrder !== 0 ? $dateOrder : ((int)$b['record_id'] <=> (int)$a['record_id']);
});

$title = $type === 'borrow' ? 'Borrow History' : ($type === 'return' ? 'Return History' : ($type === 'attendance' ? 'Attendance History' : 'All History'));
head($title);
ui_css();
?>
<style>
.pg .history-tabs{display:flex;gap:8px;flex-wrap:wrap}
.pg .history-tabs .active{background:var(--g);border-color:var(--g);color:#fff}
.pg .history-count{margin-left:auto}
.pg .history-type{white-space:nowrap}
@media print{
  .pg .noprint{display:none!important}
  .pg .card{border:0!important;box-shadow:none!important;padding:0!important}
  .pg .tw{overflow:visible!important}
  .pg table{width:100%;min-width:0;font-size:10px}
}
</style>
<div class="pg">
  <div class="card">
    <div class="ph">
      <span class="pico"><?=ic('clock')?></span>
      <div><h3><?=h($title)?></h3><p>Borrow scans include the student and book. Attendance includes time-in and time-out.</p></div>
      <span class="badge history-count"><?=count($records)?> record<?=count($records) === 1 ? '' : 's'?></span>
      <button class="ghost noprint" type="button" onclick="window.print()"><?=ic('print')?>Print History</button>
    </div>
    <div class="history-tabs noprint">
      <a class="btn ghost <?=$type === 'all' ? 'active' : ''?>" href="history.php">All History</a>
      <a class="btn ghost <?=$type === 'borrow' ? 'active' : ''?>" href="history.php?type=borrow">Borrow History</a>
      <a class="btn ghost <?=$type === 'return' ? 'active' : ''?>" href="history.php?type=return">Return History</a>
      <a class="btn ghost <?=$type === 'attendance' ? 'active' : ''?>" href="history.php?type=attendance">Attendance History</a>
    </div>
    <form class="bar noprint" method="get" action="history.php" style="margin-top:14px">
      <?php if ($type !== 'all'): ?><input type="hidden" name="type" value="<?=h($type)?>"><?php endif; ?>
      <div class="sw"><?=ic('search')?><input type="search" name="q" value="<?=h($search)?>" placeholder="Search student, student no., or book"></div>
      <button type="submit"><?=ic('search')?>Search</button>
      <?php if ($search !== ''): ?><a class="btn ghost" href="history.php<?=$type !== 'all' ? '?type='.h($type) : ''?>">Clear</a><?php endif; ?>
    </form>
    <div class="tw" style="margin-top:16px">
      <table>
        <tr><th>Date &amp; Time</th><th>Type</th><th>Student</th><th>Grade / Section</th><th>Book</th><th>Details</th></tr>
        <?php foreach ($records as $record): ?>
          <tr>
            <td><?=h(date('M j, Y · h:i A', strtotime($record['occurred_at'])))?></td>
            <td><span class="pill <?=$record['event_type'] === 'borrow' ? 'out' : 'in'?> history-type"><?=h($record['event_type'] === 'borrow' ? 'Borrow' : 'Attendance')?></span></td>
            <td><?=h($record['name'])?><br><small class="muted"><?=h($record['student_no'])?></small></td>
            <td class="muted"><?=h(trim(($record['grade'] ?? '') . ' ' . ($record['section'] ?? '')) ?: '—')?></td>
            <td><?=h($record['book_title'] ?: '—')?></td>
            <td>
              <?php if ($record['event_type'] === 'borrow'):
                $returned = !empty($record['returned_at']);
                $late = $returned
                    ? substr($record['returned_at'], 0, 10) > $record['due_date']
                    : $record['due_date'] < date('Y-m-d');
                $status = $returned ? ($late ? 'Returned late' : 'Returned') : ($late ? 'Overdue' : 'Borrowed');
              ?>
                <?=h($status)?> · Due <?=h(date('M j, Y', strtotime($record['due_date'])))?>
                <?=$returned ? ' · Returned ' . h(date('M j, Y · h:i A', strtotime($record['returned_at']))) : ''?>
              <?php else: ?>
                Time-in <?=h(date('h:i A', strtotime($record['time_in'])))?>
                · Time-out <?=$record['time_out'] ? h(date('h:i A', strtotime($record['time_out']))) : '—'?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$records): ?><tr><td colspan="6" class="muted" style="text-align:center;padding:28px">No history records found.</td></tr><?php endif; ?>
      </table>
    </div>
  </div>
</div>
<?php foot(); ?>

