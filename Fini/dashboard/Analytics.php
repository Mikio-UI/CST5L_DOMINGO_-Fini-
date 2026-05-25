<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href="/Fini/login.php";</script>';
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$user_id  = (int) $_SESSION['user_id'];
$today    = date('Y-m-d');

require_once __DIR__ . '/../public/database.config.php';
$db = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DB_NAME);

// ── Tag class → hex color map ─────────────────────────────
$tagClassToHex = [
    'tag-blue'   => '#4a8fff',
    'tag-orange' => '#f0a070',
    'tag-purple' => '#c4b0ff',
    'tag-red'    => '#f07070',
    'tag-green'  => '#52d68a',
];

// ── Initialise every variable the HTML needs ─────────────
$incompleteTasks   = 0;
$totalTasks        = 0;
$completedThisWeek = 0;
$completedTrend    = 0;   // always 0 — no updated_at to compare weeks
$completionRate    = 0;
$completionTrend   = 0;   // always 0 — no updated_at for last-month rate
$overdueCount      = 0;
$overdueRate       = 0;
$monthLabel        = date('F Y');
$activityItems     = [];
$topTasksData      = [];
$barDataJS         = '[]';
$lineDataJS        = '[]';
$donutDataJS       = '[]';
$donutTotalJS      = '0';
$lineMax           = 1;

