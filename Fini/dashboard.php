<?php
ob_start();
session_start();

// ── Step 5: Session guard ──
if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href="/login.php";</script>';
    exit();
}

$user_id  = (int) $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// ── Step 5: DB via shared config ──
require_once __DIR__ . '/public/database.config.php'; // FIX 2: was /../public which went above /Fini
// database.config.php defines $SERVER_NAME, $USERNAME, $PASSWORD, $DB_NAME
$db = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DB_NAME);
if ($db->connect_error) {
    $db = null; // gracefully degrade — no tasks will load
}

// ── Fetch tasks — Step 6: filtered by user_id ──
$tasks = ['todo' => [], 'inprogress' => [], 'done' => []];
$stats = ['total' => 0, 'inprogress' => 0, 'completed' => 0, 'overdue' => 0];
$upcoming = [];

if ($db) {
    $stmt = $db->prepare(
        "SELECT * FROM tasks WHERE user_id = ? ORDER BY due_date ASC, id ASC"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $status = $row['status']; // 'todo' | 'inprogress' | 'done'
        if (isset($tasks[$status])) $tasks[$status][] = $row;

        $stats['total']++;
        if ($status === 'inprogress') $stats['inprogress']++;
        if ($status === 'done')       $stats['completed']++;
        if ($row['due_date'] && $status !== 'done' && strtotime($row['due_date']) < time()) {
            $stats['overdue']++;
        }
        if ($status !== 'done' && $row['due_date'] && strtotime($row['due_date']) >= strtotime('today')) {
            $upcoming[] = $row;
        }
    }
    $stmt->close();
}

// Tag → dot color map for upcoming panel
$tagColors = [
    'tag-blue'   => '#4a8fff',
    'tag-orange' => '#f0a070',
    'tag-purple' => '#c4b0ff',
    'tag-red'    => '#f07070',
    'tag-green'  => '#52d68a',
];

// Helper to render a task card
function renderCard(array $row, bool $isDone): string {
    $title    = htmlspecialchars($row['title']);
    $tagClass = htmlspecialchars($row['tag_class'] ?? 'tag-blue');
    $tagLabel = htmlspecialchars($row['tag_label'] ?? 'Task');
    $dueStr   = $row['due_date'] ?? '';
    $id       = (int)$row['id'];
    $opacity  = $isDone ? ' style="opacity:.6"' : '';
    $due      = '';
    if ($dueStr) {
        $ts   = strtotime($dueStr);
        $diff = ($ts - strtotime('today')) / 86400;
        if ($diff < 0)          $due = '<span style="color:var(--danger)">' . date('M j', $ts) . '</span>';
        elseif (round($diff) === 0) $due = 'Today';
        else                    $due = date('M j', $ts);
    } else {
        $due = 'No due';
    }
    return <<<HTML
<div class="task-card" data-id="{$id}" data-due-str="{$dueStr}"{$opacity}>
    <div class="task-title">{$title}</div>
    <div class="task-meta"><span class="tag {$tagClass}">{$tagLabel}</span><span class="task-due">{$due}</span></div>
</div>
HTML;
}
?>
<!DOCTYPE html>
<html lang="en">
<script>
    (function() {
        if (localStorage.getItem('fini_theme') === 'light') {
            document.documentElement.classList.add('light-mode');
        }
    })();
