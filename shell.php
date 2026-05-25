<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /Fini/login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$user_id  = (int) $_SESSION['user_id'];

require_once __DIR__ . '/public/database.config.php';
$db = $conn;
$incompleteTasks = 0;
if ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'done'");
    $s->bind_param('i', $user_id); $s->execute(); $s->bind_result($incompleteTasks); $s->fetch(); $s->close();
}

// Which inner page to start on? Default: dashboard.
$startPage = '/Fini/dashboard.php';
if (isset($_GET['page'])) {
    // Whitelist allowed pages
    $allowed = [
        'dashboard'  => '/Fini/dashboard.php',
        'mytasks'    => '/Fini/dashboard/Mytasks.php',
        'calendar'   => '/Fini/dashboard/Calendar.php',
        'analytics'  => '/Fini/dashboard/Analytics.php',
        'melodie'    => '/Fini/dashboard/melodie.php',
        'settings'   => '/Fini/dashboard/settings.php',
    ];
    $startPage = $allowed[$_GET['page']] ?? $startPage;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            if (localStorage.getItem('fini_theme') === 'light')
                document.documentElement.classList.add('light-mode');
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fini</title>
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
            --music-accent:  #c084fc;
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
        :root.light-mode .shell-player { background: rgba(240,242,248,0.98) !important; border-top-color: #dde1ed !important; }

        html, body {
            height: 100%;
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow: hidden;
        }

        /* ── content iframe fills everything except the player bar ── */
        #content-frame {
            position: fixed;
            top: 0; left: 0; right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            border: none;
            z-index: 1;
            background: var(--bg);
        }

        /* ── PERSISTENT PLAYER BAR ── */
        .shell-player {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 96px;
            background: rgba(18,20,26,0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 1px solid var(--border);
            z-index: 200;
            display: none;
            align-items: center;
            padding: 0 28px;
            gap: 24px;
        }

        /* Now playing */
        .player-now {
            display: flex; align-items: center; gap: 14px;
            width: 260px; flex-shrink: 0;
        }
        .player-art {
            width: 52px; height: 52px; border-radius: 8px;
            flex-shrink: 0; display: flex; align-items: center;
            justify-content: center; overflow: hidden; background: var(--panel2);
        }
        .player-art img { width: 100%; height: 100%; object-fit: cover; }
        .player-info { flex: 1; min-width: 0; }
        .player-title {
            font-family: 'Roboto', sans-serif; font-weight: 500; font-size: .88rem;
            color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .player-artist { font-family: 'Roboto', sans-serif; font-size: .75rem; color: var(--muted); }
        .player-like {
            background: none; border: none; cursor: pointer;
            color: var(--muted); padding: 6px; border-radius: 50%; transition: color .18s;
        }
        .player-like:hover, .player-like.liked { color: var(--music-accent); }

        /* Controls */
        .player-controls {
            flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px;
        }
        .player-btns { display: flex; align-items: center; gap: 16px; }
        .ctrl-btn {
            background: none; border: none; cursor: pointer; color: var(--muted);
            padding: 6px; border-radius: 50%; transition: color .18s, transform .12s;
            display: flex; align-items: center; justify-content: center;
        }
        .ctrl-btn:hover { color: var(--text); transform: scale(1.1); }
        .ctrl-btn.active { color: var(--music-accent); }
        .ctrl-btn svg { width: 18px; height: 18px; }
        .play-btn {
            width: 40px; height: 40px; background: var(--text); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; border: none; transition: transform .12s, background .18s; flex-shrink: 0;
        }
        .play-btn:hover { transform: scale(1.06); background: white; }
        .play-btn svg { width: 16px; height: 16px; color: #12141a; margin-left: 2px; }
        .player-progress {
            display: flex; align-items: center; gap: 10px;
            width: 100%; max-width: 480px;
        }
        .prog-time { font-size: .72rem; color: var(--muted); min-width: 34px; text-align: center; }
        .prog-bar {
            flex: 1; height: 4px; background: var(--subtle); border-radius: 2px;
            cursor: pointer; position: relative; user-select: none;
        }
        .prog-fill {
            height: 100%; background: var(--music-accent); border-radius: 2px;
            position: relative; transition: width .05s linear;
            pointer-events: none;
        }
        .prog-fill::after {
            content: ''; position: absolute; right: -5px; top: 50%;
            transform: translateY(-50%); width: 12px; height: 12px;
            background: white; border-radius: 50%;
            box-shadow: 0 1px 4px rgba(0,0,0,.4);
            opacity: 0; transition: opacity .15s;
        }
        .prog-bar:hover .prog-fill::after { opacity: 1; }
        .prog-bar.dragging .prog-fill::after { opacity: 1; }

        /* Volume */
        .player-vol {
            display: flex; align-items: center; gap: 8px;
            width: 200px; flex-shrink: 0; justify-content: flex-end;
        }
        .vol-bar { width: 90px; height: 4px; background: var(--subtle); border-radius: 2px; cursor: pointer; user-select: none; }
        .vol-fill { height: 100%; background: var(--muted); border-radius: 2px; transition: background .18s; }
        .vol-bar:hover .vol-fill { background: var(--text); }

        /* Mélodie nav button inside player bar */
        .mel-nav-btn {
            background: rgba(192,132,252,.1);
            border: 1px solid rgba(192,132,252,.25);
            color: var(--music-accent);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: .78rem;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            display: flex; align-items: center; gap: 6px;
            transition: background .18s, border-color .18s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .mel-nav-btn:hover { background: rgba(192,132,252,.2); border-color: var(--music-accent); }
        .mel-nav-btn svg { width: 14px; height: 14px; }

        /* ── YOUTUBE FLOATING PLAYER ── */
        #yt-player {
            position: fixed;
            bottom: 96px; right: 20px;
            width: 320px; height: 180px;
            border-radius: 12px; overflow: hidden;
            border: 1px solid var(--border);
            z-index: 300;
            box-shadow: 0 8px 32px rgba(0,0,0,.5);
            display: none; background: #000;
        }
        #yt-player.visible { display: block; }
        #yt-player iframe { width: 100%; height: 100%; border: none; }
        .yt-close {
            position: absolute; top: 8px; right: 8px;
            width: 24px; height: 24px; background: rgba(0,0,0,.6);
            border: none; border-radius: 50%; color: white; cursor: pointer;
            display: flex; align-items: center; justify-content: center; z-index: 10;
        }

        /* Now-playing mini pulse indicator */
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; } 50% { opacity: .3; }
        }
        .now-playing-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--music-accent);
            animation: pulse-dot 1.4s ease-in-out infinite;
            flex-shrink: 0;
        }
        .now-playing-dot.hidden { display: none; }
    </style>
