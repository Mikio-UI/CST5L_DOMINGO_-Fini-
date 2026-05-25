<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/models/account.php';
require_once __DIR__ . '/controllers/account.php';
require_once __DIR__ . '/public/database.config.php';

$errors  = "";
$message = "";

if (isset($_GET['registered'])) {
    $message = "Account created! Please sign in.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (empty($username) || empty($password)) {
        $errors = "Please fill in all fields.";
    } else {
        $controller = new AccountController($SERVER_NAME, $USERNAME, $PASSWORD, $DB_NAME);
        $result     = $controller->login($username, $password);

        if ($result !== false) {
            $_SESSION["username"] = $username;
            $_SESSION["user_id"]  = $result;
            ob_end_clean();
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
            echo '<script>window.location.href = "/dashboard.php";</script>';
            echo '</body></html>';
            exit();
        } else {
            $errors = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fini — Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/login.css">
</head>
<body>

<div class="layout">
    <video autoplay muted loop playsinline class="bg-video">
        <source src="/assets/bg.mp4" type="video/mp4">
    </video>

    <div class="left">

        <nav class="nav">
            <span class="brand">Fini.</span>
            <ul class="nav-links">
                <li><a href="/dashboard.php">Dashboard</a></li>
                <li><a href="/register.php">Join</a></li>
            </ul>
        </nav>

        <p class="eyebrow">Welcome back</p>
        <h1>Sign in to<br>your account<span>.</span></h1>
        <p class="already">Not a member yet? <a href="/register.php">Create an account</a></p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errors) ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm" novalidate>

            <div class="form-grid">

                <div class="field">
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="e.g. bobsmith"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >
                    <span class="field-label">Username</span>
                    <span class="field-icon">
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 8a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-5.33 0-8 2.67-8 4v1h16v-1c0-1.33-2.67-4-8-4z" fill="#8899bb"/>
                        </svg>
                    </span>
                </div>

                <div class="field">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                    >
                    <span class="field-label">Password</span>
                    <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle password visibility">👁</button>
                </div>

            </div>

            <div class="row-options">
                <label class="remember-label">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="/password.php" class="forgot-link">Forgot password?</a>
            </div>

            <div class="btn-row">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('loginForm').reset()">
                    Clear
                </button>
                <button type="submit" name="login" class="btn btn-primary" id="submitBtn">
                    Sign In
                </button>
            </div>

        </form>

    </div>

    <div class="right">
        <span class="watermark">Fini.</span>
    </div>

</div>

<style>
    /* ── Transition overlay styles ── */
    #login-transition-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        pointer-events: none;
        display: none;
    }

    /* Dark curtain that sweeps across */
    #lt-curtain {
        position: absolute;
        inset: 0;
        background: #0d1117;
        transform: scaleX(0);
        transform-origin: left center;
        will-change: transform;
    }

    /* Centered logo that appears during transition */
    #lt-logo {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        font-family: 'Instrument Serif', serif;
        font-size: 3.5rem;
        color: #e9eaf0;
        letter-spacing: -1px;
    }

    #lt-logo .dot {
        color: #4a8fff;
    }

    /* Shimmer line */
    #lt-shimmer {
        position: absolute;
        top: 50%;
        left: -100%;
        width: 60%;
        height: 1px;
        background: linear-gradient(to right, transparent, rgba(74,143,255,0.6), transparent);
        transform: translateY(-50%);
    }
</style>

<!-- Transition overlay (hidden until triggered) -->
<div id="login-transition-overlay">
    <div id="lt-curtain"></div>
    <div id="lt-logo">Fini<span class="dot">.</span></div>
    <div id="lt-shimmer"></div>
</div>

<script>
    const toggleBtn = document.getElementById('togglePw');
    const pwInput   = document.getElementById('password');

    toggleBtn.addEventListener('click', () => {
        const isHidden = pwInput.type === 'password';
        pwInput.type          = isHidden ? 'text' : 'password';
        toggleBtn.textContent = isHidden ? '🙈' : '👁';
    });

    document.getElementById('loginForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const btn  = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Signing in…';

        const form = this;
        const data = new FormData(form);

        fetch(window.location.href, {
            method: 'POST',
            body: data
        })
        .then(res => res.text())
        .then(html => {
            if (html.includes('dashboard.php')) {
                // Signal the dashboard to use transition reveal
                sessionStorage.setItem('fini_login_transition', '1');
                playLoginTransition(() => {
                    window.location.href = '/dashboard.php';
                });
            } else {
                const parser   = new DOMParser();
                const doc      = parser.parseFromString(html, 'text/html');
                const newAlert = doc.querySelector('.alert');
                const oldAlert = document.querySelector('.alert');
                if (oldAlert) oldAlert.remove();
                if (newAlert) document.querySelector('h1').after(newAlert);
                btn.disabled = false;
                btn.textContent = 'Sign In';

                // Shake the form on error
                const formEl = document.getElementById('loginForm');
                formEl.style.transition = 'transform 0.08s ease';
                formEl.style.transform = 'translateX(-6px)';
                setTimeout(() => { formEl.style.transform = 'translateX(6px)'; }, 80);
                setTimeout(() => { formEl.style.transform = 'translateX(-4px)'; }, 160);
                setTimeout(() => { formEl.style.transform = 'translateX(0)'; }, 240);
            }
        })
        .catch(() => { form.submit(); });
    });

    function playLoginTransition(callback) {
        const overlay  = document.getElementById('login-transition-overlay');
        const curtain  = document.getElementById('lt-curtain');
        const logo     = document.getElementById('lt-logo');
        const shimmer  = document.getElementById('lt-shimmer');
        const left     = document.querySelector('.left');
        const right    = document.querySelector('.right');

        // Show overlay
        overlay.style.display = 'block';

        // Phase 1 (0–200ms): Fade out form content, keep brand visible
        left.style.transition  = 'opacity 0.2s ease';
        left.style.opacity     = '0';
        if (right) {
            right.style.transition = 'opacity 0.2s ease';
            right.style.opacity    = '0';
        }

        // Phase 2 (200–650ms): Curtain sweeps in from left
        setTimeout(() => {
            curtain.style.transition = 'transform 0.45s cubic-bezier(0.87, 0, 0.13, 1)';
            curtain.style.transform  = 'scaleX(1)';
        }, 180);

        // Phase 3 (600ms): Logo fades in at center
        setTimeout(() => {
            logo.style.transition = 'opacity 0.3s ease';
            logo.style.opacity    = '1';

            // Shimmer sweeps across
            shimmer.style.transition = 'left 0.7s cubic-bezier(0.4, 0, 0.2, 1)';
            shimmer.style.left       = '140%';
        }, 580);

        // Phase 4 (1050ms): Navigate
        setTimeout(() => {
            callback();
        }, 1050);
    }
</script>

</body>
</html>