<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href="/Fini/login.php";</script>';
    exit();
}

$username        = $_SESSION['username'] ?? 'User';
$user_id         = (int) $_SESSION['user_id'];
$activeTaskCount = 0;

require_once __DIR__ . '/../public/database.config.php';
$db = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DB_NAME);
if (!$db->connect_error) {
    // Auto-migrate: add profile columns if they don't exist yet
    $db->query("ALTER TABLE accounts
        ADD COLUMN IF NOT EXISTS display_name VARCHAR(120) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS bio          TEXT         DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS gender       VARCHAR(30)  DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS location     VARCHAR(120) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS avatar_data  MEDIUMTEXT   DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS cover_data   MEDIUMTEXT   DEFAULT NULL");

    $stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'done'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($activeTaskCount);
    $stmt->fetch();
    $stmt->close();

    // Load profile data
    $stmt = $db->prepare("SELECT email, display_name, bio, gender, location, avatar_data, cover_data FROM accounts WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $profileRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $profile_email   = $profileRow['email']        ?? ($username . '@fini.app');
    $profile_name    = $profileRow['display_name'] ?: $username;
    $profile_bio     = $profileRow['bio']          ?? '';
    $profile_gender  = $profileRow['gender']       ?? '';
    $profile_loc     = $profileRow['location']     ?? '';
    $avatar_data     = $profileRow['avatar_data']  ?? '';
    $cover_data      = $profileRow['cover_data']   ?? '';

    // Sync session
    $_SESSION['avatar_data']  = $avatar_data;
    $_SESSION['cover_data']   = $cover_data;
    $_SESSION['display_name'] = $profile_name;
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
    <title>Fini — Settings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
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
        }        html, body {
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
            z-index: 10;
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
            background: rgba(74,143,255,.12);
            color: var(--accent);
            font-weight: 500;
        }

        .nav-icon { width: 16px; height: 16px; opacity: .7; flex-shrink: 0; }
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

        .topbar-greeting strong span { color: var(--accent); }
        .topbar-greeting small { font-size: .72rem; color: var(--muted); }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px 5px 5px;
            border-radius: 50px;
            background: var(--panel2);
            border: 1px solid var(--border);
            flex-shrink: 0;
        }

        .avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #6366f1);
            display: grid;
            place-items: center;
            font-size: .78rem;
            font-weight: 600;
            color: #fff;
            flex-shrink: 0;
        }

        .topbar-user-name {
            font-size: .82rem;
            font-weight: 500;
            color: var(--text);
        }

        .logout-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            padding: 4px;
            transition: color .2s;
            display: flex;
            flex-shrink: 0;
            text-decoration: none;
        }
        .logout-btn:hover { color: var(--danger); }

        /* ─── PAGE ─── */
        .page {
            padding-top: 62px;
            height: 100vh;
            overflow-y: auto;
            position: relative;
            z-index: 2;
            animation: fadeUp .5s cubic-bezier(.22,.68,0,1.15) .1s both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .page-inner {
            padding: 40px 40px;
            max-width: 1200px;
        }

        /* ─── page heading ─── */
        .page-heading {
            margin-bottom: 32px;
        }

        .page-heading h1 {
            font-family: 'Instrument Serif', serif;
            font-size: 1.9rem;
            letter-spacing: -.4px;
            color: var(--text);
            margin-bottom: 4px;
        }

        .page-heading p {
            font-size: .84rem;
            color: var(--muted);
        }

        /* ─── settings layout ─── */
        .settings-layout {
            display: grid;
            grid-template-columns: 200px 1fr 300px;
            gap: 24px;
            align-items: start;
        }

        /* ─── sidebar nav ─── */
        .settings-sidebar {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .settings-nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: .84rem;
            font-weight: 400;
            color: var(--muted);
            cursor: pointer;
            transition: background .15s, color .15s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: 'Outfit', sans-serif;
        }

        .settings-nav-item:hover {
            background: var(--subtle);
            color: var(--text);
        }

        .settings-nav-item.active {
            background: rgba(74,143,255,.12);
            color: var(--accent);
            font-weight: 500;
        }

        .settings-nav-item svg { flex-shrink: 0; opacity: .7; }
        .settings-nav-item.active svg { opacity: 1; }

        .settings-nav-divider {
            height: 1px;
            background: var(--border);
            margin: 8px 0;
        }

        /* danger nav item */
        .settings-nav-item.danger { color: var(--danger); }
        .settings-nav-item.danger:hover { background: rgba(240,112,112,.1); color: var(--danger); }

        /* ─── settings panels ─── */
        .settings-panel {
            display: none;
        }
        .settings-panel.active {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .settings-card {
            background: rgba(24,27,36,0.8);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            backdrop-filter: blur(8px);
        }

        .settings-card-title {
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 20px;
        }

        /* ─── form elements ─── */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-row:last-child { margin-bottom: 0; }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-bottom: 16px;
        }

        .form-group:last-child { margin-bottom: 0; }

        .form-label {
            font-size: .8rem;
            font-weight: 500;
            color: var(--text);
        }

        .form-input {
            background: var(--panel2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 9px 13px;
            font-family: 'Outfit', sans-serif;
            font-size: .85rem;
            color: var(--text);
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            width: 100%;
        }

        .form-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(74,143,255,.1);
        }

        .form-input::placeholder { color: var(--muted); }

        .form-hint {
            font-size: .75rem;
            color: var(--muted);
        }

        /* ─── toggle ─── */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 0;
            border-bottom: 1px solid var(--border);
        }

        .toggle-row:last-child { border-bottom: none; padding-bottom: 0; }
        .toggle-row:first-child { padding-top: 0; }

        .toggle-info { flex: 1; }

        .toggle-label {
            font-size: .88rem;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 2px;
        }

        .toggle-desc {
            font-size: .76rem;
            color: var(--muted);
        }

        .toggle {
            position: relative;
            width: 40px; height: 22px;
            flex-shrink: 0;
            margin-left: 16px;
        }

        .toggle input { opacity: 0; width: 0; height: 0; }

        .toggle-track {
            position: absolute;
            inset: 0;
            background: var(--subtle);
            border-radius: 99px;
            cursor: pointer;
            transition: background .2s;
        }

        .toggle-track::before {
            content: '';
            position: absolute;
            width: 16px; height: 16px;
            left: 3px; top: 3px;
            background: var(--muted);
            border-radius: 50%;
            transition: transform .2s, background .2s;
        }

        .toggle input:checked + .toggle-track { background: rgba(74,143,255,.25); }
        .toggle input:checked + .toggle-track::before {
            transform: translateX(18px);
            background: var(--accent);
        }

        /* ─── profile avatar section ─── */
        .avatar-section {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }

        .avatar-large {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #6366f1);
            display: grid;
            place-items: center;
            font-family: 'Instrument Serif', serif;
            font-size: 1.6rem;
            color: #fff;
            flex-shrink: 0;
        }

        .avatar-info { flex: 1; }
        .avatar-name {
            font-family: 'Instrument Serif', serif;
            font-size: 1.2rem;
            letter-spacing: -.3px;
            color: var(--text);
            margin-bottom: 3px;
        }

        .avatar-email { font-size: .8rem; color: var(--muted); }

        .btn-sm {
            padding: 6px 14px;
            border-radius: 50px;
            font-family: 'Outfit', sans-serif;
            font-size: .78rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--panel2);
            color: var(--text);
            transition: border-color .2s, background .2s;
        }
        .btn-sm:hover { border-color: var(--accent); background: var(--subtle); }

        .btn-save {
            padding: 9px 20px;
            border-radius: 50px;
            font-family: 'Outfit', sans-serif;
            font-size: .84rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            background: var(--accent);
            color: #fff;
            box-shadow: 0 4px 18px rgba(74,143,255,.3);
            transition: transform .15s, box-shadow .15s;
        }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(74,143,255,.4); }

        .btn-danger {
            padding: 9px 20px;
            border-radius: 50px;
            font-family: 'Outfit', sans-serif;
            font-size: .84rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid rgba(240,112,112,.3);
            background: rgba(240,112,112,.08);
            color: var(--danger);
            transition: background .2s, border-color .2s, transform .15s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-danger:hover {
            background: rgba(240,112,112,.15);
            border-color: rgba(240,112,112,.5);
            transform: translateY(-1px);
        }

        .card-footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* ─── logout zone ─── */
        .logout-zone {
            background: rgba(240,112,112,.05);
            border: 1px solid rgba(240,112,112,.2);
            border-radius: var(--radius);
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .logout-zone-info {}
        .logout-zone-title {
            font-size: .95rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .logout-zone-desc {
            font-size: .8rem;
            color: var(--muted);
        }

        /* ─── scrollbar ─── */
        .page::-webkit-scrollbar { width: 5px; }
        .page::-webkit-scrollbar-track { background: transparent; }
        .page::-webkit-scrollbar-thumb { background: var(--subtle); border-radius: 99px; }

        /* ─── logout modal ─── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.6);
            backdrop-filter: blur(4px);
            z-index: 100;
            display: none;
            place-items: center;
        }

        .modal-overlay.open { display: grid; }

        .modal {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 32px;
            width: 100%;
            max-width: 400px;
            animation: modalIn .25s cubic-bezier(.22,.68,0,1.15) both;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(.94) translateY(10px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: rgba(240,112,112,.1);
            border: 1px solid rgba(240,112,112,.2);
            display: grid;
            place-items: center;
            margin-bottom: 18px;
            color: var(--danger);
        }

        .modal h2 {
            font-family: 'Instrument Serif', serif;
            font-size: 1.35rem;
            letter-spacing: -.3px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .modal p {
            font-size: .84rem;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-cancel {
            padding: 9px 18px;
            border-radius: 50px;
            font-family: 'Outfit', sans-serif;
            font-size: .84rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--panel2);
            color: var(--text);
            transition: background .15s;
        }
        .btn-cancel:hover { background: var(--subtle); }

        /* ─── responsive ─── */
        @media (max-width: 820px) {
            .topbar-greeting { display: none; }
            .page-inner { padding: 24px 20px; }
            .topbar { padding: 0 20px; gap: 16px; }
            .topbar-nav li a span:not(.badge) { display: none; }
            .settings-layout { grid-template-columns: 1fr; }
            .settings-sidebar { flex-direction: row; flex-wrap: wrap; }
            .form-row { grid-template-columns: 1fr; }
        }
    
        /* ── Profile Hero Card ── */
        .profile-hero-card {
            background: rgba(24,27,36,0.85);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            backdrop-filter: blur(8px);
        }

        .profile-cover {
            height: 140px;
            background: linear-gradient(135deg, #1a2a4a 0%, #1e1b3a 40%, #2a1a3a 100%);
            position: relative;
        }

        .profile-cover::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 30% 50%, rgba(74,143,255,.25) 0%, transparent 60%),
                        radial-gradient(ellipse at 75% 30%, rgba(99,102,241,.2) 0%, transparent 50%);
            pointer-events: none;
        }

        .profile-cover img {
            width: 100%; height: 100%;
            object-fit: cover;
            position: absolute; inset: 0;
            pointer-events: none;
        }

        .cover-change-btn {
            position: absolute;
            bottom: 10px; right: 12px;
            display: flex; align-items: center; gap: 6px;
            padding: 5px 12px;
            background: rgba(0,0,0,.45);
            backdrop-filter: blur(4px);
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 500;
            color: rgba(255,255,255,.85);
            cursor: pointer;
            border: 1px solid rgba(255,255,255,.12);
            transition: background .2s;
            z-index: 2;
        }
        .cover-change-btn:hover { background: rgba(0,0,0,.65); }

        .profile-hero-body {
            display: flex;
            align-items: flex-end;
            gap: 18px;
            padding: 0 24px 22px;
            margin-top: -44px;
            position: relative;
        }

        .profile-avatar-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .profile-avatar-large {
            width: 88px; height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #6366f1);
            display: grid;
            place-items: center;
            font-family: 'Instrument Serif', serif;
            font-size: 2.2rem;
            color: #fff;
            border: 4px solid var(--panel);
            overflow: hidden;
        }

        .profile-avatar-large img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .profile-avatar-edit {
            position: absolute;
            bottom: 2px; right: 2px;
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--accent);
            display: grid;
            place-items: center;
            cursor: pointer;
            border: 2px solid var(--panel);
            color: #fff;
            transition: transform .15s;
        }
        .profile-avatar-edit:hover { transform: scale(1.12); }

        .profile-hero-info {
            padding-bottom: 4px;
        }

        .profile-hero-name {
            font-family: 'Instrument Serif', serif;
            font-size: 1.4rem;
            letter-spacing: -.3px;
            color: var(--text);
            margin-bottom: 2px;
        }

        .profile-hero-email {
            font-size: .8rem;
            color: var(--muted);
        }

        /* ── Info Table ── */
        .info-table {
            display: flex;
            flex-direction: column;
        }

        .info-row {
            display: flex;
            align-items: center;
            padding: 13px 0;
            border-bottom: 1px solid var(--border);
            gap: 16px;
        }

        .info-row:last-child { border-bottom: none; padding-bottom: 0; }
        .info-row:first-child { padding-top: 0; }

        .info-key {
            width: 160px;
            flex-shrink: 0;
            font-size: .85rem;
            font-weight: 600;
            color: var(--text);
        }

        .info-val {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .info-text {
            font-size: .85rem;
            color: var(--text);
        }

        .info-text.muted { color: var(--muted); }

        .info-edit-btn {
            font-size: .78rem;
            color: var(--accent);
            background: none;
            border: none;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            padding: 3px 8px;
            border-radius: 6px;
            transition: background .15s;
        }
        .info-edit-btn:hover { background: rgba(74,143,255,.1); }

        .info-input {
            background: var(--panel2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            font-family: 'Outfit', sans-serif;
            font-size: .85rem;
            color: var(--text);
            outline: none;
            width: 100%;
            transition: border-color .2s, box-shadow .2s;
        }
        .info-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(74,143,255,.1);
        }
        .info-hint { font-size: .74rem; color: var(--muted); }

        /* ── Two-column profile layout ── */
        .settings-layout { grid-template-columns: 200px 1fr; }

        .panels-wrap { min-width: 0; }

        .profile-two-col {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 20px;
            align-items: start;
        }

        .profile-forms { display: flex; flex-direction: column; gap: 16px; }

        /* ── Preview card (right col) ── */
        .profile-preview-col { position: sticky; top: 78px; }

        .preview-sticky { display: flex; flex-direction: column; gap: 10px; }

        .preview-card { padding: 0; overflow: hidden; text-align: center; }

        .preview-cover {
            height: 110px;
            background: linear-gradient(135deg, #1a2a4a 0%, #1e1b3a 40%, #2a1a3a 100%);
            position: relative;
            background-size: cover;
            background-position: center;
        }
        .preview-cover::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 30% 50%, rgba(74,143,255,.25) 0%, transparent 60%),
                        radial-gradient(ellipse at 75% 30%, rgba(99,102,241,.2) 0%, transparent 50%);
            pointer-events: none;
        }

        .preview-avatar-wrap {
            display: flex; justify-content: center;
            margin-top: -34px; margin-bottom: 8px; position: relative; z-index: 1;
        }

        .preview-avatar {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #6366f1);
            display: grid; place-items: center;
            font-family: 'Instrument Serif', serif;
            font-size: 1.5rem; color: #fff;
            border: 3px solid var(--panel);
            overflow: hidden;
        }

        .preview-avatar img,
        .profile-avatar-large img,
        .right-avatar img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }

        .preview-name {
            font-family: 'Instrument Serif', serif;
            font-size: 1rem; color: var(--text);
            padding: 0 14px; margin-bottom: 3px;
        }
        .preview-email { font-size: .72rem; color: var(--muted); padding: 0 14px; margin-bottom: 6px; }
        .preview-bio   { font-size: .74rem; color: var(--muted); padding: 0 14px; font-style:italic; margin-bottom: 4px; min-height:16px; }
        .preview-meta  {
            font-size: .7rem; color: var(--muted);
            padding: 0 14px 14px;
            display: flex; flex-direction: column; gap: 2px; align-items: center;
        }

        /* ── Save status & button ── */
        .save-status {
            font-size: .78rem; font-weight: 500;
            min-height: 18px; transition: opacity .3s;
        }
        .save-status.ok  { color: var(--accent2); }
        .save-status.err { color: var(--danger); }

        .btn-save {
            padding: 10px 20px; border-radius: 50px;
            font-family: 'Outfit', sans-serif; font-size: .84rem; font-weight: 500;
            cursor: pointer; border: none;
            background: var(--accent); color: #fff;
            box-shadow: 0 4px 18px rgba(74,143,255,.3);
            transition: transform .15s, box-shadow .15s, opacity .2s;
            display: flex; align-items: center; justify-content: center;
            gap: 6px; width: 100%;
        }
        .btn-save:hover    { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(74,143,255,.4); }
        .btn-save:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        @media (max-width: 960px) {
            .profile-two-col { grid-template-columns: 1fr; }
            .profile-preview-col { display: none; }
        }

        a.topbar-user { color: inherit; text-decoration: none; }
        .topbar-user:hover { background: var(--subtle) !important; }

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
</head>
<body>
<script>(function(){if(window.self===window.top){window.location.replace('/Fini/shell.php?page=settings');}}());</script>