</head>
<body>
<!-- Login transition: reveal overlay -->
<div id="pageReveal" style="position:fixed;inset:0;z-index:99999;pointer-events:none;display:none;">
    <!-- Dark base -->
    <div id="pr-base" style="position:absolute;inset:0;background:#0d1117;"></div>
    <!-- Logo shown during transition -->
    <div id="pr-logo" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-family:'Instrument Serif',serif;font-size:3.5rem;color:#e9eaf0;letter-spacing:-1px;opacity:1;">Fini<span style="color:#4a8fff;">.</span></div>
    <!-- Curtain that slides away to the right -->
    <div id="pr-curtain" style="position:absolute;inset:0;background:#0d1117;transform:scaleX(1);transform-origin:right center;will-change:transform;"></div>
</div>
<script>
    (function() {
        var fromLogin = sessionStorage.getItem('fini_login_transition');
        if (!fromLogin) return;
        sessionStorage.removeItem('fini_login_transition');

        var overlay = document.getElementById('pageReveal');
        var curtain = document.getElementById('pr-curtain');
        var logo    = document.getElementById('pr-logo');

        // Show overlay immediately (dashboard renders behind it)
        overlay.style.display = 'block';

        window.addEventListener('load', function() {
            // Phase 1 (0–100ms): Brief pause so user sees the logo
            setTimeout(function() {
                // Phase 2 (100–400ms): Logo fades out
                logo.style.transition = 'opacity 0.25s ease';
                logo.style.opacity    = '0';
            }, 120);

            // Phase 3 (300–750ms): Curtain sweeps right (reveals dashboard)
            setTimeout(function() {
                curtain.style.transition = 'transform 0.5s cubic-bezier(0.87, 0, 0.13, 1)';
                curtain.style.transform  = 'scaleX(0)';
            }, 300);

            // Phase 4 (800ms): Remove overlay entirely
            setTimeout(function() {
                overlay.style.transition = 'opacity 0.15s ease';
                overlay.style.opacity    = '0';
                setTimeout(function() { overlay.remove(); }, 160);
            }, 800);
        });
    })();
