<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href="/login.php";</script>';
    exit();
}

date_default_timezone_set('Asia/Manila');

// ── AJAX: return sidebar stats as JSON ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'sidebar') {
    header('Content-Type: application/json');
    $user_id = (int) $_SESSION['user_id'];
    require_once __DIR__ . '/../public/database.config.php';
    $db2 = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DB_NAME);
    $today2 = date('Y-m-d');
    $monthStart2 = date('Y-m-01');
    $monthEnd2   = date('Y-m-t');
    $out = ['total'=>0,'inprogress'=>0,'completed'=>0,'overdue'=>0,'pending'=>0,'upcoming'=>[]];
    if (!$db2->connect_error) {
        // Total = all tasks this month
        $s = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND (due_date BETWEEN ? AND ? OR created_at BETWEEN ? AND ?)");
        $s->bind_param('issss',$user_id,$monthStart2,$monthEnd2,$monthStart2,$monthEnd2);
        $s->execute(); $s->bind_result($out['total']); $s->fetch(); $s->close();
        // In Progress = tasks with orange tag (tag-orange)
        $s = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND tag_class='tag-orange' AND (due_date BETWEEN ? AND ? OR created_at BETWEEN ? AND ?)");
        $s->bind_param('issss',$user_id,$monthStart2,$monthEnd2,$monthStart2,$monthEnd2);
        $s->execute(); $s->bind_result($out['inprogress']); $s->fetch(); $s->close();
        // Completed = tasks with green tag (tag-green)
        $s = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND tag_class='tag-green' AND (due_date BETWEEN ? AND ? OR created_at BETWEEN ? AND ?)");
        $s->bind_param('issss',$user_id,$monthStart2,$monthEnd2,$monthStart2,$monthEnd2);
        $s->execute(); $s->bind_result($out['completed']); $s->fetch(); $s->close();
        // Overdue = tasks with red tag (tag-red)
        $s = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND tag_class='tag-red' AND (due_date BETWEEN ? AND ? OR created_at BETWEEN ? AND ?)");
        $s->bind_param('issss',$user_id,$monthStart2,$monthEnd2,$monthStart2,$monthEnd2);
        $s->execute(); $s->bind_result($out['overdue']); $s->fetch(); $s->close();
        // Pending = tasks with purple tag (tag-purple)
        $s = $db2->prepare("SELECT COUNT(*) FROM tasks WHERE user_id=? AND tag_class='tag-purple' AND (due_date BETWEEN ? AND ? OR created_at BETWEEN ? AND ?)");
        $s->bind_param('issss',$user_id,$monthStart2,$monthEnd2,$monthStart2,$monthEnd2);
        $s->execute(); $s->bind_result($out['pending']); $s->fetch(); $s->close();
        $s = $db2->prepare("SELECT title,tag_label,tag_class,due_date FROM tasks WHERE user_id=? AND status!='done' AND due_date>=? ORDER BY due_date ASC LIMIT 10");
        $s->bind_param('is',$user_id,$today2);
        $s->execute();
        $res2 = $s->get_result();
        $tagHex2 = ['tag-blue'=>'#4a8fff','tag-orange'=>'#f0a070','tag-purple'=>'#c4b0ff','tag-red'=>'#f07070','tag-green'=>'#52d68a'];
        while ($row2 = $res2->fetch_assoc()) {
            $out['upcoming'][] = [
                'title'    => $row2['title'],
                'label'    => $row2['tag_label'],
                'color'    => $tagHex2[$row2['tag_class']] ?? '#4a8fff',
                'date'     => date('M j, Y', strtotime($row2['due_date'])),
            ];
        }
        $s->close();
        $db2->close();
    }
    echo json_encode($out);
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$user_id  = (int) $_SESSION['user_id'];
$today    = date('Y-m-d');

require_once __DIR__ . '/../public/database.config.php';
$db = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DB_NAME);

// ── Counts for topbar badge & sidebar ────────────────────
$incompleteTasks = 0;
$totalMonth = $inProgressMonth = $completedMonth = $overdueMonth = 0;