if (!$db->connect_error) {
    // ── Nav badge: tasks not done ─────────────────────────
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'done'");
    $s->bind_param('i', $user_id); $s->execute(); $s->bind_result($incompleteTasks); $s->fetch(); $s->close();

    // ── Total tasks ───────────────────────────────────────
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ?");
    $s->bind_param('i', $user_id); $s->execute(); $s->bind_result($totalTasks); $s->fetch(); $s->close();

    // ── Completed (done) count ────────────────────────────
    $doneCount = 0;
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'done'");
    $s->bind_param('i', $user_id); $s->execute(); $s->bind_result($doneCount); $s->fetch(); $s->close();

    // ── Completed this week: tasks created this week that are done ──
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd   = date('Y-m-d', strtotime('sunday this week'));
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'done' AND DATE(created_at) BETWEEN ? AND ?");
    $s->bind_param('iss', $user_id, $weekStart, $weekEnd);
    $s->execute(); $s->bind_result($completedThisWeek); $s->fetch(); $s->close();

    // ── Completion rate ───────────────────────────────────
    if ($totalTasks > 0) {
        $completionRate = (int)round(($doneCount / $totalTasks) * 100);
    }

    // ── Overdue ───────────────────────────────────────────
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'done' AND due_date IS NOT NULL AND due_date < ?");
    $s->bind_param('is', $user_id, $today);
    $s->execute(); $s->bind_result($overdueCount); $s->fetch(); $s->close();
    $overdueRate = ($totalTasks > 0) ? (int)round(($overdueCount / $totalTasks) * 100) : 0;

    // ── Recent activity: last 10 tasks by created_at ──────
    $s = $db->prepare("SELECT title, status, tag_label, created_at FROM tasks WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $s->bind_param('i', $user_id);
    $s->execute();
    $res2 = $s->get_result();
    while ($row = $res2->fetch_assoc()) { $activityItems[] = $row; }
    $s->close();

    // ── Top tasks: overdue or due soonest, not done ───────
    $s = $db->prepare("SELECT id, title, tag_label, tag_class, due_date, status FROM tasks WHERE user_id = ? AND status != 'done' ORDER BY due_date ASC LIMIT 5");
    $s->bind_param('i', $user_id);
    $s->execute();
    $res2 = $s->get_result();
    while ($row = $res2->fetch_assoc()) { $topTasksData[] = $row; }
    $s->close();
    // Calculate effort_pct based on rank (1st = 100%, descending)
    $topCount = count($topTasksData);
    foreach ($topTasksData as $i => &$t) {
        $t['effort_pct'] = $topCount > 1 ? (int)round(100 - ($i / ($topCount - 1)) * 60) : 100;
    }
    unset($t);

    // ── Bar chart: tasks created per day last 7 days ──────
    $barData = [];
    for ($d = 6; $d >= 0; $d--) {
        $dayDate  = date('Y-m-d', strtotime("-{$d} days"));
        $dayLabel = date('D', strtotime($dayDate));
        $cnt = 0;
        $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND DATE(created_at) = ?");
        $s->bind_param('is', $user_id, $dayDate);
        $s->execute(); $s->bind_result($cnt); $s->fetch(); $s->close();
        $barData[] = ['label' => $dayLabel, 'total' => $cnt, 'completed' => $cnt];
    }
    $barDataJS = json_encode($barData);

    // ── Line chart: cumulative done tasks last 7 days ─────
    $lineData = [];
    for ($d = 6; $d >= 0; $d--) {
        $dayDate = date('Y-m-d', strtotime("-{$d} days"));
        $cnt = 0;
        $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'done' AND DATE(created_at) <= ?");
        $s->bind_param('is', $user_id, $dayDate);
        $s->execute(); $s->bind_result($cnt); $s->fetch(); $s->close();
        $lineData[] = $cnt;
    }
    $lineDataJS = json_encode($lineData);
    $lineMax     = !empty($lineData) ? max($lineData) : 1;
    if ($lineMax < 1) $lineMax = 1;

    // ── Donut: tasks by status ────────────────────────────
    $donutData  = [];
    $donutTotal = 0;
    $donutColors = ['todo' => '#4a8fff', 'inprogress' => '#f0a070', 'done' => '#52d68a'];
    foreach (['todo' => 'To Do', 'inprogress' => 'In Progress', 'done' => 'Done'] as $st => $stLabel) {
        $cnt = 0;
        $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = ?");
        $s->bind_param('is', $user_id, $st);
        $s->execute(); $s->bind_result($cnt); $s->fetch(); $s->close();
        $donutData[] = ['label' => $stLabel, 'value' => $cnt, 'color' => $donutColors[$st]];
        $donutTotal += $cnt;
    }
    // Add pct after we know total
    foreach ($donutData as &$d) {
        $d['pct'] = $donutTotal > 0 ? round(($d['value'] / $donutTotal) * 100) : 0;
    }
    unset($d);
    $donutDataJS  = json_encode($donutData);
    $donutTotalJS = json_encode($donutTotal);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            if (localStorage.getItem('fini_theme') === 'light') {
                document.documentElement.classList.add('light-mode');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fini — Analytics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Fini/css/Analytics.css">
    <style>
        :root.light-mode {
            --bg:     #f0f2f8;
            --panel:  #ffffff;
            --panel2: #f5f7fc;
            --border: #dde1ed;
            --text:   #1a1d2e;
            --muted:  #7c85a0;
            --subtle: #e4e8f3;
        }
        :root.light-mode body { background: #f0f2f8 !important; color: #1a1d2e !important; }
        :root.light-mode .bg-video { opacity: 0.04 !important; filter: invert(1) hue-rotate(180deg) !important; }
        :root.light-mode .topbar { background: rgba(240,242,248,0.95) !important; border-bottom-color: #dde1ed !important; }
        :root.light-mode .topbar-brand,
        :root.light-mode .topbar-user-name { color: #1a1d2e !important; }
        :root.light-mode .topbar-nav li a { color: #7c85a0 !important; }
        :root.light-mode .topbar-nav li a:hover { background: #e4e8f3 !important; color: #1a1d2e !important; }
        :root.light-mode .topbar-nav li.active a { background: rgba(74,143,255,.12) !important; color: #4a8fff !important; }
        :root.light-mode .topbar-greeting strong { color: #1a1d2e !important; }
        :root.light-mode .topbar-user { background: #ffffff !important; border-color: #dde1ed !important; }
        :root.light-mode .page { background: #f0f2f8 !important; }
        :root.light-mode .stat-card,
        :root.light-mode .chart-card,
        :root.light-mode .analytics-card,
        :root.light-mode .panel-card,
        :root.light-mode .settings-card,
        :root.light-mode .task-card,
        :root.light-mode .col { background: #ffffff !important; border-color: #dde1ed !important; }
        :root.light-mode .stat-value,
        :root.light-mode .stat-label,
        :root.light-mode .section-title,
        :root.light-mode .page-heading h1,
        :root.light-mode .chart-title,
        :root.light-mode .task-name { color: #1a1d2e !important; }
        :root.light-mode .stat-sub,
        :root.light-mode .task-meta,
        :root.light-mode .col-title { color: #7c85a0 !important; }
        :root.light-mode .section-header,
        :root.light-mode .task-row { border-bottom-color: #dde1ed !important; }
        :root.light-mode .task-row:hover { background: #f5f7fc !important; }
        :root.light-mode input,
        :root.light-mode select,
        :root.light-mode textarea { background: #f5f7fc !important; border-color: #dde1ed !important; color: #1a1d2e !important; }
        :root.light-mode .bar-label,
        :root.light-mode .legend-item,
        :root.light-mode .section-period,
        :root.light-mode .activity-title,
        :root.light-mode .top-task-name { color: #1a1d2e !important; }
    </style>
</head>
<body>
<script>(function(){if(window.self===window.top){window.location.replace('/Fini/shell.php?page=analytics');}}());</script>

<video autoplay muted loop playsinline class="bg-video">
    <source src="../assets/bg.mp4" type="video/mp4">
</video>

<!-- ───── TOPBAR ───── -->
<header class="topbar">

    <a href="/Fini/dashboard.php" class="topbar-brand">Fini.</a>
    <div class="topbar-divider"></div>

    <ul class="topbar-nav">
        <li>
            <a href="/Fini/dashboard.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                <span>Dashboard</span>
                <?php if ($incompleteTasks > 0): ?><span class="badge"><?= $incompleteTasks ?></span><?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/Fini/dashboard/mytasks.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                <span>My Tasks</span>
                <?php if ($incompleteTasks > 0): ?><span class="badge"><?= $incompleteTasks ?></span><?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/Fini/dashboard/calendar.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                <span>Calendar</span>
            </a>
        </li>
        <li class="active">
            <a href="#">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                <span>Analytics</span>
            </a>
        </li>
                <li>
            <a href="/Fini/dashboard/melodie.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
                <span>mélodie</span>
            </a>
        </li>
        <li>
            <a href="/Fini/dashboard/settings.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                <span>Settings</span>
            </a>
        </li>
    </ul>

    <div class="topbar-spacer"></div>

    <div class="topbar-greeting">
        <strong>Bonjour, <?= htmlspecialchars($username) ?><span>.</span></strong>
        <small>Here's your performance overview.</small>
    </div>

    <div class="topbar-divider"></div>

    <div class="topbar-actions">
        <button class="btn btn-ghost">This Week</button>
        <button class="btn btn-primary">
            <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            Export
        </button>
    </div>

    <div class="topbar-divider"></div>

    <a href="/Fini/dashboard/settings.php" class="topbar-user" title="My Profile" style="text-decoration:none;cursor:pointer;">
        <?php $_av = $_SESSION['avatar_data'] ?? ''; if ($_av): ?><div class="avatar" style="background-image:url(<?= htmlspecialchars($_av) ?>);background-size:cover;background-position:center;font-size:0;"></div><?php else: ?><div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div><?php endif; ?>
        <span class="topbar-user-name"><?= htmlspecialchars($username) ?></span>
    </a>

    <a href="/Fini/logout.php" class="logout-btn" title="Sign out">
        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
    </a>

</header>

<!-- ───── PAGE ───── -->
<div class="page">
    <div class="page-inner">

        <!-- STAT CARDS -->
        <div class="stats-row">

            <div class="stat-card">
                <div class="stat-label">Tasks Completed</div>
                <div class="stat-value">
                    <?= $completedThisWeek ?>
                    <?php if ($completedTrend > 0): ?>
                        <span class="stat-trend trend-up">↑ <?= $completedTrend ?>%</span>
                    <?php elseif ($completedTrend < 0): ?>
                        <span class="stat-trend trend-down">↓ <?= abs($completedTrend) ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="stat-sub"><span class="stat-dot" style="background:#52d68a"></span>vs last week</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Completion Rate</div>
                <div class="stat-value">
                    <?= $completionRate ?>%
                    <?php if ($completionTrend > 0): ?>
                        <span class="stat-trend trend-up">↑ <?= $completionTrend ?>%</span>
                    <?php elseif ($completionTrend < 0): ?>
                        <span class="stat-trend trend-down">↓ <?= abs($completionTrend) ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="stat-sub"><span class="stat-dot" style="background:#4a8fff"></span>Overall progress</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Tasks</div>
                <div class="stat-value"><?= $totalTasks ?></div>
                <div class="stat-sub"><span class="stat-dot" style="background:#f0a070"></span><?= $incompleteTasks ?> still open</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Overdue Rate</div>
                <div class="stat-value">
                    <?= $overdueRate ?>%
                    <?php if ($overdueRate === 0): ?>
                        <span class="stat-trend trend-up">All clear</span>
                    <?php else: ?>
                        <span class="stat-trend trend-down">↑ <?= $overdueCount ?> tasks</span>
                    <?php endif; ?>
                </div>
                <div class="stat-sub"><span class="stat-dot" style="background:#f07070"></span><?= $overdueCount ?> overdue</div>
            </div>

        </div>

        <!-- ROW 1: Bar chart + Line chart -->
        <div class="analytics-grid" style="margin-bottom:20px;">

            <div class="panel-card">
                <div class="panel-card-header">
                    <div class="panel-card-title">Tasks Completed — This Week</div>
                    <div class="panel-card-sub">Mon – Sun</div>
                </div>
                <div class="bar-chart" id="barChart"></div>
            </div>

            <div class="panel-card">
                <div class="panel-card-header">
                    <div class="panel-card-title">Cumulative Progress</div>
                    <div class="panel-card-sub"><?= $monthLabel ?></div>
                </div>
                <div class="line-chart-wrap">
                    <svg id="lineChart" viewBox="0 0 400 150" preserveAspectRatio="none"></svg>
                </div>
            </div>

        </div>

        <!-- ROW 2: Donut + Activity + Top tasks -->
        <div class="analytics-grid-3">

            <!-- Category breakdown donut -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <div class="panel-card-title">By Category</div>
                </div>
                <div class="donut-wrap">
                    <svg class="donut-svg" id="donutChart" width="110" height="110" viewBox="0 0 110 110"></svg>
                    <div class="donut-legend" id="donutLegend"></div>
                </div>
            </div>

            <!-- Activity feed — live from DB -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <div class="panel-card-title">Recent Activity</div>
                </div>
                <?php if (empty($activityItems)): ?>
                    <div style="font-size:.78rem;color:var(--muted);padding:10px 0">No activity yet.</div>
                <?php else: foreach ($activityItems as $item):
                    $ts     = strtotime($item['created_at']);
                    $diff   = time() - $ts;
                    $tLabel = $diff < 86400  ? 'Today, '     . date('g:i A', $ts)
                            : ($diff < 172800 ? 'Yesterday, ' . date('g:i A', $ts)
                            :                   date('M j, g:i A', $ts));
                    $st = $item['status'];
                    if ($st === 'done') {
                        $ibg = 'rgba(82,214,138,.15)'; $ic = '#52d68a';
                        $isvg = '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>';
                        $suffix = 'completed';
                    } elseif ($st === 'inprogress') {
                        $ibg = 'rgba(74,143,255,.15)'; $ic = '#4a8fff';
                        $isvg = '<path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>';
                        $suffix = 'in progress';
                    } else {
                        $ibg = 'rgba(240,160,112,.15)'; $ic = '#f0a070';
                        $isvg = '<path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>';
                        $suffix = 'added';
                    }
                ?>
                <div class="activity-item">
                    <div class="activity-icon" style="background:<?= $ibg ?>">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="<?= $ic ?>"><?= $isvg ?></svg>
                    </div>
                    <div class="activity-text">
                        <div class="activity-title"><?= htmlspecialchars($item['title']) ?> — <?= $suffix ?></div>
                        <div class="activity-time"><?= $tLabel ?></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Top tasks by effort — live from DB -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <div class="panel-card-title">Top Tasks by Effort</div>
                </div>
                <?php if (empty($topTasksData)): ?>
                    <div style="font-size:.78rem;color:var(--muted);padding:10px 0">No tasks yet.</div>
                <?php else: foreach ($topTasksData as $i => $t):
                    $tColor = $tagClassToHex[$t['tag_class'] ?? ''] ?? '#4a8fff';
                ?>
                <div class="top-task-item">
                    <div class="top-task-rank"><?= $i + 1 ?></div>
                    <div class="top-task-info">
                        <div class="top-task-name"><?= htmlspecialchars($t['title']) ?></div>
                        <div class="top-task-bar-bg">
                            <div class="top-task-bar-fill" style="width:<?= $t['effort_pct'] ?>%;background:<?= htmlspecialchars($tColor) ?>"></div>
                        </div>
                    </div>
                    <div class="top-task-pct"><?= $t['effort_pct'] ?>%</div>
                </div>
                <?php endforeach; endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
// ── Bar Chart — DB data via PHP ──
const barData = <?= $barDataJS ?>;
const barChart = document.getElementById('barChart');
const maxTotal = Math.max(...barData.map(d => d.total), 1);

barData.forEach(d => {
    const group = document.createElement('div');
    group.className = 'bar-group';
    group.style.position = 'relative';

    const wrap = document.createElement('div');
    wrap.className = 'bar-wrap';

    const barBg = document.createElement('div');
    barBg.className = 'bar';
    barBg.style.height = `${(d.total / maxTotal) * 100}%`;
    barBg.style.background = 'var(--subtle)';

    const barFg = document.createElement('div');
    barFg.className = 'bar';
    barFg.style.height = `${(d.completed / maxTotal) * 100}%`;
    barFg.style.background = 'var(--accent)';

    wrap.appendChild(barBg);
    wrap.appendChild(barFg);

    const label = document.createElement('div');
    label.className = 'bar-label';
    label.textContent = d.label;
    label.style.position = 'absolute';
    label.style.bottom = '4px';
    label.style.left = '50%';
    label.style.transform = 'translateX(-50%)';
    label.style.fontSize = '.62rem';
    label.style.color = 'var(--muted)';

    group.appendChild(wrap);
    group.appendChild(label);
    barChart.appendChild(group);
});

// ── Line Chart — DB data via PHP ──
const linePoints = <?= $lineDataJS ?>;
const maxVal = <?= $lineMax ?>;
const svgEl = document.getElementById('lineChart');
const W = 400, H = 150, PAD = 10;

for (let i = 0; i <= 4; i++) {
    const y = PAD + ((H - PAD * 2) / 4) * i;
    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('x1', 0); line.setAttribute('x2', W);
    line.setAttribute('y1', y); line.setAttribute('y2', y);
    line.setAttribute('stroke', '#252b38'); line.setAttribute('stroke-width', '1');
    svgEl.appendChild(line);
}

const pts = linePoints.map((v, i) => {
    const x = linePoints.length > 1 ? (i / (linePoints.length - 1)) * W : 0;
    const y = H - PAD - ((v / maxVal) * (H - PAD * 2));
    return `${x},${y}`;
});

const area = document.createElementNS('http://www.w3.org/2000/svg', 'path');
area.setAttribute('d', `M0,${H} L${pts.join(' L')} L${W},${H} Z`);
area.setAttribute('fill', 'rgba(74,143,255,0.08)');
svgEl.appendChild(area);

const path = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
path.setAttribute('points', pts.join(' '));
path.setAttribute('fill', 'none');
path.setAttribute('stroke', '#4a8fff');
path.setAttribute('stroke-width', '2');
path.setAttribute('stroke-linejoin', 'round');
path.setAttribute('stroke-linecap', 'round');
svgEl.appendChild(path);

linePoints.forEach((v, i) => {
    if (i > 0 && v > linePoints[i - 1]) {
        const x = (i / (linePoints.length - 1)) * W;
        const y = H - PAD - ((v / maxVal) * (H - PAD * 2));
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', x); circle.setAttribute('cy', y);
        circle.setAttribute('r', '3');
        circle.setAttribute('fill', '#4a8fff');
        circle.setAttribute('stroke', '#12141a');
        circle.setAttribute('stroke-width', '2');
        svgEl.appendChild(circle);
    }
});

// ── Donut Chart — DB data via PHP ──
const donutData  = <?= $donutDataJS ?>;
const donutTotal = <?= $donutTotalJS ?>;
const donutSvg   = document.getElementById('donutChart');
const cx = 55, cy = 55, r = 40, stroke = 16;
const circumference = 2 * Math.PI * r;
let offset = 0;

if (donutData.length === 0) {
    const emptyRing = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    emptyRing.setAttribute('cx', cx); emptyRing.setAttribute('cy', cy);
    emptyRing.setAttribute('r', r);
    emptyRing.setAttribute('fill', 'none');
    emptyRing.setAttribute('stroke', '#252b38');
    emptyRing.setAttribute('stroke-width', stroke);
    donutSvg.appendChild(emptyRing);
} else {
    donutData.forEach(d => {
        const dash = (d.pct / 100) * circumference;
        const gap  = circumference - dash;
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', cx); circle.setAttribute('cy', cy);
        circle.setAttribute('r', r);
        circle.setAttribute('fill', 'none');
        circle.setAttribute('stroke', d.color);
        circle.setAttribute('stroke-width', stroke);
        circle.setAttribute('stroke-dasharray', `${dash} ${gap}`);
        circle.setAttribute('stroke-dashoffset', -offset);
        circle.setAttribute('transform', `rotate(-90 ${cx} ${cy})`);
        circle.style.transition = 'stroke-dashoffset .3s';
        donutSvg.appendChild(circle);
        offset += dash;
    });
}

const centerText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
centerText.setAttribute('x', cx); centerText.setAttribute('y', cy - 4);
centerText.setAttribute('text-anchor', 'middle');
centerText.setAttribute('fill', '#e9eaf0');
centerText.setAttribute('font-size', '16');
centerText.setAttribute('font-family', 'Instrument Serif, serif');
centerText.textContent = donutTotal;
donutSvg.appendChild(centerText);

const subText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
subText.setAttribute('x', cx); subText.setAttribute('y', cy + 12);
subText.setAttribute('text-anchor', 'middle');
subText.setAttribute('fill', '#5c6478');
subText.setAttribute('font-size', '8');
subText.setAttribute('font-family', 'Outfit, sans-serif');
subText.textContent = 'tasks';
donutSvg.appendChild(subText);

const legend = document.getElementById('donutLegend');
if (donutData.length === 0) {
    legend.innerHTML = '<div style="font-size:.75rem;color:var(--muted)">No categories yet.</div>';
} else {
    donutData.forEach(d => {
        legend.innerHTML += `
            <div class="legend-item">
                <div class="legend-left">
                    <span class="legend-dot" style="background:${d.color}"></span>
                    ${d.label}
                </div>
                <span class="legend-pct">${d.pct}%</span>
            </div>`;
    });
}

// ── Period toggle ──
document.querySelector('.btn-ghost').addEventListener('click', function() {
    const periods = ['This Week', 'This Month', 'All Time'];
    const idx = periods.indexOf(this.textContent);
    this.textContent = periods[(idx + 1) % periods.length];
});
</script>

<script src="/Fini/nav-intercept.js"></script>
</body>
</html>