</script>

<!-- ── PAGE CONTENT IFRAME ── -->
<iframe id="content-frame" src="<?= htmlspecialchars($startPage) ?>" allow="autoplay; encrypted-media" allowfullscreen></iframe>

<!-- ── YOUTUBE FLOATING VIDEO PLAYER ── -->
<div id="yt-player">
    <button class="yt-close" id="ytClose">
        <svg width="10" height="10" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
    </button>
    <div id="ytIframeWrap"></div>
</div>

<!-- ── PERSISTENT PLAYER BAR ── -->
<div class="shell-player">

    <!-- Now playing info -->
    <div class="player-now">
        <div class="player-art" id="playerArt">
            <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="1.5" style="width:24px;height:24px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z"/>
            </svg>
        </div>
        <div class="player-info">
            <div class="player-title" id="playerTitle">No track selected</div>
            <div class="player-artist" id="playerArtist">Open mélodie to play music</div>
        </div>
        <div class="now-playing-dot hidden" id="nowPlayingDot"></div>
        <button class="player-like" id="likeBtn" title="Like">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>

    <!-- Transport controls -->
    <div class="player-controls">
        <div class="player-btns">
            <button class="ctrl-btn" id="shuffleBtn" title="Shuffle">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/></svg>
            </button>
            <button class="ctrl-btn" id="prevBtn" title="Previous">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M8.445 14.832A1 1 0 0010 14v-2.798l5.445 3.63A1 1 0 0017 14V6a1 1 0 00-1.555-.832L10 8.798V6a1 1 0 00-1.555-.832l-6 4a1 1 0 000 1.664l6 4z"/></svg>
            </button>
            <button class="play-btn" id="playBtn" title="Play/Pause">
                <svg id="playIcon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                </svg>
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
            <div class="prog-bar" id="progBar"><div class="prog-fill" id="progFill" style="width:0%"></div></div>
            <span class="prog-time" id="timeTot">0:00</span>
        </div>
    </div>

    <!-- Volume + open mélodie -->
    <div class="player-vol">
        <button class="ctrl-btn" id="volBtn">
            <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px">
                <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.984 5.984 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.983 3.983 0 00-1.172-2.828 1 1 0 010-1.415z" clip-rule="evenodd"/>
            </svg>
        </button>
        <div class="vol-bar" id="volBar"><div class="vol-fill" id="volFill" style="width:80%"></div></div>
        <button class="ctrl-btn" id="openYtBtn" title="Show/hide video" style="margin-left:4px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
            </svg>
        </button>
        <button class="mel-nav-btn" id="openMelodieBtn" title="Go to mélodie">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
            mélodie
        </button>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════
//  SHELL PLAYER — owns the YouTube IFrame API directly
//  Progress, seek, and volume are real — no fake timer
// ═══════════════════════════════════════════════════════════════

const T = id => `https://i.ytimg.com/vi/${id}/mqdefault.jpg`;