if (!$db->connect_error) {
    // Nav badge: all incomplete tasks
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'done'");
    $s->bind_param('i', $user_id); $s->execute(); $s->bind_result($incompleteTasks); $s->fetch(); $s->close();

    // This-month stats
    $monthStart = date('Y-m-01');
    $monthEnd   = date('Y-m-t');

    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND (due_date BETWEEN ? AND ? OR created_at BETWEEN ? AND ?)");
    $s->bind_param('issss', $user_id, $monthStart, $monthEnd, $monthStart, $monthEnd);
    $s->execute(); $s->bind_result($totalMonth); $s->fetch(); $s->close();

    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'inprogress' AND (due_date BETWEEN ? AND ? OR created_at BETWEEN ? AND ?)");
    $s->bind_param('issss', $user_id, $monthStart, $monthEnd, $monthStart, $monthEnd);
    $s->execute(); $s->bind_result($inProgressMonth); $s->fetch(); $s->close();

    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'done' AND (due_date BETWEEN ? AND ? OR created_at BETWEEN ? AND ?)");
    $s->bind_param('issss', $user_id, $monthStart, $monthEnd, $monthStart, $monthEnd);
    $s->execute(); $s->bind_result($completedMonth); $s->fetch(); $s->close();

    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'done' AND due_date < ?");
    $s->bind_param('is', $user_id, $today);
    $s->execute(); $s->bind_result($overdueMonth); $s->fetch(); $s->close();
}
    // ── Calendar events JSON (for JS calendar rendering) ─
    $jsEvents = [];
    $upcomingItems = [];
    $tagClassToHex = [
        'tag-blue'   => '#4a8fff',
        'tag-orange' => '#f0a070',
        'tag-purple' => '#c4b0ff',
        'tag-red'    => '#f07070',
        'tag-green'  => '#52d68a',
    ];
    $colorNameMap = [
        '#4a8fff' => 'blue',
        '#f0a070' => 'orange',
        '#a070f0' => 'purple',
        '#70c090' => 'green',
        '#f07070' => 'red',
    ];

    $s = $db->prepare("SELECT id, title, tag_label, tag_class, due_date, status FROM tasks WHERE user_id = ? AND status != 'done' AND due_date IS NOT NULL ORDER BY due_date ASC");
    $s->bind_param('i', $user_id);
    $s->execute();
    $res = $s->get_result();
    while ($row = $res->fetch_assoc()) {
        $hex = $tagClassToHex[$row['tag_class'] ?? ''] ?? '#4a8fff';
        $jsEvents[] = [
            'id'    => $row['id'],
            'title' => $row['title'],
            'date'  => $row['due_date'],
            'color' => $hex,
            'status'=> $row['status'],
        ];
        if ($row['due_date'] >= $today) {
            $upcomingItems[] = $row;
        }
    }
    $s->close();
    $jsEventsJSON = json_encode($jsEvents);

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
    <title>Fini — Calendar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/Calendar.css">
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
<script>(function(){if(window.self===window.top){window.location.replace('/shell.php?page=calendar');}}());</script>

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
                <?php if ($incompleteTasks > 0): ?><span class="badge"><?= $incompleteTasks ?></span><?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/dashboard/mytasks.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                <span>My Tasks</span>
                <?php if ($incompleteTasks > 0): ?><span class="badge"><?= $incompleteTasks ?></span><?php endif; ?>
            </a>
        </li>
        <li class="active">
            <a href="#">
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
        <strong>Calendar <span>.</span></strong>
        <small>Here's your schedule.</small>
    </div>

    <div class="topbar-divider"></div>

    <div class="topbar-actions">
        <button class="btn btn-ghost">Today</button>
        <button class="btn btn-primary" id="newEventBtn">
            <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
            New Event
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

        <!-- CALENDAR MAIN -->
        <div class="calendar-main">
            <div class="cal-header">
                <div class="cal-month-nav">
                    <button class="cal-nav-btn" id="prevMonth">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    </button>
                    <div class="cal-month-title" id="calMonthTitle"></div>
                    <button class="cal-nav-btn" id="nextMonth">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
                <div class="view-toggle">
                    <button class="view-btn active">Month</button>
                    <button class="view-btn">Week</button>
                    <button class="view-btn">Day</button>
                </div>
            </div>

            <div class="cal-grid-wrap">
                <div class="cal-weekdays">
                    <div class="cal-weekday">Sun</div>
                    <div class="cal-weekday">Mon</div>
                    <div class="cal-weekday">Tue</div>
                    <div class="cal-weekday">Wed</div>
                    <div class="cal-weekday">Thu</div>
                    <div class="cal-weekday">Fri</div>
                    <div class="cal-weekday">Sat</div>
                </div>
                <div class="cal-days" id="calDays"></div>
            </div>
        </div>

        <!-- SIDEBAR -->
        <div class="cal-sidebar">

            <!-- Mini calendar -->
            <div class="panel-card">
                <div class="mini-cal-header">
                    <div class="mini-cal-title" id="miniCalTitle"></div>
                    <div class="mini-nav">
                        <button class="mini-nav-btn" id="miniPrev">‹</button>
                        <button class="mini-nav-btn" id="miniNext">›</button>
                    </div>
                </div>
                <div class="mini-weekdays">
                    <div class="mini-wd">S</div><div class="mini-wd">M</div><div class="mini-wd">T</div>
                    <div class="mini-wd">W</div><div class="mini-wd">T</div><div class="mini-wd">F</div><div class="mini-wd">S</div>
                </div>
                <div class="mini-days" id="miniDays"></div>
            </div>

            <!-- Task summary — live from DB -->
            <div class="panel-card">
                <div class="panel-card-title">This Month</div>
                <div class="task-summary-item">
                    <div class="task-summary-left"><span class="task-dot" style="background:#4a8fff"></span>Total Tasks</div>
                    <span class="task-summary-count" id="sidebarTotal"><?= $totalMonth ?></span>
                </div>
                <div class="task-summary-item">
                    <div class="task-summary-left"><span class="task-dot" style="background:#f0a070"></span>In Progress</div>
                    <span class="task-summary-count" id="sidebarInProgress"><?= $inProgressMonth ?></span>
                </div>
                <div class="task-summary-item">
                    <div class="task-summary-left"><span class="task-dot" style="background:#52d68a"></span>Completed</div>
                    <span class="task-summary-count" id="sidebarCompleted"><?= $completedMonth ?></span>
                </div>
                <div class="task-summary-item">
                    <div class="task-summary-left"><span class="task-dot" style="background:#f07070"></span>Overdue</div>
                    <span class="task-summary-count" id="sidebarOverdue"><?= $overdueMonth ?></span>
                </div>
                <div class="task-summary-item">
                    <div class="task-summary-left"><span class="task-dot" style="background:#c4b0ff"></span>Pending</div>
                    <span class="task-summary-count" id="sidebarPending">0</span>
                </div>
            </div>

            <!-- Upcoming events — live from DB -->
            <div class="panel-card">
                <div class="panel-card-title">Upcoming</div>
                <div id="sidebarUpcoming">
                <?php if (empty($upcomingItems)): ?>
                    <div style="font-size:.78rem;color:var(--muted);padding:10px 0">No upcoming tasks.</div>
                <?php else: foreach ($upcomingItems as $ev):
                    $hex    = $tagClassToHex[$ev['tag_class'] ?? ''] ?? '#4a8fff';
                    $label  = $ev['tag_label'] ?? '';
                    $cname  = $colorNameMap[$hex] ?? 'blue';
                    $dFmt   = date('M j, Y', strtotime($ev['due_date']));
                ?>
                <div class="event-item">
                    <div class="event-color-bar" style="background:<?= $hex ?>"></div>
                    <div class="event-info">
                        <div class="event-title"><?= htmlspecialchars($ev['title']) ?></div>
                        <div class="event-date"><?= $dFmt ?></div>
                    </div>
                    <?php if ($label): ?>
                    <span class="event-tag ev-<?= $cname ?>"><?= htmlspecialchars($label) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- ───── NEW EVENT MODAL ───── -->