<video autoplay muted loop playsinline class="bg-video">
    <source src="assets/bg.mp4" type="video/mp4">
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
                <?php if($activeTaskCount > 0): ?><span class="badge"><?= $activeTaskCount ?></span><?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/Fini/dashboard/mytasks.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                <span>My Tasks</span>
                <?php if($activeTaskCount > 0): ?><span class="badge"><?= $activeTaskCount ?></span><?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/Fini/dashboard/calendar.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                <span>Calendar</span>
            </a>
        </li>
        <li>
            <a href="/Fini/dashboard/analytics.php">
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
        <li class="active">
            <a href="settings.php">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                <span>Settings</span>
            </a>
        </li>
    </ul>

    <div class="topbar-spacer"></div>

    <div class="topbar-greeting">
        <strong>Settings<span>.</span></strong>
        <small>Manage your account & preferences.</small>
    </div>

    <div class="topbar-divider"></div>

    <a href="/Fini/dashboard/settings.php" class="topbar-user" title="My Profile" style="text-decoration:none;cursor:pointer;outline:2px solid var(--accent);outline-offset:3px;border-radius:8px;padding:2px 6px 2px 2px;">
        <?php $_av = $_SESSION['avatar_data'] ?? ''; if ($_av): ?><div class="avatar" id="topbarAvatar" style="background-image:url(<?= htmlspecialchars($_av) ?>);background-size:cover;background-position:center;font-size:0;"></div><?php else: ?><div class="avatar" id="topbarAvatar"><?= strtoupper(substr($username, 0, 1)) ?></div><?php endif; ?>
        <span class="topbar-user-name" id="topbarName"><?= htmlspecialchars($_SESSION['display_name'] ?? $username) ?></span>
    </a>

    <a href="/Fini/logout.php" class="logout-btn" title="Sign out" id="topbar-logout">
        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
    </a>