let state = {
    queue:      [],
    currentIdx: -1,
    playing:    false,
    shuffle:    false,
    repeat:     false,
    volume:     80,           // 0–100
    liked:      JSON.parse(localStorage.getItem('mel_liked') || '[]'),
    seeking:    false,        // true while user is dragging progress bar
};

// ── YouTube IFrame API ───────────────────────────────────────
// ytPlayer is the YT.Player instance — gives us real getCurrentTime(),
// getDuration(), seekTo(), setVolume(), etc.

let ytPlayer       = null;
let ytApiReady     = false;
let pendingVideoId = null;
let progressInterval = null;

// YT API calls this when its script finishes loading
window.onYouTubeIframeAPIReady = function () {
    ytApiReady = true;
    if (pendingVideoId) {
        createPlayer(pendingVideoId);
        pendingVideoId = null;
    }
};

function createPlayer(id) {
    const wrap = document.getElementById('ytIframeWrap');
    wrap.innerHTML = '';
    // YT.Player replaces the div with an <iframe>
    ytPlayer = new YT.Player('ytIframeWrap', {
        height: '180',
        width:  '320',
        videoId: id,
        playerVars: {
            autoplay:       1,
            enablejsapi:    1,
            rel:            0,
            modestbranding: 1,
        },
        events: {
            onReady:       onPlayerReady,
            onStateChange: onPlayerStateChange,
        }
    });
}

function loadYT(id) {
    document.getElementById('yt-player').classList.add('visible');
    clearInterval(progressInterval);

    if (ytPlayer && typeof ytPlayer.loadVideoById === 'function') {
        // Reuse existing player — swap video and volume
        ytPlayer.loadVideoById(id);
        ytPlayer.setVolume(state.volume);
        return;
    }

    // No player yet — create one, or queue if API not loaded
    ytPlayer = null;
    if (ytApiReady) {
        createPlayer(id);
    } else {
        pendingVideoId = id; // onYouTubeIframeAPIReady will call createPlayer
    }
}

function onPlayerReady(e) {
    e.target.setVolume(state.volume);
    startProgressPolling();
}

function onPlayerStateChange(e) {
    // YT.PlayerState: ENDED=0, PLAYING=1, PAUSED=2, BUFFERING=3, CUED=5
    if (e.data === YT.PlayerState.PLAYING) {
        state.playing = true;
        updatePlayBtn();
        document.getElementById('nowPlayingDot').classList.remove('hidden');
        startProgressPolling();
        postToMelodie({ type: 'shell:play' });
    } else if (e.data === YT.PlayerState.PAUSED) {
        state.playing = false;
        updatePlayBtn();
        postToMelodie({ type: 'shell:pause' });
    } else if (e.data === YT.PlayerState.ENDED) {
        state.playing = false;
        updatePlayBtn();
        clearInterval(progressInterval);
        // Auto-advance
        if (state.repeat) {
            postToMelodie({ type: 'shell:replay' });
        } else {
            postToMelodie({ type: 'shell:next' });
        }
    }
}

function startProgressPolling() {
    clearInterval(progressInterval);
    progressInterval = setInterval(() => {
        if (!ytPlayer || state.seeking) return;
        try {
            const cur = ytPlayer.getCurrentTime()  || 0;
            const dur = ytPlayer.getDuration()     || 0;
            if (!dur) return;
            const pct = Math.min((cur / dur) * 100, 100);
            document.getElementById('progFill').style.width = pct + '%';
            document.getElementById('timeCur').textContent  = formatTime(cur);
            document.getElementById('timeTot').textContent  = formatTime(dur);
        } catch(err) {}
    }, 500);
}

function closeYt() {
    clearInterval(progressInterval);
    document.getElementById('yt-player').classList.remove('visible');
    if (ytPlayer) {
        try { ytPlayer.destroy(); } catch(e) {}
        ytPlayer = null;
    }
    // YT.Player replaces the div with an iframe — restore a fresh div for next load
    const ytBox = document.getElementById('yt-player');
    const oldWrap = document.getElementById('ytIframeWrap');
    if (oldWrap) oldWrap.remove();
    const newWrap = document.createElement('div');
    newWrap.id = 'ytIframeWrap';
    ytBox.appendChild(newWrap);
    resetProgress();
}

