<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href="/login.php";</script>';
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$user_id  = (int) $_SESSION['user_id'];
$today    = date('Y-m-d');

require_once __DIR__ . '/../public/database.config.php';
$db = $conn;

// ── Counts ──────────────────────────────────────────────
$countAll = $countProgress = $countPending = $countOverdue = 0;
$tasks = ['overdue' => [], 'inprogress' => [], 'todo' => []];

if (!$db->connect_error) {
    // Total active tasks (for nav badge)
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'done'");
    $s->bind_param('i', $user_id); $s->execute(); $s->bind_result($countAll); $s->fetch(); $s->close();

    // In-progress
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'inprogress'");
    $s->bind_param('i', $user_id); $s->execute(); $s->bind_result($countProgress); $s->fetch(); $s->close();

    // Pending (not started, not overdue)
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'todo' AND (due_date IS NULL OR due_date >= ?)");
    $s->bind_param('is', $user_id, $today); $s->execute(); $s->bind_result($countPending); $s->fetch(); $s->close();

    // Overdue (due_date < today and not done)
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'done' AND due_date < ?");
    $s->bind_param('is', $user_id, $today); $s->execute(); $s->bind_result($countOverdue); $s->fetch(); $s->close();

    // ── Fetch task rows ──────────────────────────────────
    $s = $db->prepare("SELECT id, title, tag_label, tag_class, due_date, status FROM tasks WHERE user_id = ? AND status != 'done' ORDER BY due_date ASC");
    $s->bind_param('i', $user_id);
    $s->execute();
    $res = $s->get_result();
    while ($row = $res->fetch_assoc()) {
        $due = $row['due_date'];
        if ($due && $due < $today && $row['status'] !== 'done') {
            $tasks['overdue'][] = $row;
        } elseif ($row['status'] === 'inprogress') {
            $tasks['inprogress'][] = $row;
        } else {
            $tasks['todo'][] = $row;
        }
    }
    $s->close();
}

// ── AJAX handlers ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$db->connect_error) {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $id   = (int)($_POST['id']   ?? 0);
        $done = (int)($_POST['done'] ?? 0);
        $newStatus = $done ? 'done' : 'todo';
        $s = $db->prepare("UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?");
        $s->bind_param('sii', $newStatus, $id, $user_id);
        $s->execute(); $s->close();
        echo json_encode(['ok' => true]); ob_end_flush(); exit();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $s  = $db->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        $s->bind_param('ii', $id, $user_id);
        $s->execute(); $s->close();
        echo json_encode(['ok' => true]); ob_end_flush(); exit();
    }

    if ($action === 'add') {
        $title     = trim($_POST['title']     ?? '');
        $tag_label = trim($_POST['tag_label'] ?? '');
        $tag = trim($_POST['tag'] ?? 'tag-blue');
        $priority  = trim($_POST['priority']  ?? 'medium');
        $due_date  = trim($_POST['due_date']  ?? '') ?: null;
        if ($title === '') { echo json_encode(['ok'=>false,'msg'=>'Title required']); ob_end_flush(); exit(); }
        $s = $db->prepare("INSERT INTO tasks (user_id, title, tag_label, tag_class, due_date, status) VALUES (?,?,?,?,?,'todo')");
        $s->bind_param('issss', $user_id, $title, $tag_label, $tag, $due_date);
        $s->execute();
        $newId = $db->insert_id;
        $s->close();
        echo json_encode(['ok'=>true,'id'=>$newId]); ob_end_flush(); exit();
    }
}

