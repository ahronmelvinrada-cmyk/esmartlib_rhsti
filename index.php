<?php
require 'config.php';
require 'ui2026.php';
auth();
head('Dashboard');
ui_side();

$q = fn($sql) => $pdo->query($sql)->fetchColumn();

/* ---------- Stat cards: label => [value, icon, tone] ---------- */
$c = [
    'Total Book Assets'             => [$q("SELECT COALESCE(SUM(quantity),0) FROM books"), 'book', 'emerald'],
    'Registered Students'           => [$q("SELECT COUNT(*) FROM students"), 'users', 'blue'],
    'Books Out'                     => [$q("SELECT COUNT(*) FROM borrowings WHERE returned_at IS NULL"), 'out', 'amber'],
    'Overdue Alerts'                => [$q("SELECT COUNT(*) FROM borrowings WHERE returned_at IS NULL AND due_date < CURDATE()"), 'alert', 'rose'],
    'Scanned Today'                 => [$q("SELECT COUNT(*) FROM borrowings WHERE DATE(borrowed_at) = CURDATE() OR DATE(returned_at) = CURDATE()"), 'scan', 'violet'],
    'Library Visits Today'          => [$q("SELECT COUNT(*) FROM attendance WHERE DATE(time_in) = CURDATE()"), 'door', 'teal'],
];
$sub = [
    'Total Book Assets'    => 'copies in the collection',
    'Registered Students'  => 'with library access',
    'Books Out'            => 'currently borrowed',
    'Overdue Alerts'       => 'need follow-up',
    'Scanned Today'        => 'borrow + return',
    'Library Visits Today' => 'time-ins recorded',
];