function resetProgress() {
    document.getElementById('progFill').style.width = '0%';
    document.getElementById('timeCur').textContent  = '0:00';
    document.getElementById('timeTot').textContent  = '0:00';
}

// ── iframe navigation ────────────────────────────────────────

const frame = document.getElementById('content-frame');

function navigateTo(url) {
    frame.src = url;
    history.pushState({ url }, '', url);
}

window.addEventListener('popstate', e => {
    if (e.state && e.state.url) frame.src = e.state.url;
});

// ── Messages from inner pages ────────────────────────────────

window.addEventListener('message', e => {
    if (!e.data || typeof e.data !== 'object') return;

    // Filter out YT API own messages (they come as strings)
    if (typeof e.data === 'string') return;

    switch (e.data.type) {
        case 'fini:navigate':
            navigateTo(e.data.url);
            break;

        case 'fini:loaded':
            history.replaceState({ url: e.data.url }, '', e.data.url);
            break;

        case 'fini:track':
            applyTrackState(e.data);
            break;

        case 'fini:queue':
            state.queue      = e.data.queue;
            state.currentIdx = e.data.currentIdx;
            state.shuffle    = e.data.shuffle;
            state.repeat     = e.data.repeat;
            document.getElementById('shuffleBtn').classList.toggle('active', state.shuffle);
            document.getElementById('repeatBtn').classList.toggle('active', state.repeat);
            break;

        case 'fini:ytload':
            loadYT(e.data.id);
            break;
    }
});

// ── Apply track info to player bar ──────────────────────────

function applyTrackState(data) {
    document.getElementById('playerTitle').textContent  = data.title  || 'No track selected';
    document.getElementById('playerArtist').textContent = data.artist || '';
    const art = document.getElementById('playerArt');
    if (data.thumb) {
        art.innerHTML = `<img src="${data.thumb}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">`;
        art.style.background = 'none';
    }
    state.currentIdx = data.idx ?? -1;
    updateLikeBtn();
    document.getElementById('nowPlayingDot').classList.remove('hidden');
    // Show player bar (hidden by default or after X click)
    const playerBar = document.querySelector('.shell-player');
    playerBar.style.display = 'flex';
    document.getElementById('content-frame').style.bottom = '96px';
    document.getElementById('content-frame').style.height = 'calc(100% - 96px)';
    if (data.id) loadYT(data.id);
}

// ── Transport controls ───────────────────────────────────────

function postToMelodie(msg) {
    try { frame.contentWindow.postMessage(msg, '*'); } catch(err) {}
}

document.getElementById('playBtn').addEventListener('click', () => {
    if (!ytPlayer) {
        navigateTo('/Fini/dashboard/melodie.php');
        return;
    }
    try {
        if (state.playing) {
            ytPlayer.pauseVideo();
        } else {
            ytPlayer.playVideo();
        }
    } catch(err) {}
});

document.getElementById('prevBtn').addEventListener('click', () => {
    postToMelodie({ type: 'shell:prev' });
});

document.getElementById('nextBtn').addEventListener('click', () => {
    postToMelodie({ type: 'shell:next' });
});

document.getElementById('shuffleBtn').addEventListener('click', () => {
    state.shuffle = !state.shuffle;
    document.getElementById('shuffleBtn').classList.toggle('active', state.shuffle);
    postToMelodie({ type: 'shell:shuffle', value: state.shuffle });
});

document.getElementById('repeatBtn').addEventListener('click', () => {
    state.repeat = !state.repeat;
    document.getElementById('repeatBtn').classList.toggle('active', state.repeat);
    postToMelodie({ type: 'shell:repeat', value: state.repeat });
});