<div class="modal-overlay" id="eventModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="eventModalTitle">New Task</div>
            <button class="modal-close" id="closeModal">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>

        <div class="form-group">
            <label class="form-label">Title</label>
            <input class="form-input" type="text" placeholder="Task title…" id="eventTitle">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Due Date</label>
                <input class="form-input" type="date" id="eventDate">
            </div>
            <div class="form-group">
                <label class="form-label">Priority</label>
                <select class="form-select" id="eventPriority">
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Tag</label>
            <select class="form-select" id="eventCat">
                <option value="design" data-label="Design"  data-color="#4a8fff">Design</option>
                <option value="backend" data-label="Backend" data-color="#f0a070">Backend</option>
                <option value="docs"   data-label="Docs"    data-color="#c4b0ff">Docs</option>
                <option value="meeting" data-label="Meeting" data-color="#52d68a">Meeting</option>
                <option value="urgent" data-label="Urgent"  data-color="#f07070">Urgent</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Color</label>
            <div class="color-picker">
                <div class="color-dot selected" style="background:#4a8fff" data-color="blue" data-hex="#4a8fff"></div>
                <div class="color-dot" style="background:#52d68a" data-color="green"  data-hex="#52d68a"></div>
                <div class="color-dot" style="background:#f0a070" data-color="orange" data-hex="#f0a070"></div>
                <div class="color-dot" style="background:#f07070" data-color="red"    data-hex="#f07070"></div>
                <div class="color-dot" style="background:#c4b0ff" data-color="purple" data-hex="#c4b0ff"></div>
            </div>
        </div>

        <div id="saveMsg" style="display:none;font-size:.78rem;color:#52d68a;margin-bottom:8px"></div>

        <div class="modal-actions">
            <button class="btn btn-ghost" id="cancelModal">Cancel</button>
            <button class="btn" id="deleteEventBtn" style="display:none;background:rgba(240,112,112,.15);color:#f07070;border:1px solid rgba(240,112,112,.3);">Delete</button>
            <button class="btn btn-primary" id="saveEvent">
                <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                Add Task
            </button>
        </div>
    </div>