$icons = [
    'book'  => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Z"/><path d="M19 19v2H6"/>',
    'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.2a6.5 6.5 0 0 1 3.5 5.8"/>',
    'out'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'alert' => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17.5v.01"/>',
    'scan'  => '<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M4 12h16"/>',
    'door'  => '<path d="M5 21V4a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v17M3 21h18M12 12v.01"/>',
];
$svg = fn($k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $icons[$k] . '</svg>';

/* ---------- Last 7 days ---------- */
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = [0, 0];
}
foreach ($pdo->query("SELECT DATE(borrowed_at) AS d, COUNT(*) AS n FROM borrowings
                      WHERE borrowed_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d") as $r) {
    if (isset($days[$r['d']])) $days[$r['d']][0] = (int)$r['n'];
}
foreach ($pdo->query("SELECT DATE(time_in) AS d, COUNT(*) AS n FROM attendance
                      WHERE time_in >= CURDATE() - INTERVAL 6 DAY GROUP BY d") as $r) {
    if (isset($days[$r['d']])) $days[$r['d']][1] = (int)$r['n'];
}
$labels = array_map(fn($d) => date('D j', strtotime($d)), array_keys($days));

$overdue = $pdo->query("SELECT s.name, b.title, r.due_date, DATEDIFF(CURDATE(), r.due_date) AS late
                        FROM borrowings r
                        JOIN students s ON s.id = r.student_id
                        JOIN books b ON b.id = r.book_id
                        WHERE r.returned_at IS NULL AND r.due_date < CURDATE()
                        ORDER BY r.due_date LIMIT 10")->fetchAll();
?>

<style>
.dash{
  --muted:var(--mute); --surface:#fff;
  --brand:var(--g); --brand-ink:var(--gd); --radius:20px;
  color:var(--ink); font-feature-settings:"tnum"; display:grid; gap:22px;
}
.dash *{box-sizing:border-box}

/* Hero */
.dash .hero{display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px}
.dash .hero h1{margin:0; font-size:clamp(26px,3vw,36px); letter-spacing:-.03em; font-weight:800}
.dash .hero p{margin:4px 0 0; color:var(--muted); font-size:14px}
.dash .live{display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:999px;
  background:#ecfdf5; color:var(--brand-ink); font-weight:600; font-size:13px; border:1px solid #bbf7d0}
.dash .live i{width:8px; height:8px; border-radius:50%; background:var(--brand); box-shadow:0 0 0 0 rgba(16,185,129,.6); animation:ping 2s infinite}
@keyframes ping{70%{box-shadow:0 0 0 8px rgba(16,185,129,0)}100%{box-shadow:0 0 0 0 rgba(16,185,129,0)}}

/* Stat cards */
.dash .stats{display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:16px}
.dash .stat{position:relative; overflow:hidden; background:var(--surface); border:1px solid var(--line);
  border-radius:var(--radius); padding:20px; display:flex; flex-direction:column; gap:14px;
  transition:transform .25s, box-shadow .25s, border-color .25s}
.dash .stat::after{content:""; position:absolute; inset:auto -30% -60% auto; width:160px; height:160px; border-radius:50%;
  background:var(--tone-bg); opacity:.55; filter:blur(28px); pointer-events:none}
.dash .stat:hover{transform:translateY(-4px); box-shadow:0 18px 40px -18px rgba(15,31,23,.25); border-color:var(--tone)}
.dash .ico{width:44px; height:44px; border-radius:14px; display:grid; place-items:center; background:var(--tone-bg); color:var(--tone)}
.dash .ico svg{width:22px; height:22px}
.dash .stat .lbl{font-size:13px; color:var(--muted); font-weight:600}
.dash .stat .num{font-size:38px; line-height:1; font-weight:800; letter-spacing:-.04em; margin-top:2px}
.dash .stat .hint{font-size:12px; color:var(--muted); margin-top:6px}
.dash .emerald{--tone:#059669; --tone-bg:#d1fae5}
.dash .blue{--tone:#2563eb; --tone-bg:#dbeafe}
.dash .amber{--tone:#d97706; --tone-bg:#fef3c7}
.dash .rose{--tone:#e11d48; --tone-bg:#ffe4e6}
.dash .violet{--tone:#7c3aed; --tone-bg:#ede9fe}
.dash .teal{--tone:#0d9488; --tone-bg:#ccfbf1}
.dash .rose.hot{border-color:#fecdd3}

/* Quick actions */
.dash .actions{display:flex; flex-wrap:wrap; gap:10px}
.dash .act{display:inline-flex; align-items:center; gap:8px; padding:11px 18px; border-radius:14px; text-decoration:none;
  font-weight:600; font-size:14px; color:var(--brand-ink); background:#fff; border:1px solid var(--line);
  transition:all .2s}
.dash .act:hover{background:var(--brand-ink); color:#fff; border-color:var(--brand-ink); transform:translateY(-2px)}
.dash .act.primary{background:linear-gradient(135deg,#2a9d5c,var(--g)); color:#fff; border:0; box-shadow:0 10px 24px -10px var(--g)}
.dash .act.primary:hover{filter:brightness(1.08)}
.dash .act span{opacity:.6; transition:transform .2s}
.dash .act:hover span{transform:translateX(3px); opacity:1}

/* Panels */
.dash .panel{background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); padding:24px}
.dash .panel-h{display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:10px; flex-wrap:wrap}
.dash .panel-h h3{margin:0; font-size:18px; font-weight:700; letter-spacing:-.02em}
.dash .badge{font-size:12px; font-weight:700; padding:5px 10px; border-radius:999px; background:#f1f5f3; color:var(--muted)}
.dash .badge.bad{background:#ffe4e6; color:#be123c}
.dash .chart-box{position:relative; height:320px}

/* Table */
.dash .tbl{width:100%; border-collapse:separate; border-spacing:0 6px}
.dash .tbl{border:0; background:transparent; border-radius:0; overflow:visible}
.dash .tbl th{background:transparent; border-bottom:0; color:var(--muted)}
.dash .tbl td{border-bottom:0}
.dash .tbl th{text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); font-weight:700; padding:6px 14px}
.dash .tbl td{padding:14px; background:#f8fbf9; font-size:14px}
.dash .tbl td:first-child{border-radius:14px 0 0 14px; font-weight:600}
.dash .tbl td:last-child{border-radius:0 14px 14px 0}
.dash .tbl tr:hover td{background:#effaf4}
.dash .pill{display:inline-block; padding:4px 10px; border-radius:999px; background:#ffe4e6; color:#be123c; font-weight:700; font-size:12px}
.dash .empty{text-align:center; padding:36px 10px; color:var(--muted)}
.dash .empty b{display:block; color:var(--ink); font-size:16px; margin-bottom:4px}
.dash .empty .ok{width:52px; height:52px; margin:0 auto 12px; border-radius:50%; background:#d1fae5; color:#059669; display:grid; place-items:center; font-size:24px}

@media (max-width:640px){ .dash .panel{padding:16px} .dash .tbl td:nth-child(2){display:none} .dash .tbl th:nth-child(2){display:none} }
@media (prefers-reduced-motion:reduce){ .dash *{animation:none!important; transition:none!important} }
</style>

<div class="dash">

  <!-- Hero -->
  <div class="hero">
    <div>
      <h1>Dashboard</h1>
      <p><?= date('l, F j, Y') ?> · Welcome back, here’s what’s happening in the library.</p>
    </div>
    <div class="live"><i></i> System online</div>
  </div>

  <!-- Stats -->
  <div class="stats">
    <?php foreach ($c as $k => [$v, $ic, $tone]): ?>
      <div class="stat <?= $tone ?> <?= ($k === 'Overdue Alerts' && $v > 0) ? 'hot' : '' ?>">
        <div class="ico"><?= $svg($ic) ?></div>
        <div>
          <div class="lbl"><?= h($k) ?></div>
          <div class="num" data-count="<?= (int)$v ?>"><?= h($v) ?></div>
          <div class="hint"><?= h($sub[$k]) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Quick actions -->
  <div class="actions">
    <a class="act primary" href="borrow.php">Issue New Borrow <span>→</span></a>
    <a class="act" href="return.php">Return Processing <span>→</span></a>
    <a class="act" href="attendance.php">Time-In / Time-Out <span>→</span></a>
    <a class="act" href="books.php">Add &amp; Manage Books <span>→</span></a>
    <a class="act" href="students.php">Manage Students <span>→</span></a>
    <?php if(($_SESSION['u']['role'] ?? '') === 'admin'): ?><a class="act" href="settings.php">Settings <span>→</span></a><?php endif; ?>
  </div>

  <!-- Chart -->
  <div class="panel">
    <div class="panel-h">
      <h3>Last 7 days</h3>
      <span class="badge">Borrowed vs. Library visits</span>
    </div>
    <div class="chart-box"><canvas id="ch"></canvas></div>
  </div>

  <!-- Overdue -->
  <div class="panel">
    <div class="panel-h">
      <h3>Overdue books</h3>
      <span class="badge <?= $overdue ? 'bad' : '' ?>"><?= count($overdue) ?> <?= count($overdue) === 1 ? 'item' : 'items' ?></span>
    </div>

    <?php if (!$overdue): ?>
      <div class="empty">
        <div class="ok">✓</div>
        <b>All clear!</b>
        Walang overdue na libro ngayon.
      </div>
    <?php else: ?>
      <table class="tbl">
        <thead><tr><th>Student</th><th>Book</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($overdue as $r): ?>
            <tr>
              <td><?= h($r['name']) ?></td>
              <td><?= h($r['title']) ?></td>
              <td><?= h(date('M j, Y', strtotime($r['due_date']))) ?></td>
              <td><span class="pill"><?= (int)$r['late'] ?> day<?= $r['late'] == 1 ? '' : 's' ?> late</span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php
$chartScript = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function(){
  // Count-up animation sa stat numbers
  document.querySelectorAll("[data-count]").forEach(function(el){
    var end = +el.dataset.count, t0 = performance.now(), dur = 900;
    if(!end) return;
    (function tick(t){
      var p = Math.min((t - t0) / dur, 1), e = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(end * e).toLocaleString();
      if(p < 1) requestAnimationFrame(tick);
    })(t0);
  });

  var ctx = document.getElementById("ch").getContext("2d");
  var g1 = ctx.createLinearGradient(0,0,0,320); g1.addColorStop(0,"#818cf8"); g1.addColorStop(1,"#4f46e5");
  var g2 = ctx.createLinearGradient(0,0,0,320); g2.addColorStop(0,"#34d399"); g2.addColorStop(1,"#059669");

  new Chart(ctx, {
    type: "bar",
    data: {
      labels: ' . json_encode($labels) . ',
      datasets: [
        { label: "Borrowed", data: ' . json_encode(array_column($days, 0)) . ', backgroundColor: g1, borderRadius: 10, borderSkipped: false, maxBarThickness: 34 },
        { label: "Library visits", data: ' . json_encode(array_column($days, 1)) . ', backgroundColor: g2, borderRadius: 10, borderSkipped: false, maxBarThickness: 34 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: { position: "top", align: "end", labels: { usePointStyle: true, pointStyle: "circle", boxWidth: 8, padding: 18, font: { weight: "600" } } },
        tooltip: { backgroundColor: "#0f1f17", padding: 12, cornerRadius: 12, boxPadding: 6, titleFont: { weight: "700" } }
      },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { color: "#6b7a72", font: { weight: "600" } } },
        y: { beginAtZero: true, border: { display: false }, grid: { color: "#eef2ef" },
             ticks: { precision: 0, stepSize: 1, color: "#6b7a72" } }cd C:\xampp\htdocs
      },
      animation: { duration: 900, easing: "easeOutQuart" }
    }
  });
})();
</script>';

foot($chartScript);
?>  