document.getElementById('likeBtn').addEventListener('click', () => {
    postToMelodie({ type: 'shell:like' });
});

document.getElementById('ytClose').addEventListener('click', () => {
    closeYt();
    state.playing = false;
    updatePlayBtn();
    document.getElementById('nowPlayingDot').classList.add('hidden');
    document.querySelector('.shell-player').style.display = 'none';
    document.getElementById('content-frame').style.bottom = '0';
    document.getElementById('content-frame').style.height = '100%';
    postToMelodie({ type: 'shell:pause' });
});

document.getElementById('openYtBtn').addEventListener('click', () => {
    const ytVisible = document.getElementById('yt-player').classList.contains('visible');
    if (ytVisible) {
        document.getElementById('yt-player').classList.remove('visible');
    } else {
        document.getElementById('yt-player').classList.add('visible');
    }
});

document.getElementById('openMelodieBtn').addEventListener('click', () => {
    navigateTo('/Fini/dashboard/melodie.php');
});

// ── Volume slider (click + drag) ─────────────────────────────

const volBar  = document.getElementById('volBar');
const volFill = document.getElementById('volFill');
let volDragging = false;

function setVolume(e) {
    const rect = volBar.getBoundingClientRect();
    const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    state.volume = Math.round(pct * 100);
    volFill.style.width = state.volume + '%';
    if (ytPlayer) {
        try { ytPlayer.setVolume(state.volume); } catch(err) {}
    }
}

volBar.addEventListener('mousedown', e => { volDragging = true; setVolume(e); });
document.addEventListener('mousemove', e => { if (volDragging) setVolume(e); });
document.addEventListener('mouseup',   () => { volDragging = false; });

// ── Progress bar (click + drag) ───────────────────────────────

const progBar  = document.getElementById('progBar');
const progFill = document.getElementById('progFill');
let progDragging = false;

function scrubTo(e) {
    if (!ytPlayer) return;
    const rect = progBar.getBoundingClientRect();
    const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    try {
        const dur = ytPlayer.getDuration() || 0;
        if (!dur) return;
        progFill.style.width = (pct * 100) + '%';
        document.getElementById('timeCur').textContent = formatTime(pct * dur);
    } catch(err) {}
}

function commitSeek(e) {
    if (!ytPlayer) return;
    const rect = progBar.getBoundingClientRect();
    const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    try {
        const dur = ytPlayer.getDuration() || 0;
        if (!dur) return;
        ytPlayer.seekTo(pct * dur, true);
    } catch(err) {}
}

progBar.addEventListener('mousedown', e => {
    progDragging  = true;
    state.seeking = true;
    progBar.classList.add('dragging');
    scrubTo(e);
});
document.addEventListener('mousemove', e => { if (progDragging) scrubTo(e); });
document.addEventListener('mouseup', e => {
    if (progDragging) {
        commitSeek(e);
        progDragging  = false;
        state.seeking = false;
        progBar.classList.remove('dragging');
    }
});

// ── Helpers ──────────────────────────────────────────────────

function updatePlayBtn() {
    const icon = document.getElementById('playIcon');
    if (state.playing) {
        icon.innerHTML = '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>';
    } else {
        icon.innerHTML = '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>';
    }
}

function updateLikeBtn() {
    if (state.currentIdx < 0 || !state.queue[state.currentIdx]) return;
    const track = state.queue[state.currentIdx];
    const liked = state.liked.some(t => t.id === track.id);
    document.getElementById('likeBtn').classList.toggle('liked', liked);
}

function formatTime(s) {
    s = Math.floor(s);
    const m = Math.floor(s / 60), sec = s % 60;
    return m + ':' + String(sec).padStart(2, '0');
}

// Push initial history entry
history.replaceState({ url: frame.src }, '', frame.src);
</script>

<!-- YouTube IFrame API — loaded async, fires onYouTubeIframeAPIReady when ready -->
<script src="https://www.youtube.com/iframe_api"></script>

</body>
</html>