</header>

<!-- ───── PAGE ───── -->
<div class="page">
    <div class="page-inner">

        <div class="page-heading">
            <h1>Settings</h1>
            <p>Manage your profile, preferences, and account security.</p>
        </div>

        <div class="settings-layout">

            <!-- ── Sidebar nav ── -->
            <nav class="settings-sidebar">
                <button class="settings-nav-item active" data-panel="profile">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                    Profile
                </button>
                <button class="settings-nav-item" data-panel="notifications">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zm0 16a2 2 0 01-2-2h4a2 2 0 01-2 2z"/></svg>
                    Notifications
                </button>
                <button class="settings-nav-item" data-panel="appearance">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486z" clip-rule="evenodd"/></svg>
                    Appearance
                </button>
                <button class="settings-nav-item" data-panel="security">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    Security
                </button>
                <div class="settings-nav-divider"></div>
                <button class="settings-nav-item danger" id="open-logout-modal">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
                    Sign Out
                </button>
            </nav>

            <!-- ── Main content ── -->
            <div class="panels-wrap">

                <!-- PROFILE -->
                <div class="settings-panel active" id="panel-profile">
                    <div class="profile-two-col">

                        <!-- LEFT: forms -->
                        <div class="profile-forms">

                            <!-- Hero card -->
                            <div class="profile-hero-card">
                                <div class="profile-cover" id="profileCover" <?php if($cover_data): ?> style="background-image:url('<?= htmlspecialchars($cover_data) ?>');background-size:cover;background-position:center"<?php endif; ?>>
                                    <label class="cover-change-btn">
                                        <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"/></svg>
                                        Change Cover
                                        <input type="file" accept="image/*" id="coverInput" style="display:none">
                                    </label>
                                </div>
                                <div class="profile-hero-body">
                                    <div class="profile-avatar-wrap">
                                        <div class="profile-avatar-large" id="profileAvatarEl">
                                            <?php if ($avatar_data): ?>
                                                <img src="<?= htmlspecialchars($avatar_data) ?>" alt="avatar">
                                            <?php else: ?>
                                                <?= strtoupper(substr($username, 0, 1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <label class="profile-avatar-edit" title="Change photo">
                                            <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                                            <input type="file" accept="image/*" id="avatarInput" style="display:none">
                                        </label>
                                    </div>
                                    <div class="profile-hero-info">
                                        <div class="profile-hero-name" id="heroName"><?= htmlspecialchars($profile_name) ?></div>
                                        <div class="profile-hero-email" id="heroEmail"><?= htmlspecialchars($profile_email) ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Basic Information -->
                            <div class="settings-card">
                                <div class="settings-card-title">Basic Information</div>
                                <div class="info-table">
                                    <div class="info-row">
                                        <div class="info-key">Full Name</div>
                                        <div class="info-val">
                                            <input class="info-input" id="editFullName" type="text" value="<?= htmlspecialchars($profile_name) ?>" placeholder="Your full name">
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-key">Email Address</div>
                                        <div class="info-val">
                                            <input class="info-input" id="editEmail" type="email" value="<?= htmlspecialchars($profile_email) ?>" placeholder="you@example.com">
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-key">Username</div>
                                        <div class="info-val">
                                            <span class="info-text">@<?= htmlspecialchars($username) ?></span>
                                            <span class="info-hint">Cannot be changed</span>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-key">Password</div>
                                        <div class="info-val">
                                            <span class="info-text">••••••••</span>
                                            <button class="info-edit-btn" onclick="document.querySelector('[data-panel=security]').click()">Change password →</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Information -->
                            <div class="settings-card">
                                <div class="settings-card-title">Additional Information</div>
                                <div class="info-table">
                                    <div class="info-row">
                                        <div class="info-key">Bio</div>
                                        <div class="info-val">
                                            <input class="info-input" id="editBio" type="text" value="<?= htmlspecialchars($profile_bio) ?>" placeholder="A short bio about yourself…">
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-key">Gender</div>
                                        <div class="info-val">
                                            <select class="info-input" id="editGender" style="width:auto">
                                                <option value="">Prefer not to say</option>
                                                <option <?= $profile_gender==='Male'?'selected':'' ?>>Male</option>
                                                <option <?= $profile_gender==='Female'?'selected':'' ?>>Female</option>
                                                <option <?= $profile_gender==='Non-binary'?'selected':'' ?>>Non-binary</option>
                                                <option <?= $profile_gender==='Other'?'selected':'' ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-key">Location</div>
                                        <div class="info-val">
                                            <input class="info-input" id="editLocation" type="text" value="<?= htmlspecialchars($profile_loc) ?>" placeholder="City, Country">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- System Settings -->
                            <div class="settings-card">
                                <div class="settings-card-title">System Settings</div>
                                <div class="info-table">
                                    <div class="info-row">
                                        <div class="info-key">Language</div>
                                        <div class="info-val"><span class="info-text">System Default (English)</span></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-key">Privacy</div>
                                        <div class="info-val">
                                            <select class="info-input" style="width:auto">
                                                <option>Only I can view my profile</option>
                                                <option>Everyone can view my profile</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-key">Notifications</div>
                                        <div class="info-val">
                                            <button class="info-edit-btn" onclick="document.querySelector('[data-panel=notifications]').click()">Manage notifications →</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div><!-- /profile-forms -->

                        <!-- RIGHT: live preview card -->
                        <div class="profile-preview-col">
                            <div class="preview-sticky">
                                <div class="settings-card preview-card">
                                    <div class="settings-card-title">Preview</div>
                                    <div class="preview-cover" id="previewCoverEl" <?php if($cover_data): ?> style="background-image:url('<?= htmlspecialchars($cover_data) ?>');background-size:cover;background-position:center"<?php endif; ?>></div>
                                    <div class="preview-avatar-wrap">
                                        <div class="preview-avatar" id="previewAvatarEl">
                                            <?php if ($avatar_data): ?><img src="<?= htmlspecialchars($avatar_data) ?>" alt="avatar"><?php else: ?><?= strtoupper(substr($username, 0, 1)) ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="preview-name" id="previewNameEl"><?= htmlspecialchars($profile_name) ?></div>
                                    <div class="preview-email" id="previewEmailEl"><?= htmlspecialchars($profile_email) ?></div>
                                    <div class="preview-bio" id="previewBioEl"><?= htmlspecialchars($profile_bio) ?></div>
                                    <div class="preview-meta" id="previewMetaEl">
                                        <?php if ($profile_loc): ?><span>📍 <?= htmlspecialchars($profile_loc) ?></span><?php endif; ?>
                                        <?php if ($profile_gender): ?><span><?= htmlspecialchars($profile_gender) ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div id="saveStatus" class="save-status" style="text-align:center;min-height:18px;"></div>
                                <button class="btn-save" id="masterSaveBtn" onclick="saveAllChanges()">
                                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Save Profile
                                </button>
                            </div>
                        </div>

                    </div><!-- /profile-two-col -->
                </div><!-- /panel-profile -->

                <!-- NOTIFICATIONS -->
                <div class="settings-panel" id="panel-notifications">
                    <div class="settings-card">
                        <div class="settings-card-title">Notification Preferences</div>
                        <div class="toggle-row">
                            <div class="toggle-info"><div class="toggle-label">Task reminders</div><div class="toggle-desc">Get notified before a task is due.</div></div>
                            <label class="toggle"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="toggle-row">
                            <div class="toggle-info"><div class="toggle-label">Overdue alerts</div><div class="toggle-desc">Daily digest of tasks past their due date.</div></div>
                            <label class="toggle"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="toggle-row">
                            <div class="toggle-info"><div class="toggle-label">Weekly summary</div><div class="toggle-desc">A summary of your week every Sunday.</div></div>
                            <label class="toggle"><input type="checkbox"><span class="toggle-track"></span></label>
                        </div>
                        <div class="toggle-row">
                            <div class="toggle-info"><div class="toggle-label">Product updates</div><div class="toggle-desc">New features and announcements from Fini.</div></div>
                            <label class="toggle"><input type="checkbox"><span class="toggle-track"></span></label>
                        </div>
                        <div class="card-footer"><button class="btn-save">Save Changes</button></div>
                    </div>
                </div>

                <!-- APPEARANCE -->
                <div class="settings-panel" id="panel-appearance">
                    <div class="settings-card">
                        <div class="settings-card-title">Appearance</div>
                        <div class="toggle-row">
                            <div class="toggle-info"><div class="toggle-label">Dark mode</div><div class="toggle-desc">Use the dark theme across Fini.</div></div>
                            <label class="toggle"><input type="checkbox" id="darkModeToggle" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="toggle-row">
                            <div class="toggle-info"><div class="toggle-label">Background video</div><div class="toggle-desc">Animated background on the dashboard.</div></div>
                            <label class="toggle"><input type="checkbox" checked><span class="toggle-track"></span></label>
                        </div>
                        <div class="toggle-row">
                            <div class="toggle-info"><div class="toggle-label">Compact layout</div><div class="toggle-desc">Reduce spacing for more content on screen.</div></div>
                            <label class="toggle"><input type="checkbox"><span class="toggle-track"></span></label>
                        </div>
                        <div class="card-footer"><button class="btn-save">Save Changes</button></div>
                    </div>
                </div>

                <!-- SECURITY -->
                <div class="settings-panel" id="panel-security">
                    <div class="settings-card">
                        <div class="settings-card-title">Change Password</div>
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input class="form-input" id="currentPw" type="password" placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input class="form-input" id="newPw" type="password" placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input class="form-input" id="confirmPw" type="password" placeholder="••••••••">
                            <span class="form-hint">At least 8 characters.</span>
                        </div>
                        <div id="pwStatus" class="save-status" style="min-height:18px;margin-bottom:8px;"></div>
                        <div class="card-footer">
                            <button class="btn-save" onclick="changePassword()">Update Password</button>
                        </div>
                    </div>
                    <div class="logout-zone">
                        <div class="logout-zone-info">
                            <div class="logout-zone-title">Sign out of Fini</div>
                            <div class="logout-zone-desc">You will be redirected to the login page and your session will end.</div>
                        </div>
                        <button class="btn-danger" id="open-logout-modal-2">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
                            Sign Out
                        </button>
                    </div>
                </div>

            </div><!-- /panels-wrap -->

        </div><!-- /settings-layout -->
    </div><!-- /page-inner -->
</div><!-- /page -->

<!-- ───── LOGOUT MODAL ───── -->
<div class="modal-overlay" id="logout-modal">
    <div class="modal">
        <div class="modal-icon">
            <svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
        </div>
        <h2>Sign out?</h2>
        <p>You'll be logged out of your Fini account and redirected to the login page. Any unsaved changes will be lost.</p>
        <div class="modal-actions">
            <button class="btn-cancel" id="cancel-logout">Cancel</button>
            <a href="/Fini/logout.php" class="btn-danger" style="text-decoration:none">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
                Yes, sign out
            </a>
        </div>
    </div>
</div>

<script>
    let pendingAvatar = null;
    let pendingCover  = null;

    // ── Init preview from PHP data on page load ──
    (function initPreview() {
        <?php if ($avatar_data): ?>
        const avatarSrc = '<?= addslashes(htmlspecialchars($avatar_data)) ?>';
        document.getElementById('previewAvatarEl').innerHTML =
            `<img src="${avatarSrc}" alt="avatar">`;
        <?php endif; ?>

        <?php if ($cover_data): ?>
        const coverSrc = '<?= addslashes(htmlspecialchars($cover_data)) ?>';
        const pc = document.getElementById('previewCoverEl');
        pc.style.backgroundImage    = `url('${coverSrc}')`;
        pc.style.backgroundSize     = 'cover';
        pc.style.backgroundPosition = 'center';
        <?php endif; ?>
    })();

    // ── Live preview updaters ──
    function updatePreview() {
        const name  = document.getElementById('editFullName').value.trim();
        const email = document.getElementById('editEmail').value.trim();
        const bio   = document.getElementById('editBio').value.trim();
        const loc   = document.getElementById('editLocation').value.trim();
        const gender = document.getElementById('editGender').value;

        document.getElementById('previewNameEl').textContent  = name  || '—';
        document.getElementById('previewEmailEl').textContent = email || '';
        document.getElementById('previewBioEl').textContent   = bio   || '';
        document.getElementById('heroName').textContent  = name  || '—';
        document.getElementById('heroEmail').textContent = email || '';

        const meta = document.getElementById('previewMetaEl');
        meta.innerHTML = '';
        if (loc)    meta.innerHTML += `<span>📍 ${loc}</span>`;
        if (gender && gender !== '') meta.innerHTML += `<span>${gender}</span>`;
    }

    // ── Wire live preview to all inputs ──
    ['editFullName','editEmail','editBio','editLocation'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', updatePreview);
    });
    document.getElementById('editGender')?.addEventListener('change', updatePreview);

    // ── Avatar upload ──
    document.getElementById('avatarInput').addEventListener('change', function() {
        if (!this.files[0]) return;
        const reader = new FileReader();
        reader.onload = e => {
            pendingAvatar = e.target.result;
            // Update big hero avatar
            document.getElementById('profileAvatarEl').innerHTML = `<img src="${pendingAvatar}" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
            // Update preview card avatar
            document.getElementById('previewAvatarEl').innerHTML = `<img src="${pendingAvatar}" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
            // Update topbar avatar immediately
            const topbar = document.getElementById('topbarAvatar');
            topbar.style.backgroundImage    = `url(${pendingAvatar})`;
            topbar.style.backgroundSize     = 'cover';
            topbar.style.backgroundPosition = 'center';
            topbar.style.fontSize           = '0';
            topbar.textContent              = '';
            showStatus('Photo selected — save to apply everywhere.', 'ok');
        };
        reader.readAsDataURL(this.files[0]);
    });

    // ── Cover upload ──
    document.getElementById('coverInput').addEventListener('change', function() {
        if (!this.files[0]) return;
        const reader = new FileReader();
        reader.onload = e => {
            pendingCover = e.target.result;
            const cover = document.getElementById('profileCover');
            cover.style.backgroundImage    = `url(${pendingCover})`;
            cover.style.backgroundSize     = 'cover';
            cover.style.backgroundPosition = 'center';
            const previewCover = document.getElementById('previewCoverEl');
            previewCover.style.backgroundImage    = `url(${pendingCover})`;
            previewCover.style.backgroundSize     = 'cover';
            previewCover.style.backgroundPosition = 'center';
            showStatus('Cover selected — save to apply everywhere.', 'ok');
        };
        reader.readAsDataURL(this.files[0]);
    });

    // ── Save all profile changes ──
    async function saveAllChanges() {
        const btn = document.getElementById('masterSaveBtn');
        btn.disabled = true;
        btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="animation:spin .8s linear infinite"><path d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H8a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H12a1 1 0 110-2h4a1 1 0 011 1v4a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"/></svg> Saving…`;

        const errors = [];

        // 1. Profile fields
        try {
            const res  = await fetch('/Fini/api/Profile.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action:       'save',
                    display_name: document.getElementById('editFullName').value.trim(),
                    email:        document.getElementById('editEmail').value.trim(),
                    bio:          document.getElementById('editBio').value.trim(),
                    gender:       document.getElementById('editGender').value,
                    location:     document.getElementById('editLocation').value.trim(),
                })
            });
            const data = await res.json();
            if (!data.success) errors.push(data.error || 'Profile save failed');
            else {
                // Update topbar name
                document.getElementById('topbarName').textContent = document.getElementById('editFullName').value.trim() || '<?= htmlspecialchars($username) ?>';
            }
        } catch(e) { errors.push('Network error'); }

        // 2. Avatar
        if (pendingAvatar) {
            try {
                const res  = await fetch('/Fini/api/Profile.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'save_avatar', avatar_data: pendingAvatar })
                });
                const data = await res.json();
                if (data.success) pendingAvatar = null;
                else errors.push(data.error || 'Avatar save failed');
            } catch(e) { errors.push('Avatar network error'); }
        }

        // 3. Cover
        if (pendingCover) {
            try {
                const res  = await fetch('/Fini/api/Profile.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'save_cover', cover_data: pendingCover })
                });
                const data = await res.json();
                if (data.success) pendingCover = null;
                else errors.push(data.error || 'Cover save failed');
            } catch(e) { errors.push('Cover network error'); }
        }

        btn.disabled = false;
        btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> Save Profile`;

        if (errors.length) showStatus(errors.join(' · '), 'err');
        else {
            showStatus('✓ Saved! Changes now appear on all pages.', 'ok');
            setTimeout(() => showStatus('', ''), 4000);
        }
    }

    function showStatus(msg, type) {
        const el = document.getElementById('saveStatus');
        el.textContent = msg;
        el.className = 'save-status ' + (type || '');
    }

    // ── Change password ──
    async function changePassword() {
        const current = document.getElementById('currentPw').value;
        const newPw   = document.getElementById('newPw').value;
        const confirm = document.getElementById('confirmPw').value;
        const status  = document.getElementById('pwStatus');

        if (!current || !newPw || !confirm) { status.className='save-status err'; status.textContent='All fields required.'; return; }
        if (newPw !== confirm) { status.className='save-status err'; status.textContent='Passwords do not match.'; return; }
        if (newPw.length < 8) { status.className='save-status err'; status.textContent='Min 8 characters.'; return; }

        try {
            const res  = await fetch('/Fini/api/Profile.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action:'change_password', current_password:current, new_password:newPw, confirm_password:confirm })
            });
            const data = await res.json();
            if (data.success) {
                status.className = 'save-status ok';
                status.textContent = '✓ Password updated!';
                document.getElementById('currentPw').value = '';
                document.getElementById('newPw').value = '';
                document.getElementById('confirmPw').value = '';
            } else {
                status.className = 'save-status err';
                status.textContent = data.error || 'Failed to update password.';
            }
        } catch(e) { status.className='save-status err'; status.textContent='Network error.'; }
    }

    // ── Sidebar switching ──
    document.querySelectorAll('.settings-nav-item[data-panel]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.settings-nav-item').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('panel-' + btn.dataset.panel).classList.add('active');
        });
    });

    // ── Logout modal ──
    const modal = document.getElementById('logout-modal');
    ['open-logout-modal','open-logout-modal-2','topbar-logout'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', e => { e.preventDefault(); modal.classList.add('open'); });
    });
    document.getElementById('cancel-logout').addEventListener('click', () => modal.classList.remove('open'));
    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') modal.classList.remove('open'); });

    // ── Spin keyframe ──
    const s = document.createElement('style');
    s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(s);

    // ── Dark / Light mode ──
    const darkToggle = document.getElementById('darkModeToggle');

    function applyTheme(isDark) {
        if (isDark) {
            document.documentElement.classList.remove('light-mode');
        } else {
            document.documentElement.classList.add('light-mode');
        }
        localStorage.setItem('fini_theme', isDark ? 'dark' : 'light');
    }

    // Load saved preference on page load
    const savedTheme = localStorage.getItem('fini_theme');
    if (savedTheme === 'light') {
        darkToggle.checked = false;
        applyTheme(false);
    } else {
        darkToggle.checked = true;
        applyTheme(true);
    }

    darkToggle.addEventListener('change', () => applyTheme(darkToggle.checked));
</script>

<script src="/Fini/nav-intercept.js"></script>
</body>
</html>