</div>

<style>
/* ── Week / Day view ── */
.cal-week-wrap, .cal-day-wrap { display:none; overflow:auto; }
.cal-week-wrap.active, .cal-day-wrap.active { display:block; }
.cal-grid-wrap.hidden { display:none; }

.week-header { display:grid; grid-template-columns: 52px repeat(7,1fr); border-bottom:1px solid rgba(255,255,255,.07); }
.week-header-day { padding:8px 4px; text-align:center; font-size:.72rem; color:var(--muted,#888); }
.week-header-day .wh-num { font-size:1.1rem; font-weight:600; color:var(--text,#fff); display:block; }
.week-header-day.today-col .wh-num { background:#4a8fff; border-radius:50%; width:30px; height:30px; line-height:30px; margin:0 auto; }

.week-body { display:grid; grid-template-columns: 52px repeat(7,1fr); }
.week-time-col { display:flex; flex-direction:column; }
.week-time-slot { height:52px; display:flex; align-items:flex-start; justify-content:flex-end; padding-right:8px; font-size:.65rem; color:var(--muted,#888); border-right:1px solid rgba(255,255,255,.07); padding-top:2px; }
.week-col { display:flex; flex-direction:column; border-right:1px solid rgba(255,255,255,.05); position:relative; }
.week-cell { height:52px; border-bottom:1px solid rgba(255,255,255,.04); cursor:pointer; transition:background .15s; }
.week-cell:hover { background:rgba(74,143,255,.07); }
.week-events { position:absolute; top:0; left:0; right:0; padding:2px 3px; pointer-events:none; }
.week-event-pill { background:rgba(74,143,255,.25); border-left:3px solid #4a8fff; border-radius:4px; padding:3px 6px; font-size:.7rem; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; pointer-events:all; cursor:pointer; }

.day-view-wrap { display:none; }
.day-view-wrap.active { display:block; }
.day-view-header { font-size:1.1rem; font-weight:600; padding:10px 0 14px; border-bottom:1px solid rgba(255,255,255,.07); margin-bottom:8px; }
.day-view-body { display:grid; grid-template-columns:52px 1fr; }
.day-view-events { padding:0 8px; }
.day-event-block { background:rgba(74,143,255,.2); border-left:3px solid #4a8fff; border-radius:6px; padding:10px 14px; margin-bottom:8px; cursor:pointer; }
.day-event-block:hover { background:rgba(74,143,255,.32); }
.day-event-title { font-size:.88rem; font-weight:600; }
.day-event-meta { font-size:.72rem; color:var(--muted,#888); margin-top:3px; }
.day-no-events { color:var(--muted,#888); font-size:.82rem; padding:20px 0; }

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

<script>
    const MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const DAY_NAMES   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    const today = new Date(<?= date('Y') ?>, <?= (int)date('n') - 1 ?>, <?= (int)date('j') ?>);
    let currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
    let currentView = 'month'; // 'month' | 'week' | 'day'
    let selectedDay = new Date(today); // for day view

    let events = <?= $jsEventsJSON ?>;

    function dateKey(y, m, d) {
        return `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    }
    function dateKeyFromDate(dt) {
        return dateKey(dt.getFullYear(), dt.getMonth(), dt.getDate());
    }
    function eventsForDate(y, m, d) {
        const key = dateKey(y, m, d);
        return events.filter(e => e.date === key);
    }

    // ── Colour helper ──
    function hexForEvent(ev) { return ev.color || '#4a8fff'; }

    // ─────────────────────────────────────────────
    // MONTH VIEW
    // ─────────────────────────────────────────────
    function renderMonth() {
        const y = currentDate.getFullYear();
        const m = currentDate.getMonth();
        document.getElementById('calMonthTitle').textContent = `${MONTH_NAMES[m]} ${y}`;

        const firstDay    = new Date(y, m, 1).getDay();
        const daysInMonth = new Date(y, m+1, 0).getDate();
        const daysInPrev  = new Date(y, m, 0).getDate();
        const grid        = document.getElementById('calDays');
        grid.innerHTML    = '';

        for (let i = 0; i < 42; i++) {
            const cell = document.createElement('div');
            cell.className = 'cal-day';
            let day, mo, yr;

            if (i < firstDay) {
                day = daysInPrev - firstDay + 1 + i; mo = m-1; yr = m===0?y-1:y;
                cell.classList.add('other-month');
            } else if (i >= firstDay + daysInMonth) {
                day = i - firstDay - daysInMonth + 1; mo = m+1; yr = m===11?y+1:y;
                cell.classList.add('other-month');
            } else {
                day = i - firstDay + 1; mo = m; yr = y;
            }

            if (yr===today.getFullYear() && mo===today.getMonth() && day===today.getDate())
                cell.classList.add('today');

            const numEl = document.createElement('div');
            numEl.className = 'day-num';
            numEl.textContent = day;
            cell.appendChild(numEl);

            const dayEvents = eventsForDate(yr, mo, day);
            if (dayEvents.length) {
                const evWrap = document.createElement('div');
                evWrap.className = 'day-events';
                dayEvents.slice(0, 2).forEach(ev => {
                    const evEl = document.createElement('div');
                    evEl.className = 'day-event';
                    evEl.style.cssText = `background:${hexForEvent(ev)}22;border-left:3px solid ${hexForEvent(ev)};padding:2px 5px;border-radius:3px;font-size:.68rem;cursor:pointer;`;
                    evEl.textContent = ev.title;
                    evEl.addEventListener('click', e => { e.stopPropagation(); openEditModal(ev); });
                    evWrap.appendChild(evEl);
                });
                if (dayEvents.length > 2) {
                    const more = document.createElement('div');
                    more.style.cssText = 'font-size:.65rem;color:#888;padding-left:5px;';
                    more.textContent = `+${dayEvents.length - 2} more`;
                    evWrap.appendChild(more);
                }
                cell.appendChild(evWrap);
            }

            // Click empty area → open new task modal pre-filled with that date
            cell.addEventListener('click', () => {
                if (!cell.classList.contains('other-month')) {
                    openNewModal(dateKey(yr, mo, day));
                }
            });

            grid.appendChild(cell);
        }
    }

    // ─────────────────────────────────────────────
    // WEEK VIEW
    // ─────────────────────────────────────────────
    function getWeekStart(dt) {
        const d = new Date(dt);
        d.setDate(d.getDate() - d.getDay());
        return d;
    }

    function renderWeek() {
        const ws = getWeekStart(selectedDay);
        const days = Array.from({length:7}, (_,i) => {
            const d = new Date(ws); d.setDate(ws.getDate()+i); return d;
        });

        // Title
        const s = days[0], e = days[6];
        document.getElementById('calMonthTitle').textContent =
            `${MONTH_NAMES[s.getMonth()]} ${s.getDate()} – ${MONTH_NAMES[e.getMonth()]} ${e.getDate()}, ${e.getFullYear()}`;

        let wrapEl = document.getElementById('weekViewWrap');
        if (!wrapEl) {
            wrapEl = document.createElement('div');
            wrapEl.id = 'weekViewWrap';
            wrapEl.className = 'cal-week-wrap';
            document.querySelector('.cal-grid-wrap').after(wrapEl);
        }
        wrapEl.innerHTML = '';
        wrapEl.classList.add('active');

        // Header row
        const header = document.createElement('div');
        header.className = 'week-header';
        header.innerHTML = '<div style="border-right:1px solid rgba(255,255,255,.07)"></div>';
        days.forEach(d => {
            const isToday = dateKeyFromDate(d) === dateKeyFromDate(today);
            const col = document.createElement('div');
            col.className = 'week-header-day' + (isToday ? ' today-col' : '');
            col.innerHTML = `<span style="font-size:.7rem;color:#888">${DAY_NAMES[d.getDay()]}</span><span class="wh-num">${d.getDate()}</span>`;
            header.appendChild(col);
        });
        wrapEl.appendChild(header);

        // Body
        const body = document.createElement('div');
        body.className = 'week-body';

        // Time column
        const timeCol = document.createElement('div');
        timeCol.className = 'week-time-col';
        for (let h = 0; h < 24; h++) {
            const slot = document.createElement('div');
            slot.className = 'week-time-slot';
            slot.textContent = h === 0 ? '' : `${h}:00`;
            timeCol.appendChild(slot);
        }
        body.appendChild(timeCol);

        // Day columns
        days.forEach(d => {
            const col = document.createElement('div');
            col.className = 'week-col';
            const dayEvs = eventsForDate(d.getFullYear(), d.getMonth(), d.getDate());

            // 24 blank cells
            for (let h = 0; h < 24; h++) {
                const cell = document.createElement('div');
                cell.className = 'week-cell';
                cell.addEventListener('click', () => openNewModal(dateKeyFromDate(d)));
                col.appendChild(cell);
            }

            // Event pills overlay (all-day style at top)
            if (dayEvs.length) {
                const evContainer = document.createElement('div');
                evContainer.className = 'week-events';
                evContainer.style.top = '4px';
                dayEvs.forEach(ev => {
                    const pill = document.createElement('div');
                    pill.className = 'week-event-pill';
                    pill.style.borderLeftColor = hexForEvent(ev);
                    pill.style.background = hexForEvent(ev) + '22';
                    pill.textContent = ev.title;
                    pill.addEventListener('click', e => { e.stopPropagation(); openEditModal(ev); });
                    evContainer.appendChild(pill);
                });
                col.appendChild(evContainer);
            }

            body.appendChild(col);
        });
        wrapEl.appendChild(body);
    }

    // ─────────────────────────────────────────────
    // DAY VIEW
    // ─────────────────────────────────────────────
    function renderDay() {
        const d = selectedDay;
        document.getElementById('calMonthTitle').textContent =
            `${DAY_NAMES[d.getDay()]}, ${MONTH_NAMES[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;

        let wrapEl = document.getElementById('dayViewWrap');
        if (!wrapEl) {
            wrapEl = document.createElement('div');
            wrapEl.id = 'dayViewWrap';
            wrapEl.className = 'day-view-wrap';
            document.querySelector('.cal-grid-wrap').after(wrapEl);
        }
        wrapEl.innerHTML = '';
        wrapEl.classList.add('active');

        const dayEvs = eventsForDate(d.getFullYear(), d.getMonth(), d.getDate());
        if (dayEvs.length === 0) {
            wrapEl.innerHTML = `<div class="day-no-events">No tasks on this day. <span style="color:#4a8fff;cursor:pointer" onclick="openNewModal('${dateKeyFromDate(d)}')">+ Add one</span></div>`;
        } else {
            dayEvs.forEach(ev => {
                const block = document.createElement('div');
                block.className = 'day-event-block';
                block.style.borderLeftColor = hexForEvent(ev);
                block.style.background = hexForEvent(ev) + '18';
                block.innerHTML = `<div class="day-event-title">${ev.title}</div><div class="day-event-meta">${ev.date}</div>`;
                block.addEventListener('click', () => openEditModal(ev));
                wrapEl.appendChild(block);
            });
        }
    }

    // ─────────────────────────────────────────────
    // MINI CALENDAR
    // ─────────────────────────────────────────────
    function renderMini() {
        const y = currentDate.getFullYear();
        const m = currentDate.getMonth();
        document.getElementById('miniCalTitle').textContent = `${MONTH_NAMES[m].slice(0,3)} ${y}`;

        const firstDay    = new Date(y, m, 1).getDay();
        const daysInMonth = new Date(y, m+1, 0).getDate();
        const daysInPrev  = new Date(y, m, 0).getDate();
        const grid        = document.getElementById('miniDays');
        grid.innerHTML    = '';

        for (let i = 0; i < 35; i++) {
            const cell = document.createElement('div');
            cell.className = 'mini-day';
            let day, mo, yr;

            if (i < firstDay) {
                day = daysInPrev - firstDay + 1 + i; mo = m-1; yr = m===0?y-1:y;
            } else if (i >= firstDay + daysInMonth) {
                day = i - firstDay - daysInMonth + 1; mo = m+1; yr = m===11?y+1:y;
            } else {
                day = i - firstDay + 1; mo = m; yr = y;
                cell.classList.add('cur-month');
                if (eventsForDate(yr, mo, day).length) cell.classList.add('has-event');
            }

            if (yr===today.getFullYear() && mo===today.getMonth() && day===today.getDate())
                cell.classList.add('today-mini');

            // Mini-cal day click → jump to day view
            const capDay = day, capMo = mo, capYr = yr;
            cell.addEventListener('click', () => {
                selectedDay = new Date(capYr, capMo, capDay);
                currentDate = new Date(capYr, capMo, 1);
                setView('day');
            });

            cell.textContent = day;
            grid.appendChild(cell);
        }
    }

    // ─────────────────────────────────────────────
    // RENDER DISPATCHER
    // ─────────────────────────────────────────────
    function hideAllViews() {
        document.querySelector('.cal-grid-wrap').classList.remove('hidden');
        const ww = document.getElementById('weekViewWrap');
        const dw = document.getElementById('dayViewWrap');
        if (ww) { ww.classList.remove('active'); }
        if (dw) { dw.classList.remove('active'); }
    }

    function render() {
        hideAllViews();
        renderMini();

        if (currentView === 'month') {
            renderMonth();
        } else if (currentView === 'week') {
            document.querySelector('.cal-grid-wrap').classList.add('hidden');
            renderWeek();
            document.getElementById('weekViewWrap').classList.add('active');
        } else {
            document.querySelector('.cal-grid-wrap').classList.add('hidden');
            renderDay();
            document.getElementById('dayViewWrap').classList.add('active');
        }
    }

    function setView(v) {
        currentView = v;
        document.querySelectorAll('.view-btn').forEach(b => {
            b.classList.toggle('active', b.textContent.trim().toLowerCase() === v);
        });
        render();
    }

    // ─────────────────────────────────────────────
    // NAVIGATION
    // ─────────────────────────────────────────────
    document.getElementById('prevMonth').addEventListener('click', () => {
        if (currentView === 'month') {
            currentDate.setMonth(currentDate.getMonth() - 1);
        } else if (currentView === 'week') {
            selectedDay.setDate(selectedDay.getDate() - 7);
            currentDate = new Date(selectedDay.getFullYear(), selectedDay.getMonth(), 1);
        } else {
            selectedDay.setDate(selectedDay.getDate() - 1);
            currentDate = new Date(selectedDay.getFullYear(), selectedDay.getMonth(), 1);
        }
        render();
    });

    document.getElementById('nextMonth').addEventListener('click', () => {
        if (currentView === 'month') {
            currentDate.setMonth(currentDate.getMonth() + 1);
        } else if (currentView === 'week') {
            selectedDay.setDate(selectedDay.getDate() + 7);
            currentDate = new Date(selectedDay.getFullYear(), selectedDay.getMonth(), 1);
        } else {
            selectedDay.setDate(selectedDay.getDate() + 1);
            currentDate = new Date(selectedDay.getFullYear(), selectedDay.getMonth(), 1);
        }
        render();
    });

    document.getElementById('miniPrev').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderMini(); });
    document.getElementById('miniNext').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderMini(); });

    document.querySelector('.btn-ghost').addEventListener('click', () => {
        selectedDay = new Date(today);
        currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
        render();
    });

    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', () => setView(btn.textContent.trim().toLowerCase()));
    });

    // ─────────────────────────────────────────────
    // NEW TASK MODAL
    // ─────────────────────────────────────────────
    function openNewModal(dateVal) {
        document.getElementById('eventModalTitle').textContent = 'New Task';
        document.getElementById('saveEvent').innerHTML = `<svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg> Add Task`;
        document.getElementById('saveEvent').dataset.mode = 'create';
        delete document.getElementById('saveEvent').dataset.editId;
        document.getElementById('eventTitle').value = '';
        document.getElementById('eventDate').value = dateVal || '';
        document.getElementById('eventPriority').value = 'medium';
        document.getElementById('eventCat').value = 'design';
        document.querySelectorAll('.color-dot').forEach((d,i) => d.classList.toggle('selected', i===0));
        document.getElementById('deleteEventBtn').style.display = 'none';
        document.getElementById('saveMsg').style.display = 'none';
        document.getElementById('eventModal').classList.add('open');
    }

    function openEditModal(ev) {
        document.getElementById('eventModalTitle').textContent = 'Edit Task';
        document.getElementById('saveEvent').innerHTML = `<svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> Save Changes`;
        document.getElementById('saveEvent').dataset.mode = 'update';
        document.getElementById('saveEvent').dataset.editId = ev.id;
        document.getElementById('eventTitle').value = ev.title || '';
        document.getElementById('eventDate').value = ev.date || '';
        document.getElementById('deleteEventBtn').style.display = 'inline-flex';
        document.getElementById('deleteEventBtn').dataset.deleteId = ev.id;
        document.getElementById('saveMsg').style.display = 'none';
        document.getElementById('eventModal').classList.add('open');
    }

    function closeModal() {
        document.getElementById('eventModal').classList.remove('open');
        document.getElementById('saveMsg').style.display = 'none';
        document.getElementById('eventTitle').value = '';
        document.getElementById('eventTitle').style.borderColor = '';
    }

    document.getElementById('newEventBtn').addEventListener('click', () => {
        openNewModal(dateKeyFromDate(today));
    });
    document.getElementById('closeModal').addEventListener('click', closeModal);
    document.getElementById('cancelModal').addEventListener('click', closeModal);
    document.getElementById('eventModal').addEventListener('click', e => {
        if (e.target === document.getElementById('eventModal')) closeModal();
    });

    document.querySelectorAll('.color-dot').forEach(dot => {
        dot.addEventListener('click', () => {
            document.querySelectorAll('.color-dot').forEach(d => d.classList.remove('selected'));
            dot.classList.add('selected');
        });
    });

    // ─────────────────────────────────────────────
    // SAVE (CREATE / UPDATE)
    // ─────────────────────────────────────────────
    document.getElementById('saveEvent').addEventListener('click', async () => {
        const titleEl  = document.getElementById('eventTitle');
        const title    = titleEl.value.trim();
        const date     = document.getElementById('eventDate').value;
        const selDot   = document.querySelector('.color-dot.selected');
        const hex      = selDot?.dataset.hex   || '#4a8fff';
        const dotColor = selDot?.dataset.color || 'blue';  // e.g. 'orange', 'blue'
        const tagClass = 'tag-' + dotColor;                // e.g. 'tag-orange'
        const catSel   = document.getElementById('eventCat');
        const selOpt   = catSel.options[catSel.selectedIndex];
        const tagLabel = selOpt.dataset.label  || catSel.value;
        const tagColor = hex;
        const priority = document.getElementById('eventPriority').value;
        const btn      = document.getElementById('saveEvent');
        const mode     = btn.dataset.mode || 'create';
        const editId   = btn.dataset.editId ? parseInt(btn.dataset.editId) : null;

        if (!title || !date) {
            titleEl.style.borderColor = '#f07070';
            setTimeout(() => titleEl.style.borderColor = '', 1500);
            return;
        }

        btn.disabled = true;
        const origHTML = btn.innerHTML;
        btn.textContent = 'Saving…';

        const payload = {
            action:    mode === 'update' ? 'update' : 'create',
            title,
            due_date:  date,
            tag_class: tagClass,
            tag_label: tagLabel,
            priority,
            status:    'todo',
        };
        if (mode === 'update' && editId) payload.id = editId;

        try {
            const res  = await fetch('/api/Tasks.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.success) {
                if (mode === 'create') {
                    events.push({ id: data.id, date, title, color: tagColor });
                } else {
                    const idx = events.findIndex(e => e.id === editId);
                    if (idx !== -1) { events[idx].title = title; events[idx].date = date; events[idx].color = tagColor; }
                }
                render();
                const msg = document.getElementById('saveMsg');
                msg.textContent = mode === 'update' ? 'Task updated!' : 'Task saved!';
                msg.style.display = 'block';
                refreshSidebar();
                setTimeout(() => closeModal(), 900);
            } else {
                alert('Could not save: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Network error — please try again. (' + err.message + ')');
        }

        btn.disabled = false;
        btn.innerHTML = origHTML;
    });

    // ─────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────
    document.getElementById('deleteEventBtn').addEventListener('click', async () => {
        const btn = document.getElementById('deleteEventBtn');
        const id  = parseInt(btn.dataset.deleteId);
        if (!id || !confirm('Delete this task?')) return;

        btn.disabled = true;
        btn.textContent = 'Deleting…';

        try {
            const res  = await fetch('/api/Tasks.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ action: 'delete', id }),
            });
            const data = await res.json();
            if (data.success) {
                events = events.filter(e => e.id !== id);
                render();
                closeModal();
            } else {
                alert('Delete failed: ' + (data.error || 'Unknown'));
                btn.disabled = false;
                btn.textContent = 'Delete';
            }
        } catch (err) {
            alert('Network error.');
            btn.disabled = false;
            btn.textContent = 'Delete';
        }
    });

    // ── Init ──
    render();
</script>

<script>
async function refreshSidebar() {
    try {
        const res  = await fetch('/dashboard/Calendar.php?ajax=sidebar');
        const data = await res.json();
        document.getElementById('sidebarTotal').textContent      = data.total;
        document.getElementById('sidebarInProgress').textContent = data.inprogress;
        document.getElementById('sidebarCompleted').textContent  = data.completed;
        document.getElementById('sidebarOverdue').textContent    = data.overdue;
        document.getElementById('sidebarPending').textContent   = data.pending || 0;


        const upEl = document.getElementById('sidebarUpcoming');
        if (data.upcoming.length === 0) {
            upEl.innerHTML = '<div style="font-size:.78rem;color:var(--muted);padding:10px 0">No upcoming tasks.</div>';
        } else {
            upEl.innerHTML = data.upcoming.map(ev => `
                <div class="event-item">
                    <div class="event-color-bar" style="background:${ev.color}"></div>
                    <div class="event-info">
                        <div class="event-title">${ev.title}</div>
                        <div class="event-date">${ev.date}</div>
                    </div>
                    ${ev.label ? `<span class="event-tag">${ev.label}</span>` : ''}
                </div>`).join('');
        }
    } catch(e) { console.error('Sidebar refresh failed', e); }
}

// Refresh immediately on load and every 30 seconds
refreshSidebar();
setInterval(refreshSidebar, 30000);

// Also refresh after any task modal closes
document.addEventListener('taskSaved', refreshSidebar);
</script>
<script src="/nav-intercept.js"></script>
</body>
</html>