// ── Task row helper ──────────────────────────────────────
function taskRow(array $t, string $today): string {
    $id       = (int)$t['id'];
    $title    = htmlspecialchars($t['title']);
    $label    = htmlspecialchars($t['tag_label'] ?? '');
    $color    = htmlspecialchars($t['tag_class']  ?? 'tag-blue');
    $pri      = htmlspecialchars($t['priority']   ?? 'medium');
    $priLabel = ucfirst($pri);
    $due      = $t['due_date'];
    $dueLabel = $due ? date('M j, Y', strtotime($due)) : '—';
    $dueCss   = '';
    if ($due) { $dueCss = ($due === $today) ? 'today' : ($due < $today ? 'overdue' : ''); }
    $checked  = $t['status'] === 'done' ? ' checked' : '';
    $nameClass = $t['status'] === 'done' ? ' done' : '';

    $editIcon   = '<svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>';
    $deleteIcon = '<svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zm-1 7a1 1 0 012 0v4a1 1 0 11-2 0V9zm4 0a1 1 0 012 0v4a1 1 0 11-2 0V9z" clip-rule="evenodd"/></svg>';

    return <<<HTML
<div class="task-row" data-id="{$id}" data-status="{$t['status']}" data-priority="{$pri}" data-due="{$due}">
    <div class="task-check{$checked}" onclick="toggleCheck(this, {$id})"></div>
    <div class="task-name-cell"><span class="task-name{$nameClass}">{$title}</span></div>
    <span><span class="tag {$color}">{$label}</span></span>
    <span><span class="priority priority-{$pri}">{$priLabel}</span></span>
    <span class="due-date {$dueCss}">{$dueLabel}</span>
    <div class="row-actions">
        <button class="icon-btn" title="Edit">{$editIcon}</button>
        <button class="icon-btn" title="Delete" onclick="deleteTask(this, {$id})">{$deleteIcon}</button>
    </div>
</div>
HTML;
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
    <title>Fini — My Tasks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/Mytasks.css">
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
        :root.light-mode .task-table { background: #ffffff !important; border-color: #dde1ed !important; }
        :root.light-mode .task-row.header-row { background: #f0f2f8 !important; color: #7c85a0 !important; }
        :root.light-mode .task-row.header-row:hover { background: #f0f2f8 !important; }
        :root.light-mode .add-task-row { color: #7c85a0 !important; border-top-color: #dde1ed !important; }
        :root.light-mode .add-task-row:hover { background: rgba(74,143,255,.06) !important; color: #4a8fff !important; }
    </style>
</head>
<body>
<script>(function(){if(window.self===window.top){window.location.replace('/shell.php?page=mytasks');}}());</script>

<video autoplay muted loop playsinline class="bg-video">
    <source src="../assets/bg.mp4" type="video/mp4">
</video>

<!-- ───── TOPBAR ───── -->
<header class="topbar">

    <a href="/dashboard.php" class="topbar-brand">Fini.</a>
    <div class="topbar-divider"></div>

    <ul class="topbar-nav">
        <li>
            <a href="/dashboard.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="active">
            <a href="/dashboard/mytasks.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                <span>My Tasks</span>
                <span class="badge"><?= $countAll ?></span>
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
                <span>mélodie</span>
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
        <strong>My Tasks<span>.</span></strong>
        <small>Track, manage, and crush your tasks.</small>
    </div>

    <div class="topbar-divider"></div>

    <div class="topbar-actions">
        <button class="btn btn-primary" id="open-add-task">
            <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
            New Task
        </button>
    </div>

    <div class="topbar-divider"></div>

    <a href="/dashboard/settings.php" class="topbar-user" title="My Profile" style="text-decoration:none;cursor:pointer;">
        <?php $_av = $_SESSION['avatar_data'] ?? ''; if ($_av): ?><div class="avatar" style="background-image:url(<?= htmlspecialchars($_av) ?>);background-size:cover;background-position:center;font-size:0;"></div><?php else: ?><div class="avatar"><?= strtoupper(substr($username, 0, 1)) ?></div><?php endif; ?>
        <span class="topbar-user-name"><?= htmlspecialchars($username) ?></span>
    </a>

    <a href="/logout.php" class="logout-btn" title="Sign out" id="topbar-logout">
        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
    </a>

</header>

<!-- ───── PAGE ───── -->
<div class="page">
    <div class="page-inner">

        <!-- header -->
        <div class="page-header">
            <div class="page-header-left">
                <h1>My Tasks</h1>
                <p>All your tasks in one place — organized by status and priority.</p>
            </div>
        </div>

        <!-- summary cards -->
        <div class="stats-row">
            <div class="stat-card active-filter" data-filter="all" onclick="filterTasks('all', this)">
                <div class="stat-label">All Tasks</div>
                <div class="stat-value" id="count-all"><?= $countAll ?></div>
                <div class="stat-sub"><span class="stat-dot" style="background:#4a8fff"></span>Everything assigned</div>
            </div>
            <div class="stat-card" data-filter="in-progress" onclick="filterTasks('inprogress', this)">
                <div class="stat-label">In Progress</div>
                <div class="stat-value" id="count-progress"><?= $countProgress ?></div>
                <div class="stat-sub"><span class="stat-dot" style="background:#f0a070"></span>Currently active</div>
            </div>
            <div class="stat-card" data-filter="pending" onclick="filterTasks('todo', this)">
                <div class="stat-label">Pending</div>
                <div class="stat-value" id="count-pending"><?= $countPending ?></div>
                <div class="stat-sub"><span class="stat-dot" style="background:#5c6478"></span>Not yet started</div>
            </div>
            <div class="stat-card" data-filter="overdue" onclick="filterTasks('overdue', this)">
                <div class="stat-label">Overdue</div>
                <div class="stat-value" id="count-overdue"><?= $countOverdue ?></div>
                <div class="stat-sub"><span class="stat-dot" style="background:#f07070"></span>Past due date</div>
            </div>
        </div>

        <!-- toolbar -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="search-box">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                    <input type="text" placeholder="Search tasks…" id="search-input" oninput="applySearch()">
                </div>
                <button class="filter-pill active" data-sort="all" onclick="setSort('all',this)">All</button>
                <button class="filter-pill" data-sort="high" onclick="setSort('high',this)">High Priority</button>
                <button class="filter-pill" data-sort="due-soon" onclick="setSort('due-soon',this)">Due Soon</button>
            </div>
        </div>

        <!-- ── OVERDUE ── -->
        <div class="section" id="section-overdue" <?= empty($tasks['overdue']) ? 'style="display:none"' : '' ?>>
            <div class="section-header">
                <div class="section-title">
                    <span class="section-dot" style="background:#f07070"></span>
                    Overdue
                    <span class="section-count" id="badge-overdue"><?= $countOverdue ?></span>
                </div>
            </div>
            <div class="task-table">
                <div class="task-row header-row">
                    <span></span><span>Task</span><span>Category</span>
                    <span>Priority</span><span>Due Date</span><span></span>
                </div>
                <?php foreach ($tasks['overdue'] as $t) echo taskRow($t, $today); ?>
            </div>
        </div>

        <!-- ── IN PROGRESS ── -->
        <div class="section" id="section-in-progress" <?= empty($tasks['inprogress']) ? 'style="display:none"' : '' ?>>
            <div class="section-header">
                <div class="section-title">
                    <span class="section-dot" style="background:#f0a070"></span>
                    In Progress
                    <span class="section-count" id="badge-progress"><?= $countProgress ?></span>
                </div>
            </div>
            <div class="task-table">
                <div class="task-row header-row">
                    <span></span><span>Task</span><span>Category</span>
                    <span>Priority</span><span>Due Date</span><span></span>
                </div>
                <?php foreach ($tasks['inprogress'] as $t) echo taskRow($t, $today); ?>
            </div>
        </div>

        <!-- ── PENDING ── -->
        <div class="section" id="section-pending">
            <div class="section-header">
                <div class="section-title">
                    <span class="section-dot" style="background:#5c6478"></span>
                    Pending
                    <span class="section-count" id="badge-pending"><?= $countPending ?></span>
                </div>
            </div>
            <div class="task-table" id="pending-table">
                <div class="task-row header-row">
                    <span></span><span>Task</span><span>Category</span>
                    <span>Priority</span><span>Due Date</span><span></span>
                </div>
                <?php foreach ($tasks['todo'] as $t) echo taskRow($t, $today); ?>

                <!-- add task row -->
                <div class="add-task-row" onclick="document.getElementById('open-add-task').click()">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                    Add a task…
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ───── ADD TASK MODAL ───── -->
<div class="modal-overlay" id="add-task-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">New Task</span>
            <button class="modal-close" id="close-modal">
                <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>
        <div class="form-group">
            <label class="form-label">Task Name</label>
            <input class="form-input" type="text" id="new-task-name" placeholder="e.g. Build settings page">
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Category</label>
                <select class="form-select" id="new-task-cat">
                    <option value="Frontend">Frontend</option>
                    <option value="Backend">Backend</option>
                    <option value="Design">Design</option>
                    <option value="Pending">Pending</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Priority</label>
                <select class="form-select" id="new-task-priority">
                    <option value="high">High</option>
                    <option value="medium" selected>Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Due Date</label>
            <input class="form-input" type="date" id="new-task-due">
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" id="cancel-modal">Cancel</button>
            <button class="btn-save" onclick="addTask()">Add Task</button>
        </div>
    </div>
</div>

<script>
// ── Tag colour map ──
const catTagMap = {
    Frontend: 'tag-blue',
    Backend:  'tag-orange',
    Design:   'tag-blue',
    Pending:  'tag-purple',
};

// ── Modal ──
const modal = document.getElementById('add-task-modal');
document.getElementById('open-add-task').addEventListener('click', () => modal.classList.add('open'));
document.getElementById('close-modal').addEventListener('click',  () => modal.classList.remove('open'));
document.getElementById('cancel-modal').addEventListener('click', () => modal.classList.remove('open'));
modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') modal.classList.remove('open'); });

// ── Checkbox toggle (saves to DB) ──
function toggleCheck(el, id) {
    el.classList.toggle('checked');
    const nameEl = el.closest('.task-row').querySelector('.task-name');
    nameEl.classList.toggle('done');
    const done = el.classList.contains('checked') ? 1 : 0;

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle&id=${id}&done=${done}`
    });

    updateCounts();
}

// ── Delete task (removes from DB + DOM) ──
function deleteTask(btn, id) {
    const row = btn.closest('.task-row');
    row.style.animation = 'fadeOut .2s ease forwards';

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&id=${id}`
    }).then(r => r.json()).then(data => {
        if (data.ok) {
            setTimeout(() => { row.remove(); updateCounts(); updateSectionVisibility(); }, 200);
        }
    });
}

// Add fadeOut keyframe
const style = document.createElement('style');
style.textContent = '@keyframes fadeOut { to { opacity:0; transform:translateX(10px); } }';
document.head.appendChild(style);

// ── Update stat-card counts ──
function updateCounts() {
    const all      = document.querySelectorAll('.task-row:not(.header-row)').length;
    const progress = document.querySelectorAll('.task-row[data-status="in-progress"]').length;
    const pending  = document.querySelectorAll('.task-row[data-status="pending"]').length;
    const overdue  = document.querySelectorAll('.task-row[data-status="overdue"]').length;

    document.getElementById('count-all').textContent      = all;
    document.getElementById('count-progress').textContent = progress;
    document.getElementById('count-pending').textContent  = pending;
    document.getElementById('count-overdue').textContent  = overdue;
    document.getElementById('badge-overdue').textContent  = overdue;
    document.getElementById('badge-progress').textContent = progress;
    document.getElementById('badge-pending').textContent  = pending;

    // also update the nav badge
    const navBadge = document.querySelector('.topbar-nav .badge');
    if (navBadge) navBadge.textContent = all;
}

// ── Hide empty sections ──
function updateSectionVisibility() {
    ['overdue', 'inprogress', 'todo'].forEach(s => {
        const sec  = document.getElementById('section-' + s);
        const rows = sec.querySelectorAll('.task-row:not(.header-row):not(.add-task-row)');
        sec.style.display = (rows.length === 0 && s !== 'todo') ? 'none' : 'block';
    });
}

// ── Add task (saves to DB then injects row) ──
function addTask() {
    const name     = document.getElementById('new-task-name').value.trim();
    const cat      = document.getElementById('new-task-cat').value;
    const priority = document.getElementById('new-task-priority').value;
    const due      = document.getElementById('new-task-due').value;

    if (!name) { document.getElementById('new-task-name').focus(); return; }

    const tagColor = catTagMap[cat] || 'tag-blue';

    const params = new URLSearchParams({
        action:    'add',
        title:     name,
        tag_label: cat,
        tag: tagColor,
        priority:  priority,
        due_date:  due,
    });

    fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { alert(data.msg || 'Error saving task'); return; }

            const today     = new Date().toISOString().split('T')[0];
            const dueLabel  = due ? new Date(due + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';
            const priLabel  = priority.charAt(0).toUpperCase() + priority.slice(1);
            const dueCss    = due && due === today ? 'today' : (due && due < today ? 'overdue' : '');

            const editIcon   = `<svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>`;
            const deleteIcon = `<svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zm-1 7a1 1 0 012 0v4a1 1 0 11-2 0V9zm4 0a1 1 0 012 0v4a1 1 0 11-2 0V9z" clip-rule="evenodd"/></svg>`;

            const row = document.createElement('div');
            row.className = 'task-row';
            row.dataset.id       = data.id;
            row.dataset.status   = 'todo';
            row.dataset.priority = priority;
            row.dataset.due      = due || '';
            row.innerHTML = `
                <div class="task-check" onclick="toggleCheck(this, ${data.id})"></div>
                <div class="task-name-cell"><span class="task-name">${name}</span></div>
                <span><span class="tag ${tagColor}">${cat}</span></span>
                <span><span class="priority priority-${priority}">${priLabel}</span></span>
                <span class="due-date ${dueCss}">${dueLabel}</span>
                <div class="row-actions">
                    <button class="icon-btn" title="Edit">${editIcon}</button>
                    <button class="icon-btn" title="Delete" onclick="deleteTask(this, ${data.id})">${deleteIcon}</button>
                </div>
            `;

            const addRow = document.querySelector('.add-task-row');
            addRow.parentNode.insertBefore(row, addRow);

            document.getElementById('new-task-name').value = '';
            document.getElementById('new-task-due').value  = '';
            modal.classList.remove('open');
            updateCounts();
        });
}

// ── Filter by status card ──
let currentFilter = 'all';
function filterTasks(filter, card) {
    currentFilter = filter;
    document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
    card.classList.add('active-filter');

    const sections = {
        'all':         ['section-overdue', 'section-in-progress', 'section-pending'],
        'inprogress': ['section-in-progress'],
        'todo':     ['section-pending'],
        'overdue':     ['section-overdue'],
    };

    ['section-overdue', 'section-in-progress', 'section-pending'].forEach(id => {
        document.getElementById(id).style.display = 'none';
    });
    (sections[filter] || sections['all']).forEach(id => {
        document.getElementById(id).style.display = 'block';
    });
}

// ── Sort pills ──
function setSort(sort, btn) {
    document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = [...document.querySelectorAll('.task-row:not(.header-row):not(.add-task-row)')];
    if (sort === 'high') {
        rows.forEach(r => r.style.display = r.dataset.priority === 'high' ? '' : 'none');
    } else if (sort === 'due-soon') {
        rows.forEach(r => {
            const due  = r.dataset.due;
            const diff = due ? (new Date(due) - new Date()) / 86400000 : 999;
            r.style.display = diff <= 5 ? '' : 'none';
        });
    } else {
        rows.forEach(r => r.style.display = '');
    }
}

// ── Search ──
function applySearch() {
    const q = document.getElementById('search-input').value.toLowerCase();
    document.querySelectorAll('.task-row:not(.header-row):not(.add-task-row)').forEach(r => {
        const name = r.querySelector('.task-name').textContent.toLowerCase();
        r.style.display = name.includes(q) ? '' : 'none';
    });
}
</script>

<script src="/nav-intercept.js"></script>
</body>
</html>