</script>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fini — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .badge:empty, .badge[data-zero="true"] { display: none; }
        /* Flatpickr dark theme overrides */
        .flatpickr-calendar { background: #1e2130; border: 1px solid rgba(255,255,255,.1); border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,.5); font-family: 'Outfit', sans-serif; }
        .flatpickr-months .flatpickr-month, .flatpickr-weekdays, span.flatpickr-weekday { background: #1e2130; color: rgba(255,255,255,.7); }
        .flatpickr-day { color: rgba(255,255,255,.8); border-radius: 6px; }
        .flatpickr-day:hover { background: rgba(74,143,255,.25); border-color: transparent; }
        .flatpickr-day.selected, .flatpickr-day.selected:hover { background: #4a8fff; border-color: #4a8fff; color: #fff; }
        .flatpickr-day.today { border-color: #4a8fff; }
        .flatpickr-day.flatpickr-disabled { color: rgba(255,255,255,.2); }
        .flatpickr-current-month input.cur-year, .flatpickr-current-month .flatpickr-monthDropdown-months { color: #fff; background: transparent; }
        .flatpickr-prev-month svg, .flatpickr-next-month svg { fill: rgba(255,255,255,.7); }
        .flatpickr-prev-month:hover svg, .flatpickr-next-month:hover svg { fill: #fff; }
        .numInputWrapper span { border-color: rgba(255,255,255,.1); }
        .numInputWrapper span svg path { fill: rgba(255,255,255,.5); }
    
        /* ══ LIGHT MODE ══ */
        :root.light-mode {
            --bg:     #f0f2f8;
            --panel:  #ffffff;
            --panel2: #f5f7fc;
            --border: #dde1ed;
            --text:   #1a1d2e;
            --muted:  #7c85a0;
            --subtle: #e4e8f3;
        }
        :root.light-mode body { background: var(--bg); color: var(--text); }
        :root.light-mode .bg-video { opacity: 0.04; filter: invert(1) hue-rotate(180deg); }
        :root.light-mode .topbar { background: rgba(240,242,248,0.92); border-bottom-color: var(--border); }
        :root.light-mode .topbar-brand,
        :root.light-mode .topbar-user-name { color: var(--text); }
        :root.light-mode .topbar-nav li a { color: var(--muted); }
        :root.light-mode .topbar-nav li a:hover { background: var(--subtle); color: var(--text); }
        :root.light-mode .topbar-nav li.active a { background: rgba(74,143,255,.12); color: var(--accent); }
        :root.light-mode .topbar-greeting strong { color: var(--text); }
        :root.light-mode .topbar-user { background: var(--panel); border-color: var(--border); }
        /* panels & cards */
        :root.light-mode .settings-card,
        :root.light-mode .panel-card,
        :root.light-mode .stat-card,
        :root.light-mode .task-card,
        :root.light-mode .col,
        :root.light-mode .cal-sidebar,
        :root.light-mode .cal-main,
        :root.light-mode .settings-panel .settings-card { background: rgba(255,255,255,0.95); border-color: var(--border); }
        :root.light-mode .profile-hero-card { background: rgba(255,255,255,0.95); border-color: var(--border); }
        :root.light-mode .modal { background: var(--panel); border-color: var(--border); }
        :root.light-mode .modal-overlay { background: rgba(200,205,220,.5); }
        /* inputs & selects */
        :root.light-mode input,
        :root.light-mode select,
        :root.light-mode textarea,
        :root.light-mode .form-input,
        :root.light-mode .info-input,
        :root.light-mode .field input,
        :root.light-mode .field select { background: var(--panel2); border-color: var(--border); color: var(--text); }
        :root.light-mode input::placeholder,
        :root.light-mode textarea::placeholder { color: var(--muted); }
        /* nav items */
        :root.light-mode .settings-nav-item { color: var(--muted); }
        :root.light-mode .settings-nav-item:hover { background: var(--subtle); color: var(--text); }
        :root.light-mode .settings-nav-item.active { background: rgba(74,143,255,.12); color: var(--accent); }
        /* toggles */
        :root.light-mode .toggle-track { background: var(--subtle); }
        :root.light-mode .toggle input:checked + .toggle-track { background: rgba(74,143,255,.25); }
        /* task board columns */
        :root.light-mode .board-col,
        :root.light-mode .col-header { background: var(--panel2); }
        :root.light-mode .col-title { color: var(--text); }
        /* task menu */
        :root.light-mode .task-menu { background: var(--panel); border-color: var(--border); }
        :root.light-mode .task-menu-item { color: var(--text); }
        :root.light-mode .task-menu-item:hover { background: var(--subtle); }
        /* calendar */
        :root.light-mode .cal-day { background: var(--panel); border-color: var(--border); }
        :root.light-mode .cal-day:hover { background: var(--panel2); }
        :root.light-mode .cal-weekday { color: var(--muted); }
        :root.light-mode .mini-wd { color: var(--muted); }
        :root.light-mode .panel-card-title { color: var(--muted); }
        /* analytics */
        :root.light-mode .chart-wrap { background: var(--panel); border-color: var(--border); }
        /* buttons */
        :root.light-mode .btn-ghost,
        :root.light-mode .btn-sm { background: var(--panel2); border-color: var(--border); color: var(--text); }
        :root.light-mode .btn-ghost:hover,
        :root.light-mode .btn-sm:hover { background: var(--subtle); }
        /* misc */
        :root.light-mode .topbar-divider { background: var(--border); }
        :root.light-mode .settings-nav-divider { background: var(--border); }
        :root.light-mode .toggle-row { border-bottom-color: var(--border); }
        :root.light-mode .info-row { border-bottom-color: var(--border); }
        :root.light-mode .card-footer { border-top-color: var(--border); }
        :root.light-mode .logout-zone { background: rgba(240,112,112,.05); border-color: rgba(240,112,112,.2); }
        :root.light-mode .page-heading h1,
        :root.light-mode .modal h2 { color: var(--text); }
        :root.light-mode .badge { background: var(--subtle); color: var(--muted); }
        :root.light-mode .upcoming-item,
        :root.light-mode .event-item { border-color: var(--border); }
        :root.light-mode .section-header { border-bottom-color: var(--border); }
        :root.light-mode .task-row { border-bottom-color: var(--border); }
        :root.light-mode .task-row:hover { background: var(--panel2); }
        :root.light-mode .filter-panel { background: var(--panel); border-color: var(--border); }
        /* smooth transitions */
        *, *::before, *::after { transition: background-color .2s ease, border-color .2s ease, color .15s ease, box-shadow .2s ease !important; }

        </style>
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
    </style>
</head>
<body>
<script>(function(){if(window.self===window.top){window.location.replace('/shell.php?page=dashboard');}}());</script>

<video autoplay muted loop playsinline class="bg-video">
    <source src="assets/bg.mp4" type="video/mp4">
</video>

<!-- ───── TOPBAR ───── -->
<header class="topbar">

    <span class="topbar-brand">Fini.</span>
    <div class="topbar-divider"></div>

    <ul class="topbar-nav">
        <li class="active">
            <a href="dashboard.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                <span>Dashboard</span>
                <?php if (($stats['total'] - $stats['completed']) > 0): ?>
                <span class="badge"><?= $stats['total'] - $stats['completed'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/dashboard/mytasks.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                <span>My Tasks</span>
                <?php if (($stats['total'] - $stats['completed']) > 0): ?>
                <span class="badge"><?= $stats['total'] - $stats['completed'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/dashboard/calendar.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                <span>Calendar</span>
            </a>
        </li>
        <li>
            <a href="/dashboard/analytics.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                <span>Analytics</span>
            </a>
        </li>
                <li>
            <a href="/dashboard/melodie.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
                <span>Mélodie</span>
            </a>
        </li>
        <li>
            <a href="/dashboard/settings.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                <span>Settings</span>
            </a>
        </li>
    </ul>

    <div class="topbar-spacer"></div>

    <div class="topbar-greeting">
        <strong>Bonjour, <?= htmlspecialchars($username) ?><span>.</span></strong>
        <small>Here's what's on your plate today.</small>
    </div>

    <div class="topbar-divider"></div>

    <div class="topbar-actions">
        <div class="filter-wrapper">
            <button class="btn btn-ghost" id="filterBtn" style="position:relative">
                <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink:0"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L13 10.414V15a1 1 0 01-.553.894l-4 2A1 1 0 017 17v-6.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/></svg>
                Filter
                <span class="filter-count-badge" id="filterCountBadge"></span>
            </button>
            <div class="filter-panel" id="filterPanel">
                <div class="filter-panel-title">Filter Tasks</div>
                <div class="filter-section">
                    <div class="filter-section-label">By Tag</div>
                    <div class="filter-chips" id="filterTagChips">
                        <span class="filter-chip" data-tag="tag-blue"   data-color="blue">Design</span>
                        <span class="filter-chip" data-tag="tag-blue"   data-color="blue">Frontend</span>
                        <span class="filter-chip" data-tag="tag-orange" data-color="orange">Backend</span>
                        <span class="filter-chip" data-tag="tag-purple" data-color="purple">Pending</span>
                        <span class="filter-chip" data-tag="tag-green"  data-color="green">Done</span>
                        <span class="filter-chip" data-tag="tag-red"    data-color="red">Bug</span>
                    </div>
                </div>
                <div class="filter-section">
                    <div class="filter-section-label">By Status</div>
                    <div class="filter-chips" id="filterStatusChips">
                        <span class="filter-chip" data-status="todo"       data-color="status">To Do</span>
                        <span class="filter-chip" data-status="inprogress" data-color="status">In Progress</span>
                        <span class="filter-chip" data-status="done"       data-color="status">Done</span>
                    </div>
                </div>
                <div class="filter-footer">
                    <button class="filter-clear" id="filterClear">Clear all</button>
                    <button class="btn btn-primary btn-sm filter-apply" id="filterApply">Apply</button>
                </div>
            </div>
        </div>
        <button class="btn btn-primary" data-action="new-task">
            <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
            New Task
        </button>
    </div>

    <div class="topbar-divider"></div>

    <a href="/dashboard/settings.php" class="topbar-user" title="My Profile" style="text-decoration:none;cursor:pointer;">
        <?php $_av = $_SESSION['avatar_data'] ?? ''; if ($_av): ?><div class="avatar" style="background-image:url(<?= htmlspecialchars($_av) ?>);background-size:cover;background-position:center;font-size:0;"></div><?php else: ?><div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div><?php endif; ?>
        <span class="topbar-user-name"><?= htmlspecialchars($username) ?></span>
    </a>

    <a href="/logout.php" class="logout-btn" title="Sign out">
        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
    </a>

</header>

<!-- ───── PAGE ───── -->
<div class="page">
    <div class="page-inner">

        <!-- stat cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Total Tasks</div>
                <div class="stat-value" id="statTotal"><?= $stats['total'] ?></div>
                <div class="stat-sub"><span class="stat-dot" style="background:#4a8fff"></span>All active tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">In Progress</div>
                <div class="stat-value" id="statProgress"><?= $stats['inprogress'] ?></div>
                <div class="stat-sub"><span class="stat-dot" style="background:#f0a070"></span>Currently working</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed</div>
                <div class="stat-value" id="statCompleted"><?= $stats['completed'] ?></div>
                <div class="stat-sub"><span class="stat-dot" style="background:#52d68a"></span>This week</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Overdue</div>
                <div class="stat-value" id="statOverdue"><?= $stats['overdue'] ?></div>
                <div class="stat-sub"><span class="stat-dot" style="background:#f07070"></span>Needs attention</div>
            </div>
        </div>

        <!-- task board + right panel -->
        <div class="content-grid">

            <!-- TASK BOARD -->
            <div>
                <div class="board-header">
                    <div class="board-title">Task Board</div>
                </div>

                <div class="columns">

                    <!-- TO DO -->
                    <div class="col">
                        <div class="col-header">
                            <div class="col-title"><span class="col-dot" style="background:#5c6478"></span>To Do</div>
                            <span class="col-count"><?= count($tasks['todo']) ?></span>
                        </div>
                        <div class="col-empty"<?= !empty($tasks['todo']) ? ' style="display:none"' : '' ?>>No tasks yet</div>
                        <?php foreach ($tasks['todo'] as $row): echo renderCard($row, false); endforeach; ?>
                        <button class="add-task-btn">+ Add task</button>
                    </div>

                    <!-- IN PROGRESS -->
                    <div class="col">
                        <div class="col-header">
                            <div class="col-title"><span class="col-dot" style="background:#f0a070"></span>In Progress</div>
                            <span class="col-count"><?= count($tasks['inprogress']) ?></span>
                        </div>
                        <div class="col-empty"<?= !empty($tasks['inprogress']) ? ' style="display:none"' : '' ?>>No tasks yet</div>
                        <?php foreach ($tasks['inprogress'] as $row): echo renderCard($row, false); endforeach; ?>
                        <button class="add-task-btn">+ Add task</button>
                    </div>

                    <!-- DONE -->
                    <div class="col">
                        <div class="col-header">
                            <div class="col-title"><span class="col-dot" style="background:#52d68a"></span>Done</div>
                            <span class="col-count"><?= count($tasks['done']) ?></span>
                        </div>
                        <div class="col-empty"<?= !empty($tasks['done']) ? ' style="display:none"' : '' ?>>No tasks yet</div>
                        <?php foreach ($tasks['done'] as $row): echo renderCard($row, true); endforeach; ?>
                        <button class="add-task-btn">+ Add task</button>
                    </div>

                </div>
            </div>

            <!-- RIGHT PANEL -->
            <div class="right-panel">

                <div class="panel-card">
                    <div class="panel-card-title">Progress</div>
                    <?php
                    $total = $stats['total'];
                    $done  = $stats['completed'];
                    $overallPct = $total > 0 ? round($done / $total * 100) : 0;

                    // Per-tag progress — filtered by user_id
                    $tagProgress = [];
                    if ($db) {
                        $stmt2 = $db->prepare(
                            "SELECT tag_label, COUNT(*) as cnt, SUM(status='done') as done_cnt
                             FROM tasks
                             WHERE user_id = ?
                             GROUP BY tag_label"
                        );
                        $stmt2->bind_param('i', $user_id);
                        $stmt2->execute();
                        $res = $stmt2->get_result();
                        while ($r = $res->fetch_assoc()) {
                            $pct = $r['cnt'] > 0 ? round($r['done_cnt'] / $r['cnt'] * 100) : 0;
                            $tagProgress[] = ['label' => $r['tag_label'], 'pct' => $pct];
                        }
                        $stmt2->close();
                    }
                    $barColors = ['#4a8fff','#52d68a','#f0a070','#c4b0ff','#f07070'];
                    $ci = 0;
                    ?>
                    <div class="progress-item">
                        <div class="progress-label"><span>Overall</span><span class="progress-pct"><?= $overallPct ?>%</span></div>
                        <div class="progress-bar"><div class="progress-fill" style="width:<?= $overallPct ?>%; background:#4a8fff"></div></div>
                    </div>
                    <?php if (empty($tagProgress)): ?>
                    <div style="font-size:.78rem;color:var(--muted);margin-top:8px">No category data yet.</div>
                    <?php else: foreach ($tagProgress as $tp):
                        $color = $barColors[$ci % count($barColors)]; $ci++;
                    ?>
                    <div class="progress-item">
                        <div class="progress-label"><span><?= htmlspecialchars($tp['label']) ?></span><span class="progress-pct"><?= $tp['pct'] ?>%</span></div>
                        <div class="progress-bar"><div class="progress-fill" style="width:<?= $tp['pct'] ?>%; background:<?= $color ?>"></div></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <div class="panel-card">
                    <div class="panel-card-title">Upcoming</div>
                    <?php if (empty($upcoming)): ?>
                    <div style="font-size:.78rem;color:var(--muted)">No upcoming tasks.</div>
                    <?php else: foreach (array_slice($upcoming, 0, 5) as $u):
                        $dotColor = $tagColors[$u['tag_class'] ?? ''] ?? '#5c6478';
                        $dateLabel = date('M j, Y', strtotime($u['due_date']));
                    ?>
                    <div class="upcoming-item" data-id="<?= $u['id'] ?>">
                        <div class="upcoming-dot" style="background:<?= $dotColor ?>"></div>
                        <div class="upcoming-text">
                            <div class="upcoming-title"><?= htmlspecialchars($u['title']) ?></div>
                            <div class="upcoming-date"><?= $dateLabel ?></div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

            </div>

        </div>

    </div>
</div>

<!-- ───── NEW TASK MODAL ───── -->
<div class="modal-overlay" id="taskModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">New Task</span>
            <button class="modal-close" id="modalClose" aria-label="Close">✕</button>
        </div>

        <div class="field">
            <label for="taskTitle">Task Title</label>
            <input type="text" id="taskTitle" placeholder="e.g. Design onboarding flow" autocomplete="off">
        </div>

        <div class="field">
            <label for="taskDesc">Description <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)">(optional)</span></label>
            <textarea id="taskDesc" placeholder="Add a short description…"></textarea>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="taskStatus">Status</label>
                <select id="taskStatus">
                    <option value="todo">To Do</option>
                    <option value="inprogress">In Progress</option>
                    <option value="done">Done</option>
                </select>
            </div>
            <div class="field">
                <label for="taskTag">Category</label>
                <select id="taskTag">
                    <option value="tag-blue|Frontend">Frontend</option>
                    <option value="tag-orange|Backend">Backend</option>
                    <option value="tag-blue|Design">Design</option>
                    <option value="tag-purple|Docs">Docs</option>
                    <option value="tag-green|Done">Done</option>
                    <option value="tag-red|Urgent">Urgent</option>
                </select>
            </div>
        </div>

        <div class="field">
            <label for="taskDue">Due Date</label>
            <input type="text" id="taskDue" placeholder="Select date..." readonly>
        </div>

        <div class="modal-footer">
            <button class="btn btn-ghost btn-sm" id="modalCancel">Cancel</button>
            <button class="btn btn-primary btn-sm" id="modalCreate">
                <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                Create Task
            </button>
        </div>
    </div>
</div>

<!-- ───── EDIT TASK MODAL ───── -->
<div class="modal-overlay" id="editModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
        <div class="modal-header">
            <span class="modal-title" id="editModalTitle">Edit Task</span>
            <button class="modal-close" id="editModalClose" aria-label="Close">✕</button>
        </div>
        <div class="field">
            <label for="editTaskTitle">Task Title</label>
            <input type="text" id="editTaskTitle" placeholder="Task title" autocomplete="off">
        </div>
        <div class="field">
            <label for="editTaskDesc">Description <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted)">(optional)</span></label>
            <textarea id="editTaskDesc" placeholder="Add a short description…"></textarea>
        </div>
        <div class="field-row">
            <div class="field">
                <label for="editTaskStatus">Status</label>
                <select id="editTaskStatus">
                    <option value="todo">To Do</option>
                    <option value="inprogress">In Progress</option>
                    <option value="done">Done</option>
                </select>
            </div>
            <div class="field">
                <label for="editTaskTag">Category</label>
                <select id="editTaskTag">
                    <option value="tag-blue|Frontend">Frontend</option>
                    <option value="tag-orange|Backend">Backend</option>
                    <option value="tag-blue|Design">Design</option>
                    <option value="tag-purple|Docs">Docs</option>
                    <option value="tag-green|Done">Done</option>
                    <option value="tag-red|Urgent">Urgent</option>
                </select>
            </div>
        </div>
        <div class="field">
            <label for="editTaskDue">Due Date</label>
            <input type="text" id="editTaskDue" placeholder="Select date..." readonly>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost btn-sm" id="editModalCancel">Cancel</button>
            <button class="btn btn-primary btn-sm" id="editModalSave">
                <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 010 2.828l-10 10A2 2 0 016 16H4a1 1 0 01-1-1v-2a2 2 0 01.586-1.414l10-10a2 2 0 012.828 0z"/></svg>
                Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ───── TASK CONTEXT MENU ───── -->
<div class="task-menu" id="taskContextMenu" style="display:none">
    <div class="task-menu-item" id="menuEdit">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 010 2.828l-10 10A2 2 0 016 16H4a1 1 0 01-1-1v-2a2 2 0 01.586-1.414l10-10a2 2 0 012.828 0z"/></svg>
        Edit Task
    </div>
    <div class="task-menu-divider"></div>
    <div class="task-menu-item danger" id="menuDelete">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        Delete Task
    </div>
</div>

<script>


    // ── Modal helpers ──
    const overlay   = document.getElementById('taskModal');
    const titleEl   = document.getElementById('taskTitle');
    const descEl    = document.getElementById('taskDesc');
    const statusEl  = document.getElementById('taskStatus');
    const tagEl     = document.getElementById('taskTag');
    const dueEl     = document.getElementById('taskDue');

    const colMap = { todo: 0, inprogress: 1, done: 2 };
    const colEls = document.querySelectorAll('.col');
    const totalEl     = document.getElementById('statTotal');
    const progressEl  = document.getElementById('statProgress');
    const completedEl = document.getElementById('statCompleted');
    const overdueEl   = document.getElementById('statOverdue');

    // Recount everything from the DOM — called after any create/edit/delete
    function recomputeStats() {
        const today = new Date(); today.setHours(0,0,0,0);
        let total = 0, inprog = 0, done = 0, overdue = 0;

        colEls.forEach((col, colIdx) => {
            const cards = col.querySelectorAll('.task-card');
            const count = cards.length;

            // update col header count
            col.querySelector('.col-count').textContent = count;

            // show/hide "No tasks yet" placeholder
            const emptyEl = col.querySelector('.col-empty');
            if (emptyEl) emptyEl.style.display = count === 0 ? '' : 'none';

            cards.forEach(card => {
                total++;
                if (colIdx === 1) inprog++;
                if (colIdx === 2) done++;
                const due = card.dataset.dueStr;
                if (due && colIdx !== 2) {
                    const d = new Date(due + 'T00:00:00');
                    if (d < today) overdue++;
                }
            });
        });

        totalEl.textContent     = total;
        progressEl.textContent  = inprog;
        completedEl.textContent = done;
        overdueEl.textContent   = overdue;

        // update nav badge
        const navBadge = document.querySelector('.topbar-nav .badge');
        if (navBadge) navBadge.textContent = total - done;
    }

    let pendingStatus = null;

    function openModal(presetStatus) {
        pendingStatus = presetStatus || null;
        if (pendingStatus) statusEl.value = pendingStatus;
        dueEl.value = new Date().toISOString().slice(0,10);
        overlay.classList.add('open');
        setTimeout(() => titleEl.focus(), 80);
    }

    function closeModal() {
        overlay.classList.remove('open');
        titleEl.value  = '';
        descEl.value   = '';
        statusEl.value = 'todo';
        tagEl.value    = 'tag-blue|Frontend';
        dueEl.value    = '';
        pendingStatus  = null;
    }

    function formatDue(dateStr) {
        if (!dateStr) return 'No due';
        const d   = new Date(dateStr + 'T00:00:00');
        const now = new Date(); now.setHours(0,0,0,0);
        const diff = Math.round((d - now) / 86400000);
        if (diff < 0)  return `<span style="color:var(--danger)">${d.toLocaleDateString('en-US',{month:'short',day:'numeric'})}</span>`;
        if (diff === 0) return 'Today';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    // ── Create task (AJAX → api/tasks.php) ──
    async function createTask() {
        const title = titleEl.value.trim();
        if (!title) { titleEl.focus(); titleEl.style.borderColor = 'var(--danger)'; return; }
        titleEl.style.borderColor = '';

        const status            = statusEl.value;
        const [tagClass, tagLabel] = tagEl.value.split('|');
        const dueStr            = dueEl.value;

        const res  = await fetch('/api/Tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', title, status, tag_class: tagClass, tag_label: tagLabel, due_date: dueStr })
        });
        const data = await res.json();
        if (!data.success) { alert(data.error || 'Could not create task.'); return; }

        const colIdx = colMap[status];
        const col    = colEls[colIdx];
        const addBtn = col.querySelector('.add-task-btn');
        const isDone = status === 'done';

        const card = document.createElement('div');
        card.className = 'task-card';
        card.dataset.id     = data.id;
        card.dataset.dueStr = dueStr;
        if (isDone) card.style.opacity = '.6';
        card.innerHTML = `
            <div class="task-title">${escHtml(title)}</div>
            <div class="task-meta">
                <span class="tag ${tagClass}">${escHtml(tagLabel)}</span>
                <span class="task-due">${formatDue(dueStr)}</span>
            </div>`;
        attachCardListener(card);
        col.insertBefore(card, addBtn);
        const emptyEl = col.querySelector('.col-empty');
        if (emptyEl) emptyEl.style.display = 'none';

        if (dueStr && status !== 'done') addUpcoming(title, dueStr, tagClass, data.id);
        recomputeStats();

        closeModal();
    }

    function addUpcoming(title, dueStr, tagClass, taskId) {
        const colorMap = { 'tag-blue':'#4a8fff','tag-orange':'#f0a070','tag-purple':'#c4b0ff','tag-red':'#f07070','tag-green':'#52d68a' };
        const dotColor  = colorMap[tagClass] || '#5c6478';
        const dateLabel = new Date(dueStr + 'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
        const upcomingList = document.querySelector('.panel-card:last-child');
        const item = document.createElement('div');
        item.className = 'upcoming-item';
        if (taskId) item.dataset.id = taskId;
        item.innerHTML = `
            <div class="upcoming-dot" style="background:${dotColor}"></div>
            <div class="upcoming-text">
                <div class="upcoming-title">${escHtml(title)}</div>
                <div class="upcoming-date">${dateLabel}</div>
            </div>`;
        upcomingList.appendChild(item);
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Context Menu ──
    const ctxMenu    = document.getElementById('taskContextMenu');
    let   activeCard = null;

    function showMenu(card, x, y) {
        activeCard = card;
        ctxMenu.style.display = 'block';
        const mw = 170, mh = 90;
        const vw = window.innerWidth, vh = window.innerHeight;
        ctxMenu.style.left = (x + mw > vw ? vw - mw - 8 : x) + 'px';
        ctxMenu.style.top  = (y + mh > vh ? y - mh : y) + 'px';
    }

    function hideMenu() { ctxMenu.style.display = 'none'; activeCard = null; }
    function hideMenuOnly() { ctxMenu.style.display = 'none'; } // hide without clearing activeCard

    function attachCardListener(card) {
        card.addEventListener('click', e => {
            e.stopPropagation();
            const rect = card.getBoundingClientRect();
            showMenu(card, rect.left, rect.bottom + 4);
        });
    }

    document.querySelectorAll('.task-card').forEach(attachCardListener);
    document.addEventListener('click', () => hideMenu());
    document.addEventListener('keydown', e => { if (e.key === 'Escape') hideMenu(); });

    // ── Delete (AJAX) ──
    document.getElementById('menuDelete').addEventListener('click', async (e) => {
        e.stopPropagation(); // prevent document click from firing hideMenu() first
        if (!activeCard) return;

        // Snapshot card + col immediately — activeCard may be cleared by hideMenu later
        const cardToDelete = activeCard;
        const col          = cardToDelete.closest('.col');
        const id           = cardToDelete.dataset.id;

        hideMenu(); // close menu right away (sets activeCard = null — that's fine now)

        if (id) {
            const res  = await fetch('/api/Tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: parseInt(id) })
            });
            const data = await res.json();
            if (!data.success) { alert(data.error || 'Could not delete task.'); return; }
        }

        cardToDelete.style.transition = 'opacity .2s, transform .2s';
        cardToDelete.style.opacity    = '0';
        cardToDelete.style.transform  = 'scale(.95)';
        setTimeout(() => {
            cardToDelete.remove();
            // also remove from upcoming panel
            const upcomingItem = document.querySelector(`.upcoming-item[data-id="${id}"]`);
            if (upcomingItem) upcomingItem.remove();
            recomputeStats();
        }, 220);
    });

    // ── Edit ──
    const editOverlay  = document.getElementById('editModal');
    const editTitleEl  = document.getElementById('editTaskTitle');
    const editDescEl   = document.getElementById('editTaskDesc');
    const editStatusEl = document.getElementById('editTaskStatus');
    const editTagEl    = document.getElementById('editTaskTag');
    const editDueEl    = document.getElementById('editTaskDue');

    function openEditModal(card) {
        activeCard = card;
        editOverlay.dataset.activeCardId = card.dataset.id;
        const currentTitle = card.querySelector('.task-title').textContent;
        const currentTag   = card.querySelector('.tag');
        const currentDue   = card.dataset.dueStr || '';

        const col     = card.closest('.col');
        const colIdx  = Array.from(colEls).indexOf(col);
        const statuses = ['todo','inprogress','done'];
        editStatusEl.value = statuses[colIdx] || 'todo';

        const tagClasses = ['tag-blue','tag-orange','tag-purple','tag-green','tag-red'];
        let tagClass = '';
        tagClasses.forEach(c => { if (currentTag && currentTag.classList.contains(c)) tagClass = c; });
        const tagLabel = currentTag ? currentTag.textContent.trim() : 'Frontend';
        const tagVal   = tagClass + '|' + tagLabel;

        let matched = false;
        Array.from(editTagEl.options).forEach(o => {
            if (o.value === tagVal || o.value.split('|')[1] === tagLabel) {
                editTagEl.value = o.value; matched = true;
            }
        });
        if (!matched) editTagEl.value = editTagEl.options[0].value;

        editTitleEl.value = currentTitle;
        editDescEl.value  = '';
        if (window._fpEdit) window._fpEdit.setDate(currentDue || null, false);
        else editDueEl.value = currentDue;

        editOverlay.classList.add('open');
        setTimeout(() => editTitleEl.focus(), 80);
    }

    function closeEditModal() { editOverlay.classList.remove('open'); }

    async function saveEdit() {
        const cardId = editOverlay.dataset.activeCardId;
        const card = cardId ? document.querySelector(`.task-card[data-id="${cardId}"]`) : null;
        if (!card) { closeEditModal(); return; }

        const title = editTitleEl.value.trim();
        if (!title) { editTitleEl.focus(); editTitleEl.style.borderColor = 'var(--danger)'; return; }
        editTitleEl.style.borderColor = '';

        const newStatus = editStatusEl.value;
        const [newTagClass, newTagLabel] = editTagEl.value.split('|');
        const isDone = newStatus === 'done';
        const id = card.dataset.id;

        // Get date from flatpickr alt input (the visible input flatpickr creates)
        const altInput = document.querySelector('#editTaskDue + input.flatpickr-input');
        let newDue = '';
        if (altInput && altInput.value) {
            // altInput shows dd/mm/yyyy, convert to yyyy-mm-dd for DB
            const parts = altInput.value.split('/');
            if (parts.length === 3) {
                newDue = parts[2] + '-' + parts[1] + '-' + parts[0];
            }
        }
        // fallback to hidden input
        if (!newDue) newDue = document.getElementById('editTaskDue').value;

        if (id) {
                try {
                const res = await fetch('/api/Tasks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update', id, title, status: newStatus, tag_class: newTagClass, tag_label: newTagLabel, due_date: newDue })
                });
                const data = await res.json();
                if (!data.success) { alert(data.error || 'Could not update task.'); return; }
            } catch(err) {
                alert('Network error: ' + err.message); return;
            }
        }

        card.querySelector('.task-title').textContent = title;
        const tagSpan = card.querySelector('.tag');
        tagSpan.className   = 'tag ' + newTagClass;
        tagSpan.textContent = newTagLabel;
        card.querySelector('.task-due').innerHTML = formatDue(newDue);
        card.dataset.dueStr = newDue;
        card.style.opacity  = isDone ? '.6' : '1';

        const currentColIdx = Array.from(colEls).indexOf(card.closest('.col'));
        const newColIdx     = colMap[newStatus];
        if (currentColIdx !== newColIdx) {
            const targetCol = colEls[newColIdx];
            const addBtn    = targetCol.querySelector('.add-task-btn');
            if (addBtn) targetCol.insertBefore(card, addBtn);
            else        targetCol.appendChild(card);
        }

        const upcomingItem = document.querySelector(`.upcoming-item[data-id="${id}"]`);
        if (upcomingItem) {
            if (newDue && !isDone) {
                upcomingItem.querySelector('.upcoming-title').textContent = title;
                upcomingItem.querySelector('.upcoming-date').textContent =
                    new Date(newDue + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            } else {
                upcomingItem.remove();
            }
        } else if (newDue && !isDone) {
            addUpcoming(title, newDue, newTagClass, id);
        }

        recomputeStats();
        closeEditModal();
        hideMenu();
    }

    document.getElementById('menuEdit').addEventListener('click', (e) => {
        e.stopPropagation();
        if (!activeCard) return;
        const card = activeCard;
        ctxMenu.style.display = 'none';
        openEditModal(card);
    });

    document.getElementById('editModalClose').addEventListener('click', closeEditModal);
    document.getElementById('editModalCancel').addEventListener('click', closeEditModal);
    editOverlay.addEventListener('click', e => { if (e.target === editOverlay) closeEditModal(); });
    document.getElementById('editModalSave').addEventListener('click', (e) => {
        e.stopPropagation();
        saveEdit();
    });
    editTitleEl.addEventListener('input', () => editTitleEl.style.borderColor = '');
    editOverlay.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey && document.activeElement !== editDescEl) { e.preventDefault(); saveEdit(); }
        if (e.key === 'Escape') closeEditModal();
    });

    // ── Filter ──
    const filterBtn        = document.getElementById('filterBtn');
    const filterPanel      = document.getElementById('filterPanel');
    const filterClear      = document.getElementById('filterClear');
    const filterApply      = document.getElementById('filterApply');
    const filterCountBadge = document.getElementById('filterCountBadge');

    let activeTagFilters    = new Set();
    let activeStatusFilters = new Set();

    filterBtn.addEventListener('click', e => { e.stopPropagation(); filterPanel.classList.toggle('open'); });
    document.addEventListener('click', e => { if (!filterPanel.contains(e.target) && e.target !== filterBtn) filterPanel.classList.remove('open'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') filterPanel.classList.remove('open'); });

    document.querySelectorAll('#filterTagChips .filter-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const label = chip.textContent.trim();
            if (activeTagFilters.has(label)) { activeTagFilters.delete(label); chip.className = 'filter-chip'; }
            else { activeTagFilters.add(label); chip.className = 'filter-chip selected-' + chip.dataset.color; }
        });
    });

    document.querySelectorAll('#filterStatusChips .filter-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const status = chip.dataset.status;
            if (activeStatusFilters.has(status)) { activeStatusFilters.delete(status); chip.className = 'filter-chip'; }
            else { activeStatusFilters.add(status); chip.className = 'filter-chip selected-status'; }
        });
    });

    filterClear.addEventListener('click', () => {
        activeTagFilters.clear(); activeStatusFilters.clear();
        document.querySelectorAll('.filter-chip').forEach(c => c.className = 'filter-chip');
        applyFilters();
    });

    filterApply.addEventListener('click', () => { applyFilters(); filterPanel.classList.remove('open'); });

    function applyFilters() {
        const totalActive = activeTagFilters.size + activeStatusFilters.size;
        filterCountBadge.textContent = totalActive;
        filterCountBadge.classList.toggle('visible', totalActive > 0);
        filterBtn.classList.toggle('filter-active', totalActive > 0);

        const statusColMap = { todo: 0, inprogress: 1, done: 2 };
        document.querySelectorAll('.task-card').forEach(card => {
            const tagEl    = card.querySelector('.tag');
            const tagLabel = tagEl ? tagEl.textContent.trim() : '';
            const col      = card.closest('.col');
            const colIdx   = Array.from(colEls).indexOf(col);
            const colStatus = Object.keys(statusColMap).find(k => statusColMap[k] === colIdx);
            const tagMatch    = activeTagFilters.size === 0 || activeTagFilters.has(tagLabel);
            const statusMatch = activeStatusFilters.size === 0 || activeStatusFilters.has(colStatus);
            card.style.display = (tagMatch && statusMatch) ? '' : 'none';
        });
    }

    // ── Topbar buttons ──
    document.querySelector('.btn-primary[data-action="new-task"]').addEventListener('click', () => openModal());
    document.getElementById('modalClose').addEventListener('click', closeModal);
    document.getElementById('modalCancel').addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
    document.getElementById('modalCreate').addEventListener('click', createTask);
    overlay.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey && document.activeElement !== descEl) { e.preventDefault(); createTask(); }
        if (e.key === 'Escape') closeModal();
    });
    titleEl.addEventListener('input', () => titleEl.style.borderColor = '');

    document.querySelectorAll('.add-task-btn').forEach((btn, i) => {
        const statuses = ['todo', 'inprogress', 'done'];
        btn.addEventListener('click', () => openModal(statuses[i]));
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // Flatpickr for new task modal
    const fpNew = flatpickr('#taskDue', {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: false,
        appendTo: document.getElementById('taskModal'),
    });

    // Flatpickr for edit task modal — appendTo keeps it inside the modal so overlay clicks don't fire
    const fpEdit = flatpickr('#editTaskDue', {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: false,
        disableMobile: true,
        onChange(selectedDates, dateStr, instance) {
            document.getElementById('editTaskDue').value = dateStr;
            instance.close();
        }
    });

    // Expose fpEdit so openEditModal can set its date
    window._fpEdit = fpEdit;
    window._fpNew  = fpNew;
</script>

<script src="/nav-intercept.js"></script>
</body>
</html>
