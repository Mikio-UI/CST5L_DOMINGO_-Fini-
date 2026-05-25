<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href="/login.php";</script>';
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$user_id  = (int) $_SESSION['user_id'];

require_once __DIR__ . '/../public/database.config.php';
$db = $conn;

$incompleteTasks = 0;
if ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'done'");
    $s->bind_param('i', $user_id); $s->execute(); $s->bind_result($incompleteTasks); $s->fetch(); $s->close();
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
    <title>Fini — Mélodie</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;500;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #12141a;
            --panel:    #181b24;
            --panel2:   #1e2330;
            --border:   #252b38;
            --accent:   #4a8fff;
            --accent2:  #52d68a;
            --accent3:  #f0a070;
            --danger:   #f07070;
            --text:     #e9eaf0;
            --muted:    #5c6478;
            --subtle:   #2e3547;
            --radius:   10px;
            --music-accent: #c084fc;
            --music-accent2: #e879f9;
        }

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
        :root.light-mode .topbar-nav li.active a { background: rgba(192,132,252,.12) !important; color: var(--music-accent) !important; }
        :root.light-mode .topbar-greeting strong { color: #1a1d2e !important; }
        :root.light-mode .topbar-user { background: #ffffff !important; border-color: #dde1ed !important; }
        :root.light-mode .mel-sidebar { background: rgba(255,255,255,0.85) !important; border-right-color: #dde1ed !important; }
        :root.light-mode .mel-card { background: #ffffff !important; border-color: #dde1ed !important; }
        :root.light-mode .mel-player { background: rgba(240,242,248,0.98) !important; border-top-color: #dde1ed !important; }
        :root.light-mode .mel-search input { background: #f0f2f8 !important; border-color: #dde1ed !important; color: #1a1d2e !important; }
        :root.light-mode .queue-item:hover { background: #e4e8f3 !important; }

        html, body {
            height: 100%;
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow: hidden;
        }

        /* ─── video background ─── */
        .bg-video {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            z-index: 0;
            opacity: 0.18;
        }

        /* ─── TOPBAR ─── */
        .topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 32px;
            padding: 0 36px;
            height: 62px;
            background: rgba(18,20,26,0.05);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            border-bottom: 1px solid var(--border);
            animation: fadeDown .45s cubic-bezier(.22,.68,0,1.15) both;
        }

        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .topbar-brand {
            font-family: 'Instrument Serif', serif;
            font-size: 1.35rem;
            letter-spacing: -.3px;
            color: var(--text);
            flex-shrink: 0;
            margin-right: 8px;
            text-decoration: none;
        }

        .topbar-divider {
            width: 1px;
            height: 22px;
            background: var(--border);
            flex-shrink: 0;
        }

        .topbar-nav {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
        }

        .topbar-nav li a {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: .85rem;
            font-weight: 400;
            color: var(--muted);
            transition: background .18s, color .18s;
            white-space: nowrap;
        }

        .topbar-nav li a:hover {
            background: var(--subtle);
            color: var(--text);
        }

        .topbar-nav li.active a {
            background: rgba(192,132,252,.12);
            color: var(--music-accent);
            font-weight: 500;
        }

        .nav-icon {
            width: 16px; height: 16px;
            opacity: .7;
            flex-shrink: 0;
        }

        .topbar-nav li.active .nav-icon { opacity: 1; }

        .badge {
            background: var(--subtle);
            color: var(--muted);
            font-size: .65rem;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 20px;
        }

        .topbar-spacer { flex: 1; }

        .topbar-greeting {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            flex-shrink: 0;
        }

        .topbar-greeting strong {
            font-family: 'Instrument Serif', serif;
            font-size: 1.25rem;
            letter-spacing: -.5px;
            color: var(--text);
            line-height: 1.2;
        }

        .topbar-greeting strong span { color: var(--music-accent); }

        .topbar-greeting small {
            font-size: .72rem;
            color: var(--muted);
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px 6px 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--panel);
            transition: border-color .18s;
            flex-shrink: 0;
        }

        .topbar-user:hover { border-color: var(--muted); }

        .topbar-avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--music-accent), var(--music-accent2));
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 600; color: white;
            flex-shrink: 0;
            overflow: hidden;
        }

        .topbar-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .topbar-user-name {
            font-size: .82rem;
            font-weight: 500;
            color: var(--text);
        }

        /* ─── LAYOUT ─── */
        .mel-layout {
            position: fixed;
            top: 62px;
            left: 0; right: 0;
            bottom: 96px;
            display: flex;
            z-index: 1;
        }

        /* ─── SIDEBAR ─── */
        .mel-sidebar {
            width: 240px;
            flex-shrink: 0;
            background: rgba(18,20,26,0.7);
            border-right: 1px solid var(--border);
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            overflow-y: auto;
        }

        .mel-sidebar-section {
            padding: 0 16px;
            margin-bottom: 24px;
        }

        .mel-sidebar-label {
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
            font-size: .65rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 0 8px;
            margin-bottom: 8px;
        }

        .mel-sidebar-item {
            font-family: 'Roboto', sans-serif;
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            cursor: pointer;
            color: var(--muted);
            font-size: .85rem;
            transition: all .18s;
            margin-bottom: 2px;
        }

        .mel-sidebar-item:hover {
            background: var(--subtle);
            color: var(--text);
        }

        .mel-sidebar-item.active {
            background: rgba(192,132,252,.12);
            color: var(--music-accent);
        }

        .mel-sidebar-item svg {
            width: 16px; height: 16px;
            flex-shrink: 0;
        }

        /* ─── MAIN CONTENT ─── */
        .mel-main {
            flex: 1;
            overflow-y: auto;
            padding: 28px 32px;
            position: relative;
        }

        .mel-main::-webkit-scrollbar { width: 6px; }
        .mel-main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

        /* ─── SEARCH ─── */
        .mel-search {
            margin-bottom: 28px;
        }

        .mel-search input {
            width: 100%;
            max-width: 420px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 10px 18px 10px 42px;
            font-family: 'Outfit', sans-serif;
            font-size: .88rem;
            color: var(--text);
            outline: none;
            transition: border-color .18s;
        }

        .mel-search input::placeholder { color: var(--muted); }
        .mel-search input:focus { border-color: var(--music-accent); }

        .mel-search-wrap {
            position: relative;
            display: inline-block;
            width: 100%;
            max-width: 420px;
        }

        .mel-search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            width: 16px; height: 16px;
            pointer-events: none;
        }

        /* ─── SECTION HEADING ─── */
        .mel-section-title {
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ─── CARDS GRID ─── */
        .mel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 36px;
        }

        .mel-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all .2s;
            position: relative;
            overflow: hidden;
        }

        .mel-card:hover {
            border-color: var(--music-accent);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(192,132,252,.15);
        }

        .mel-card:hover .mel-card-play {
            opacity: 1;
            transform: scale(1);
        }

        .mel-card-thumb {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 8px;
            margin-bottom: 12px;
            position: relative;
            overflow: hidden;
        }

        .mel-card-thumb img {
            width: 100%; height: 100%;
            object-fit: cover;
        }

        .mel-card-thumb-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border-radius: 8px;
        }

        .mel-card-play {
            position: absolute;
            bottom: 8px; right: 8px;
            width: 36px; height: 36px;
            background: var(--music-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(0.8);
            transition: all .2s;
            box-shadow: 0 4px 12px rgba(0,0,0,.4);
        }

        .mel-card-play svg {
            width: 14px; height: 14px;
            color: white;
            margin-left: 2px;
        }

        .mel-card-title {
            font-family: 'Roboto', sans-serif;
            font-weight: 500;
            font-size: .85rem;
            color: var(--text);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mel-card-sub {
            font-family: 'Roboto', sans-serif;
            font-weight: 400;
            font-size: .75rem;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ─── QUEUE / LIST VIEW ─── */
        .mel-queue {
            margin-bottom: 36px;
        }

        .queue-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background .18s;
            position: relative;
        }

        .queue-item:hover { background: var(--subtle); }
        .queue-item.playing { background: rgba(192,132,252,.1); }

        .queue-num {
            width: 24px;
            text-align: center;
            font-size: .8rem;
            color: var(--muted);
            flex-shrink: 0;
        }

        .queue-item.playing .queue-num {
            color: var(--music-accent);
        }

        .queue-thumb {
            width: 40px; height: 40px;
            border-radius: 6px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            overflow: hidden;
        }

        .queue-thumb img {
            width: 100%; height: 100%;
            object-fit: cover;
        }

        .queue-info { flex: 1; min-width: 0; }

        .queue-title {
            font-family: 'Roboto', sans-serif;
            font-weight: 500;
            font-size: .85rem;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .queue-item.playing .queue-title { color: var(--music-accent); }

        .queue-artist {
            font-family: 'Roboto', sans-serif;
            font-weight: 400;
            font-size: .75rem;
            color: var(--muted);
        }

        .queue-dur {
            font-size: .75rem;
            color: var(--muted);
            flex-shrink: 0;
        }

        .playing-bars {
            display: none;
            align-items: flex-end;
            gap: 2px;
            height: 16px;
        }

        .queue-item.playing .playing-bars { display: flex; }
        .queue-item.playing .queue-num { display: none; }

        .playing-bars span {
            display: block;
            width: 3px;
            background: var(--music-accent);
            border-radius: 2px;
            animation: barBounce 0.8s ease-in-out infinite alternate;
        }

        .playing-bars span:nth-child(1) { height: 8px; animation-delay: 0s; }
        .playing-bars span:nth-child(2) { height: 14px; animation-delay: .15s; }
        .playing-bars span:nth-child(3) { height: 10px; animation-delay: .3s; }

        @keyframes barBounce {
            from { transform: scaleY(.4); }
            to   { transform: scaleY(1); }
        }

        /* ─── PLAYER BAR ─── */
        .mel-player {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 96px;
            background: rgba(18,20,26,0.98);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border);
            z-index: 200;
            display: flex;
            align-items: center;
            padding: 0 28px;
            gap: 24px;
        }

        /* Now playing info */
        .player-now {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 260px;
            flex-shrink: 0;
        }

        .player-art {
            width: 52px; height: 52px;
            border-radius: 8px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            overflow: hidden;
            background: var(--panel2);
        }

        .player-art img {
            width: 100%; height: 100%;
            object-fit: cover;
        }

        .player-info { flex: 1; min-width: 0; }

        .player-title {
            font-family: 'Roboto', sans-serif;
            font-weight: 500;
            font-size: .88rem;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .player-artist {
            font-family: 'Roboto', sans-serif;
            font-weight: 400;
            font-size: .75rem;
            color: var(--muted);
        }

        .player-like {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            padding: 6px;
            border-radius: 50%;
            transition: color .18s;
        }

        .player-like:hover,
        .player-like.liked { color: var(--music-accent); }

        /* Center controls */
        .player-controls {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .player-btns {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .ctrl-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            padding: 6px;
            border-radius: 50%;
            transition: color .18s, transform .12s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ctrl-btn:hover { color: var(--text); transform: scale(1.1); }
        .ctrl-btn.active { color: var(--music-accent); }

        .ctrl-btn svg { width: 18px; height: 18px; }

        .play-btn {
            width: 40px; height: 40px;
            background: var(--text);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            transition: transform .12s, background .18s;
            flex-shrink: 0;
        }

        .play-btn:hover { transform: scale(1.06); background: white; }
        .play-btn svg { width: 16px; height: 16px; color: #12141a; margin-left: 2px; }
        .play-btn.paused svg { margin-left: 2px; }

        .player-progress {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            max-width: 480px;
        }

        .prog-time {
            font-size: .72rem;
            color: var(--muted);
            min-width: 34px;
            text-align: center;
        }

        .prog-bar {
            flex: 1;
            height: 4px;
            background: var(--subtle);
            border-radius: 2px;
            cursor: pointer;
            position: relative;
        }

        .prog-fill {
            height: 100%;
            background: var(--music-accent);
            border-radius: 2px;
            position: relative;
            transition: width .1s linear;
        }

        .prog-fill::after {
            content: '';
            position: absolute;
            right: -4px; top: 50%;
            transform: translateY(-50%);
            width: 10px; height: 10px;
            background: white;
            border-radius: 50%;
            opacity: 0;
            transition: opacity .18s;
        }

        .prog-bar:hover .prog-fill::after { opacity: 1; }

        /* Volume */
        .player-vol {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 200px;
            flex-shrink: 0;
            justify-content: flex-end;
        }

        .vol-bar {
            width: 90px;
            height: 4px;
            background: var(--subtle);
            border-radius: 2px;
            cursor: pointer;
        }

        .vol-fill {
            height: 100%;
            background: var(--muted);
            border-radius: 2px;
            transition: background .18s;
        }

        .vol-bar:hover .vol-fill { background: var(--text); }

        /* ─── YOUTUBE IFRAME ─── */
        #yt-player {
            position: fixed;
            bottom: 96px;
            right: 20px;
            width: 320px;
            height: 180px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            z-index: 150;
            box-shadow: 0 8px 32px rgba(0,0,0,.5);
            display: none;
            background: #000;
        }

        #yt-player.visible { display: block; }
        #yt-player iframe { width: 100%; height: 100%; border: none; }

        .yt-close {
            position: absolute;
            top: 8px; right: 8px;
            width: 24px; height: 24px;
            background: rgba(0,0,0,.6);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        /* ─── HERO BANNER ─── */
        .mel-hero {
            height: 180px;
            border-radius: 16px;
            margin-bottom: 32px;
            background: linear-gradient(135deg, rgba(192,132,252,.3), rgba(232,121,249,.15), rgba(74,143,255,.2));
            border: 1px solid rgba(192,132,252,.2);
            display: flex;
            align-items: center;
            padding: 28px 32px;
            position: relative;
            overflow: hidden;
            animation: fadeUp .5s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .mel-hero::before {
            content: '';
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 120px;
            opacity: .06;
            line-height: 1;
        }

        .mel-hero-text h1 {
            font-family: 'Instrument Serif', serif;
            font-size: 2rem;
            color: var(--text);
            margin-bottom: 6px;
        }

        .mel-hero-text h1 span { color: var(--music-accent); }

        .mel-hero-text p {
            font-size: .85rem;
            color: var(--muted);
            max-width: 380px;
        }

        /* gradient for cards */
        .grad-purple { background: linear-gradient(135deg, #4c1d95, #7c3aed); }
        .grad-pink   { background: linear-gradient(135deg, #831843, #db2777); }
        .grad-blue   { background: linear-gradient(135deg, #1e3a5f, #3b82f6); }
        .grad-teal   { background: linear-gradient(135deg, #134e4a, #14b8a6); }
        .grad-orange { background: linear-gradient(135deg, #7c2d12, #ea580c); }
        .grad-green  { background: linear-gradient(135deg, #14532d, #22c55e); }

        /* Tabs */
        .mel-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 28px;
        }

        .mel-tab {
            padding: 7px 18px;
            border-radius: 20px;
            font-size: .83rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--border);
            background: none;
            color: var(--muted);
            transition: all .18s;
            font-family: 'Outfit', sans-serif;
        }

        .mel-tab:hover { background: var(--subtle); color: var(--text); }
        .mel-tab.active { background: rgba(192,132,252,.15); border-color: var(--music-accent); color: var(--music-accent); }
    </style>
</head>
<body>
<script>(function(){if(window.self===window.top){window.location.replace('/shell.php?page=melodie');}}());</script>

<video class="bg-video" src="/assets/bg.mp4" autoplay muted loop playsinline></video>

<!-- ───── TOPBAR ───── -->
<header class="topbar">
    <a href="/dashboard.php" class="topbar-brand">Fini.</a>
    <div class="topbar-divider"></div>

    <ul class="topbar-nav">
        <li>
            <a href="/dashboard.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                <span>Dashboard</span>
                <?php if ($incompleteTasks > 0): ?>
                <span class="badge"><?= $incompleteTasks ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/dashboard/Mytasks.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                <span>My Tasks</span>
                <?php if ($incompleteTasks > 0): ?>
                <span class="badge"><?= $incompleteTasks ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/dashboard/Calendar.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                <span>Calendar</span>
            </a>
        </li>
        <li>
            <a href="/dashboard/Analytics.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                <span>Analytics</span>
            </a>
        </li>
        <li class="active">
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
        <strong>Bonjour, <?= htmlspecialchars($username) ?><span>.</span></strong>
        <small>Your music, your focus.</small>
    </div>

    <div class="topbar-divider"></div>

    <a href="/dashboard/settings.php" class="topbar-user" style="text-decoration:none;cursor:pointer;">
        <div class="topbar-avatar">
            <?= strtoupper(substr($username, 0, 1)) ?>
        </div>
        <span class="topbar-user-name"><?= htmlspecialchars($username) ?></span>
    </a>
</header>

<!-- ───── LAYOUT ───── -->
<div class="mel-layout">

    <!-- Sidebar -->
    <aside class="mel-sidebar">
        <div class="mel-sidebar-section">
            <div class="mel-sidebar-label">Library</div>
            <div class="mel-sidebar-item active" data-tab="home">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                Home
            </div>
            <div class="mel-sidebar-item" data-tab="focus">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                Focus Mode
            </div>
            <div class="mel-sidebar-item" data-tab="liked">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
                Liked Songs
            </div>
        </div>

        <div class="mel-sidebar-section">
            <div class="mel-sidebar-label">Playlists</div>
            <div class="mel-sidebar-item" data-tab="playlist" data-playlist="opm">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
                OPM Hits
            </div>
            <div class="mel-sidebar-item" data-tab="playlist" data-playlist="popHits">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
                Pop Hits
            </div>
            <div class="mel-sidebar-item" data-tab="playlist" data-playlist="rnbSoul">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
                R&amp;B / Soul
            </div>
            <div class="mel-sidebar-item" data-tab="playlist" data-playlist="indie">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
                Indie &amp; Alt
            </div>
            <div class="mel-sidebar-item" data-tab="playlist" data-playlist="focus2">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
                Deep Focus
            </div>
            <div class="mel-sidebar-item" data-tab="playlist" data-playlist="classics">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
                Timeless Classics
            </div>
        </div>

        <div class="mel-sidebar-section" style="margin-top:auto">
            <div class="mel-sidebar-label">Search</div>
            <div class="mel-sidebar-item" id="ytSearchToggle">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                Search
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <main class="mel-main" id="melMain">

        <!-- HOME TAB -->
        <div id="tab-home">
            <div class="mel-hero">
                <div class="mel-hero-text">
                    <h1>mélodie</h1>
                    <p>Your personal soundtrack. OPM, Pop, Indie, R&amp;B, and focus music — all in one place while you work.</p>
                </div>
            </div>

            <div class="mel-section-title">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" style="color:var(--music-accent)"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                Quick Picks
            </div>

            <div class="mel-grid" id="playlistGrid">
                <!-- populated by JS -->
            </div>

            <div class="mel-section-title" style="margin-top:4px">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" style="color:var(--music-accent2)"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
                Now in Queue
            </div>

            <div class="mel-queue" id="queueList">
                <!-- populated by JS -->
            </div>
        </div>

        <!-- FOCUS TAB -->
        <div id="tab-focus" style="display:none">
            <div class="mel-hero" style="background: linear-gradient(135deg,rgba(74,143,255,.3),rgba(82,214,138,.15),rgba(192,132,252,.2));">
                <div class="mel-hero-text">
                    <h1>Focus <span style="color:var(--accent2)">Mode</span></h1>
                    <p>Curated ambient & instrumental tracks to help you get into deep work.</p>
                </div>
            </div>
            <div class="mel-queue" id="focusList"></div>
        </div>

        <!-- LIKED TAB -->
        <div id="tab-liked" style="display:none">
            <div class="mel-section-title">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" style="color:var(--music-accent)"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
                Liked Songs
            </div>
            <div class="mel-queue" id="likedList">
                <div style="color:var(--muted);font-size:.85rem;padding:24px 12px;">No liked songs yet. Click the heart while a track plays to save it here.</div>
            </div>
        </div>

        <!-- PLAYLIST TAB -->
        <div id="tab-playlist" style="display:none">
            <div class="mel-section-title" id="playlistTabTitle" style="font-size:1.3rem;margin-bottom:20px"></div>
            <div class="mel-queue" id="playlistTrackList"></div>
        </div>

        <!-- YOUTUBE SEARCH TAB -->
        <div id="tab-yt" style="display:none">
            <div class="mel-section-title">
                <svg viewBox="0 0 20 20" fill="currentColor" style="width:20px;height:20px;color:var(--music-accent)"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                Search
            </div>
            <div class="mel-search" style="margin-bottom:20px">
                <div class="mel-search-wrap">
                    <svg class="mel-search-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                    <input type="text" id="ytSearchInput" placeholder="Search for songs, artists, playlists..." autocomplete="off">
                </div>
            </div>
            <div style="color:var(--muted);font-size:.85rem;padding:8px 0 16px">
                Paste a YouTube video URL to play it here, or type a search term to find it on YouTube.
            </div>
            <div id="ytResults" class="mel-queue"></div>
        </div>

    </main>
</div>

<!-- ───── YOUTUBE FLOATING PLAYER ───── -->
<div id="yt-player">
    <button class="yt-close" id="ytClose">
        <svg width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
    </button>
    <div id="ytIframeWrap"></div>
</div>

<!-- ───── PLAYER BAR ───── -->
<div class="mel-player">
    <!-- Now playing -->
    <div class="player-now">
        <div class="player-art" id="playerArt" style="display:flex;align-items:center;justify-content:center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="1.5" style="width:24px;height:24px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z"/></svg>
        </div>
        <div class="player-info">
            <div class="player-title" id="playerTitle">No track selected</div>
            <div class="player-artist" id="playerArtist">Choose a song to play</div>
        </div>
        <button class="player-like" id="likeBtn" title="Like">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
        </button>
    </div>

    <!-- Controls -->
    <div class="player-controls">
        <div class="player-btns">
            <button class="ctrl-btn" id="shuffleBtn" title="Shuffle">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/></svg>
            </button>
            <button class="ctrl-btn" id="prevBtn" title="Previous">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.445 14.832A1 1 0 0010 14v-2.798l5.445 3.63A1 1 0 0017 14V6a1 1 0 00-1.555-.832L10 8.798V6a1 1 0 00-1.555-.832l-6 4a1 1 0 000 1.664l6 4z"/></svg>
            </button>
            <button class="play-btn" id="playBtn" title="Play/Pause">
                <svg id="playIcon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
            </button>
            <button class="ctrl-btn" id="nextBtn" title="Next">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4.555 5.168A1 1 0 003 6v8a1 1 0 001.555.832L10 11.202V14a1 1 0 001.555.832l6-4a1 1 0 000-1.664l-6-4A1 1 0 0010 6v2.798L4.555 5.168z"/></svg>
            </button>
            <button class="ctrl-btn" id="repeatBtn" title="Repeat">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z"/></svg>
            </button>
        </div>
        <div class="player-progress">
            <span class="prog-time" id="timeCur">0:00</span>
            <div class="prog-bar" id="progBar">
                <div class="prog-fill" id="progFill" style="width:0%"></div>
            </div>
            <span class="prog-time" id="timeTot">0:00</span>
        </div>
    </div>

    <!-- Volume -->
    <div class="player-vol">
        <button class="ctrl-btn" id="volBtn">
            <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px"><path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.984 5.984 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.983 3.983 0 00-1.172-2.828 1 1 0 010-1.415z" clip-rule="evenodd"/></svg>
        </button>
        <div class="vol-bar" id="volBar">
            <div class="vol-fill" id="volFill" style="width:80%"></div>
        </div>
        <button class="ctrl-btn" id="openYtBtn" title="Open video view" style="margin-left:8px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
        </button>
    </div>
</div>

<script>
// ─── DATA ───────────────────────────────────────────────────────────────────
// thumbs use YouTube's thumbnail CDN: i.ytimg.com/vi/{id}/mqdefault.jpg

const T = (id) => `https://i.ytimg.com/vi/${id}/mqdefault.jpg`;

const PLAYLISTS = {
    opm: {
        name: 'OPM Hits',
        grad: 'grad-purple',
        tracks: [
            { id: 'iHSMNFollSI', title: 'Star Song', artist: 'Rob Deniel', dur: '3:41' },
            { id: 'RLzAuuqiDP8', title: 'Lifetime (Reimagined)', artist: 'Ben&Ben', dur: '4:10' },
            { id: 'jHbf5sHU7PQ', title: 'Multo (Stripped Down)', artist: 'Cup of Joe', dur: '4:02' },
            { id: 'VFEwFAUOgU4', title: 'Kabisado', artist: 'IV of Spades', dur: '3:58' },
            { id: 'GdI9A6BKUR0', title: 'Buwan', artist: 'Juan Karlos', dur: '4:12' },
            { id: 'fNiDIxMkHrQ', title: 'Pag-ibig ay Kanibalismo', artist: 'fitterkarma', dur: '3:34' },
            { id: 'wiqatnKkglo', title: 'Di Na Muli', artist: 'Ben&Ben', dur: '4:28' },
            { id: 'jOu_oAqiHiA', title: 'Anaheim', artist: 'NIKI', dur: '3:52' },
        ]
    },
    popHits: {
        name: 'Pop Hits',
        grad: 'grad-pink',
        tracks: [
            { id: 'H5v3kku4y6Q', title: 'As It Was', artist: 'Harry Styles', dur: '2:47' },
            { id: 'b1kbLwvqugk', title: 'Anti-Hero', artist: 'Taylor Swift', dur: '3:20' },
            { id: 'ekr2nIex040', title: 'Blinding Lights', artist: 'The Weeknd', dur: '3:20' },
            { id: 'nYh-n7EOtMA', title: 'Flowers', artist: 'Miley Cyrus', dur: '3:21' },
            { id: 'JGwWNGJdvx8', title: 'Shape of You', artist: 'Ed Sheeran', dur: '3:54' },
            { id: 'PT2_F-1esPk', title: 'drivers license', artist: 'Olivia Rodrigo', dur: '4:02' },
            { id: 'kTJczUoc26U', title: 'good 4 u', artist: 'Olivia Rodrigo', dur: '2:58' },
            { id: 'TUVcZfQe-Kw', title: 'Levitating', artist: 'Dua Lipa', dur: '3:23' },
        ]
    },
    rnbSoul: {
        name: 'R&B / Soul',
        grad: 'grad-orange',
        tracks: [
            { id: 'jOu_oAqiHiA', title: 'Anaheim', artist: 'NIKI', dur: '3:52' },
            { id: 'qeMFqkcPYcg', title: 'Location', artist: 'Khalid', dur: '3:37' },
            { id: '450p7goxZqg', title: 'STAY', artist: 'The Kid LAROI ft. Justin Bieber', dur: '2:21' },
            { id: 'VF-r5TtlT9w', title: 'Love Yourself', artist: 'Justin Bieber', dur: '3:38' },
            { id: 'OPf0YbXqDm0', title: 'Happy', artist: 'Pharrell Williams', dur: '3:53' },
            { id: 'tbMIRfXdRlM', title: 'Fade Into You', artist: 'Mazzy Star', dur: '5:31' },
        ]
    },
    indie: {
        name: 'Indie & Alt',
        grad: 'grad-blue',
        tracks: [
            { id: 'g7dgyYFKrw0', title: 'About You', artist: 'The 1975', dur: '4:14' },
            { id: 'O4-6VuKkLTQ', title: 'Somebody Else', artist: 'The 1975', dur: '5:40' },
            { id: '3JZ_D3ELwOQ', title: 'Robbers', artist: 'The 1975', dur: '4:09' },
            { id: '5uE-mF3SCPY', title: 'Do I Wanna Know?', artist: 'Arctic Monkeys', dur: '4:32' },
            { id: 'bpOSxM0MsIk', title: 'Fluorescent Adolescent', artist: 'Arctic Monkeys', dur: '2:57' },
            { id: 'hTWKbfoikeg', title: 'Creep', artist: 'Radiohead', dur: '3:57' },
            { id: 'xzvKjXLIRls', title: 'Chasing Cars', artist: 'Snow Patrol', dur: '4:27' },
        ]
    },
    focus2: {
        name: 'Deep Focus',
        grad: 'grad-teal',
        tracks: [
            { id: 'WPni755-Krg', title: 'Time', artist: 'Hans Zimmer', dur: '4:35' },
            { id: 'hHW1oY26kxQ', title: 'No Time for Caution', artist: 'Hans Zimmer', dur: '6:15' },
            { id: 'aA7V1cFGWxg', title: 'Experience', artist: 'Ludovico Einaudi', dur: '5:14' },
            { id: 'eFTLKWw542g', title: 'Nuvole Bianche', artist: 'Ludovico Einaudi', dur: '5:56' },
            { id: 'jfKfPfyJRdk', title: 'Lofi Hip Hop Radio', artist: 'Lofi Girl', dur: '\u221e' },
            { id: '36YnV9STBqc', title: 'Deep Focus \u2014 Coding Music', artist: 'musiclab', dur: '\u221e' },
        ]
    },
    classics: {
        name: 'Timeless Classics',
        grad: 'grad-green',
        tracks: [
            { id: 'YkgkThdzX-8', title: "Don't Stop Me Now", artist: 'Queen', dur: '3:29' },
            { id: 'yPYZpwSpKmA', title: 'Bohemian Rhapsody', artist: 'Queen', dur: '5:54' },
            { id: '1w7OgIMMRc4', title: "Sweet Child O' Mine", artist: "Guns N' Roses", dur: '5:55' },
            { id: 'lXMskKTw3Bc', title: 'Fix You', artist: 'Coldplay', dur: '4:55' },
            { id: 'FJt7gNi3Nr4', title: 'Yellow', artist: 'Coldplay', dur: '4:29' },
            { id: 'DSdCsUbkQ1M', title: 'The Scientist', artist: 'Coldplay', dur: '5:09' },
            { id: 'NUsoVlDFqZg', title: 'Come As You Are', artist: 'Nirvana', dur: '3:39' },
        ]
    }
};

// Add thumb to all tracks automatically
Object.values(PLAYLISTS).forEach(pl => {
    pl.tracks.forEach(t => { if (!t.thumb) t.thumb = T(t.id); });
});

const FEATURED = [
    { id: 'iHSMNFollSI', title: 'Star Song', artist: 'Rob Deniel', grad: 'grad-purple' },
    { id: 'H5v3kku4y6Q', title: 'As It Was', artist: 'Harry Styles', grad: 'grad-pink' },
    { id: 'b1kbLwvqugk', title: 'Anti-Hero', artist: 'Taylor Swift', grad: 'grad-blue' },
    { id: 'ekr2nIex040', title: 'Blinding Lights', artist: 'The Weeknd', grad: 'grad-orange' },
    { id: 'g7dgyYFKrw0', title: 'About You', artist: 'The 1975', grad: 'grad-teal' },
    { id: 'WPni755-Krg', title: 'Time', artist: 'Hans Zimmer', grad: 'grad-green' },
    { id: 'jOu_oAqiHiA', title: 'Anaheim', artist: 'NIKI', grad: 'grad-purple' },
    { id: 'RLzAuuqiDP8', title: 'Lifetime (Reimagined)', artist: 'Ben&Ben', grad: 'grad-pink' },
    { id: 'nYh-n7EOtMA', title: 'Flowers', artist: 'Miley Cyrus', grad: 'grad-blue' },
    { id: 'PT2_F-1esPk', title: 'drivers license', artist: 'Olivia Rodrigo', grad: 'grad-orange' },
    { id: 'lXMskKTw3Bc', title: 'Fix You', artist: 'Coldplay', grad: 'grad-teal' },
    { id: '5uE-mF3SCPY', title: 'Do I Wanna Know?', artist: 'Arctic Monkeys', grad: 'grad-green' },
].map(t => ({ ...t, thumb: T(t.id) }));

const FOCUS_TRACKS = [
    { id: 'WPni755-Krg', title: 'Time', artist: 'Hans Zimmer', dur: '4:35' },
    { id: 'aA7V1cFGWxg', title: 'Experience', artist: 'Ludovico Einaudi', dur: '5:14' },
    { id: 'eFTLKWw542g', title: 'Nuvole Bianche', artist: 'Ludovico Einaudi', dur: '5:56' },
    { id: 'hHW1oY26kxQ', title: 'No Time for Caution', artist: 'Hans Zimmer', dur: '6:15' },
    { id: 'jfKfPfyJRdk', title: 'Lofi Hip Hop Radio', artist: 'Lofi Girl', dur: '\u221e' },
    { id: '36YnV9STBqc', title: 'Deep Focus \u2014 Coding Music', artist: 'musiclab', dur: '\u221e' },
    { id: 'DSdCsUbkQ1M', title: 'The Scientist', artist: 'Coldplay', dur: '5:09' },
    { id: 'lXMskKTw3Bc', title: 'Fix You', artist: 'Coldplay', dur: '4:55' },
].map(t => ({ ...t, thumb: T(t.id) }));

// ─── STATE ──────────────────────────────────────────────────────────────────

let state = {
    queue: [...FEATURED],
    currentIdx: -1,
    playing: false,
    shuffle: false,
    repeat: false,
    volume: 0.8,
    liked: JSON.parse(localStorage.getItem('mel_liked') || '[]'),
    ytPlayer: null,
    timer: null,
    elapsed: 0,
    duration: 0,
};

// ─── YT PLAYER ──────────────────────────────────────────────────────────────

function loadYT(track) {
    const wrap = document.getElementById('ytIframeWrap');
    const playerEl = document.getElementById('yt-player');

    if (track.id) {
        // Direct video ID embed — the only reliable method
        const src = `https://www.youtube.com/embed/${track.id}?autoplay=1&enablejsapi=1&rel=0`;
        wrap.innerHTML = `<iframe src="${src}" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
        playerEl.classList.add('visible');
    } else {
        // No ID (manual search query) — open in YouTube directly
        const query = encodeURIComponent(`${track.title} ${track.artist || ''}`);
        window.open(`https://www.youtube.com/results?search_query=${query}`, '_blank');
        playerEl.classList.remove('visible');
        wrap.innerHTML = '';
    }
}

document.getElementById('ytClose').addEventListener('click', () => {
    document.getElementById('yt-player').classList.remove('visible');
    document.getElementById('ytIframeWrap').innerHTML = '';
    state.playing = false;
    updatePlayBtn();
});

document.getElementById('openYtBtn').addEventListener('click', () => {
    if (state.currentIdx >= 0) {
        loadYT(state.queue[state.currentIdx]);
        state.playing = true;
        updatePlayBtn();
    }
});

// ─── PLAY TRACK ─────────────────────────────────────────────────────────────

function playTrack(idx, queue) {
    if (queue) state.queue = queue;
    state.currentIdx = idx;
    const track = state.queue[idx];

    document.getElementById('playerTitle').textContent  = track.title;
    document.getElementById('playerArtist').textContent = track.artist;

    const art = document.getElementById('playerArt');
    if (track.thumb) {
        art.innerHTML = '<img src="' + track.thumb + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">';
    } else {
        art.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="1.5" style="width:24px;height:24px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z"/></svg>';
    }
    art.style.background = 'none';

    loadYT(track);
    state.playing = true;
    state.elapsed = 0;
    updatePlayBtn();
    updateLikeBtn();
    renderQueue(state.queue, 'queueList', idx);
    startFakeTimer(track.dur);
}

function startFakeTimer(durStr) {
    clearInterval(state.timer);
    state.elapsed = 0;

    if (durStr && durStr !== '∞') {
        const parts = durStr.split(':');
        state.duration = parseInt(parts[0]) * 60 + parseInt(parts[1]);
    } else {
        state.duration = 3600;
    }

    document.getElementById('timeTot').textContent = durStr === '∞' ? '∞' : formatTime(state.duration);
    document.getElementById('timeCur').textContent = '0:00';
    document.getElementById('progFill').style.width = '0%';

    state.timer = setInterval(() => {
        if (!state.playing) return;
        state.elapsed++;
        const pct = Math.min((state.elapsed / state.duration) * 100, 100);
        document.getElementById('progFill').style.width = pct + '%';
        document.getElementById('timeCur').textContent = formatTime(state.elapsed);
        if (state.elapsed >= state.duration && durStr !== '∞') {
            clearInterval(state.timer);
            if (state.repeat) playTrack(state.currentIdx);
            else if (state.currentIdx < state.queue.length - 1) playTrack(state.currentIdx + 1);
        }
    }, 1000);
}

function formatTime(s) {
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return m + ':' + String(sec).padStart(2, '0');
}

// ─── CONTROLS ───────────────────────────────────────────────────────────────

document.getElementById('playBtn').addEventListener('click', () => {
    if (state.currentIdx < 0) { playTrack(0); return; }
    state.playing = !state.playing;
    updatePlayBtn();
    // try to post message to iframe
    const iframe = document.querySelector('#ytIframeWrap iframe');
    if (iframe) {
        iframe.contentWindow.postMessage(JSON.stringify({
            event: 'command', func: state.playing ? 'playVideo' : 'pauseVideo'
        }), '*');
    }
});

document.getElementById('prevBtn').addEventListener('click', () => {
    if (state.currentIdx > 0) playTrack(state.currentIdx - 1);
});

document.getElementById('nextBtn').addEventListener('click', () => {
    if (state.shuffle) {
        let idx = Math.floor(Math.random() * state.queue.length);
        playTrack(idx);
    } else if (state.currentIdx < state.queue.length - 1) {
        playTrack(state.currentIdx + 1);
    }
});

document.getElementById('shuffleBtn').addEventListener('click', () => {
    state.shuffle = !state.shuffle;
    document.getElementById('shuffleBtn').classList.toggle('active', state.shuffle);
});

document.getElementById('repeatBtn').addEventListener('click', () => {
    state.repeat = !state.repeat;
    document.getElementById('repeatBtn').classList.toggle('active', state.repeat);
});

function updatePlayBtn() {
    const icon = document.getElementById('playIcon');
    if (state.playing) {
        icon.innerHTML = '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>';
    } else {
        icon.innerHTML = '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>';
    }
}

// ─── LIKE ────────────────────────────────────────────────────────────────────

document.getElementById('likeBtn').addEventListener('click', () => {
    if (state.currentIdx < 0) return;
    const track = state.queue[state.currentIdx];
    const idx = state.liked.findIndex(t => t.id === track.id);
    if (idx >= 0) state.liked.splice(idx, 1);
    else state.liked.push(track);
    localStorage.setItem('mel_liked', JSON.stringify(state.liked));
    updateLikeBtn();
    renderLiked();
});

function updateLikeBtn() {
    if (state.currentIdx < 0) return;
    const track = state.queue[state.currentIdx];
    const liked = state.liked.some(t => t.id === track.id);
    document.getElementById('likeBtn').classList.toggle('liked', liked);
}

// ─── VOLUME ──────────────────────────────────────────────────────────────────

document.getElementById('volBar').addEventListener('click', (e) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const pct = (e.clientX - rect.left) / rect.width;
    state.volume = Math.max(0, Math.min(1, pct));
    document.getElementById('volFill').style.width = (state.volume * 100) + '%';
});

// ─── PROGRESS BAR ────────────────────────────────────────────────────────────

document.getElementById('progBar').addEventListener('click', (e) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const pct = (e.clientX - rect.left) / rect.width;
    state.elapsed = Math.floor(pct * state.duration);
    document.getElementById('progFill').style.width = (pct * 100) + '%';
    document.getElementById('timeCur').textContent = formatTime(state.elapsed);
});

// ─── RENDER QUEUE ────────────────────────────────────────────────────────────

function renderQueue(tracks, containerId, activeIdx) {
    const el = document.getElementById(containerId);
    if (!el) return;
    el.innerHTML = tracks.map((t, i) => `
        <div class="queue-item ${i === activeIdx ? 'playing' : ''}" data-idx="${i}" data-container="${containerId}">
            <div class="queue-num">${i + 1}</div>
            <div class="playing-bars">
                <span></span><span></span><span></span>
            </div>
            <div class="queue-thumb" style="background:var(--panel2);overflow:hidden;">
                ${t.thumb ? `<img src="${t.thumb}" alt="" style="width:100%;height:100%;object-fit:cover;">` : `<svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="1.5" style="width:18px;height:18px;margin:auto;display:block"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z"/></svg>`}
            </div>
            <div class="queue-info">
                <div class="queue-title">${t.title}</div>
                <div class="queue-artist">${t.artist}</div>
            </div>
            <div class="queue-dur">${t.dur || ''}</div>
        </div>
    `).join('');

    el.querySelectorAll('.queue-item').forEach(item => {
        item.addEventListener('click', () => {
            const idx = parseInt(item.dataset.idx);
            playTrack(idx, tracks);
        });
    });
}

// ─── RENDER PLAYLISTS GRID ───────────────────────────────────────────────────

function renderPlaylistGrid() {
    const grid = document.getElementById('playlistGrid');
    grid.innerHTML = FEATURED.map((t, i) => `
        <div class="mel-card" data-idx="${i}">
            <div class="mel-card-thumb" style="position:relative;overflow:hidden;border-radius:8px;">
                ${t.thumb ? `<img src="${t.thumb}" alt="${t.title}" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">` : `<div class="mel-card-thumb-placeholder ${t.grad}" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="1.5" style="width:40px;height:40px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z"/></svg></div>`}
                <div class="mel-card-play">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <div class="mel-card-title">${t.title}</div>
            <div class="mel-card-sub">${t.artist}</div>
        </div>
    `).join('');

    grid.querySelectorAll('.mel-card').forEach(card => {
        card.addEventListener('click', () => {
            const idx = parseInt(card.dataset.idx);
            playTrack(idx, FEATURED);
        });
    });
}

function renderLiked() {
    const el = document.getElementById('likedList');
    if (!state.liked.length) {
        el.innerHTML = '<div style="color:var(--muted);font-size:.85rem;padding:24px 12px;">No liked songs yet.</div>';
        return;
    }
    renderQueue(state.liked, 'likedList', -1);
}

// ─── TABS ────────────────────────────────────────────────────────────────────

function showTab(name) {
    ['home', 'focus', 'liked', 'playlist', 'yt'].forEach(t => {
        const el = document.getElementById('tab-' + t);
        if (el) el.style.display = t === name ? '' : 'none';
    });
}

document.querySelectorAll('.mel-sidebar-item').forEach(item => {
    item.addEventListener('click', () => {
        const tab  = item.dataset.tab;
        const pl   = item.dataset.playlist;

        document.querySelectorAll('.mel-sidebar-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');

        if (tab === 'home') {
            showTab('home');
        } else if (tab === 'focus') {
            showTab('focus');
            renderQueue(FOCUS_TRACKS, 'focusList', state.currentIdx);
        } else if (tab === 'liked') {
            showTab('liked');
            renderLiked();
        } else if (tab === 'playlist') {
            const pData = PLAYLISTS[pl];
            if (pData) {
                showTab('playlist');
                document.getElementById('playlistTabTitle').textContent = pData.name;
                renderQueue(pData.tracks, 'playlistTrackList', -1);
            }
        }
    });
});

document.getElementById('ytSearchToggle').addEventListener('click', () => {
    document.querySelectorAll('.mel-sidebar-item').forEach(i => i.classList.remove('active'));
    document.getElementById('ytSearchToggle').classList.add('active');
    showTab('yt');
});

// ─── YT SEARCH ───────────────────────────────────────────────────────────────

document.getElementById('ytSearchInput').addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const val = e.target.value.trim();
    if (!val) return;

    // Check if it's a YouTube URL — extract video ID and embed it directly
    const ytMatch = val.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/);
    if (ytMatch) {
        const id = ytMatch[1];
        const track = { id, title: 'YouTube Video', artist: 'YouTube', dur: '?', thumb: T(id) };
        playTrack(0, [track]);
        return;
    }

    // Generic search query — open YouTube search in a new tab
    const query = encodeURIComponent(val);
    window.open(`https://www.youtube.com/results?search_query=${query}`, '_blank');

    document.getElementById('ytResults').innerHTML = `
        <div style="padding:16px 12px;color:var(--muted);font-size:.85rem;">
            Opened YouTube search for: <span style="color:var(--text);font-weight:500">"${val.replace(/</g,'&lt;')}"</span>
            <br><br>
            <span style="font-size:.78rem;color:var(--muted);">Tip: Copy the video URL and paste it here to play it directly in the player.</span>
        </div>
    `;
});

// ─── INIT ─────────────────────────────────────────────────────────────────────

renderPlaylistGrid();
renderQueue(FEATURED, 'queueList', -1);

// ─── SHELL BRIDGE ────────────────────────────────────────────────────────────
// When running inside shell.php's iframe, we hide our own player bar and
// sync all state up to the shell instead.

const IN_SHELL = window.self !== window.top;

if (IN_SHELL) {
    // Hide only the built-in player bar and YT popup — shell owns those
    const playerEl = document.querySelector('.mel-player');
    const ytEl     = document.getElementById('yt-player');
    if (playerEl)  playerEl.style.display = 'none';
    if (ytEl)      ytEl.style.display     = 'none';

    // Remove bottom gap from layout since shell's player bar handles that
    const layout = document.querySelector('.mel-layout');
    if (layout) layout.style.bottom = '0';

    // Override loadYT to tell the shell to load the video
    window._origLoadYT = loadYT;
    window.loadYT = function(track) {
        if (track && track.id) {
            window.parent.postMessage({ type: 'fini:ytload', id: track.id }, '*');
        }
    };

    // Override playTrack to also notify shell of current track
    const _origPlayTrack = playTrack;
    window.playTrack = function(idx, queue) {
        _origPlayTrack(idx, queue);
        const track = state.queue[state.currentIdx];
        if (track) {
            window.parent.postMessage({
                type:   'fini:track',
                title:  track.title,
                artist: track.artist,
                thumb:  track.thumb || null,
                id:     track.id    || null,
                idx:    state.currentIdx,
            }, '*');
            // Also send queue so shell knows next/prev
            window.parent.postMessage({
                type:       'fini:queue',
                queue:      state.queue,
                currentIdx: state.currentIdx,
                shuffle:    state.shuffle,
                repeat:     state.repeat,
            }, '*');
        }
    };

    // Sync progress to shell every second
    setInterval(() => {
        if (!IN_SHELL) return;
        const track = state.queue[state.currentIdx];
        window.parent.postMessage({
            type:     'fini:progress',
            elapsed:  state.elapsed,
            duration: state.duration,
            durStr:   track ? (track.dur || null) : null,
        }, '*');
    }, 1000);

    // Listen for commands from shell
    window.addEventListener('message', e => {
        if (!e.data || typeof e.data !== 'object') return;
        switch (e.data.type) {
            case 'shell:play':
                state.playing = true;
                updatePlayBtn();
                window.parent.postMessage({ type: 'fini:play' }, '*');
                break;
            case 'shell:pause':
                state.playing = false;
                updatePlayBtn();
                window.parent.postMessage({ type: 'fini:pause' }, '*');
                break;
            case 'shell:prev':
                if (state.currentIdx > 0) window.playTrack(state.currentIdx - 1);
                break;
            case 'shell:next':
                if (state.shuffle) {
                    window.playTrack(Math.floor(Math.random() * state.queue.length));
                } else if (state.currentIdx < state.queue.length - 1) {
                    window.playTrack(state.currentIdx + 1);
                }
                break;
            case 'shell:shuffle':
                state.shuffle = e.data.value;
                document.getElementById('shuffleBtn').classList.toggle('active', state.shuffle);
                break;
            case 'shell:repeat':
                state.repeat = e.data.value;
                document.getElementById('repeatBtn').classList.toggle('active', state.repeat);
                break;
            case 'shell:like':
                document.getElementById('likeBtn').click();
                break;
            case 'shell:seek':
                state.elapsed = e.data.elapsed;
                const seekPct = Math.min((state.elapsed / state.duration) * 100, 100);
                document.getElementById('progFill').style.width = seekPct + '%';
                document.getElementById('timeCur').textContent = formatTime(state.elapsed);
                break;
            case 'shell:volume':
                state.volume = e.data.value;
                document.getElementById('volFill').style.width = (state.volume * 100) + '%';
                break;
        }
    });
}
</script>

<script src="/nav-intercept.js"></script>
</body>
</html>
