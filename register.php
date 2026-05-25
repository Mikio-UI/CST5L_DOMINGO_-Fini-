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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email    = trim($_POST["email"]    ?? "");
    $password = trim($_POST["password"] ?? "");
    $confirm  = trim($_POST["confirm_password"] ?? "");

    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $errors = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $errors = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $errors = "Passwords do not match.";
    } else {
        $controller = new AccountController($SERVER_NAME, $USERNAME, $PASSWORD, $DB_NAME);
        $result     = $controller->register($username, $email, $password);

        if ($result === true) {
            ob_end_clean();
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
            echo '<script>window.location.href = "/Fini/login.php?registered=1";</script>';
            echo '</body></html>';
            exit();
        } else {
            $errors = is_string($result) ? $result : "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fini — Create Account</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Fini/css/register.css">
</head>
<body>

<div class="layout">
    <video autoplay muted loop playsinline class="bg-video">
        <source src="/Fini/assets/bg.mp4" type="video/mp4">
    </video>

    <!-- ───── LEFT ───── -->
    <div class="left">

        <nav class="nav">
            <span class="brand">Fini.</span>
            <ul class="nav-links">
                <li><a href="/Fini/dashboard.php">Dashboard</a></li>
                <li><a href="/Fini/login.php">Sign In</a></li>
            </ul>
        </nav>

        <p class="eyebrow">Get started</p>
        <h1>Create your<br>account<span>.</span></h1>
        <p class="already">Already a member? <a href="/Fini/login.php">Sign in</a></p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errors) ?></div>
        <?php endif; ?>

        <form method="POST" id="registerForm" novalidate>

            <div class="form-grid">

                <div class="field">
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="e.g. johndoe"
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
                        type="email"
                        id="email"
                        name="email"
                        placeholder="e.g. john@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        autocomplete="email"
                        required
                    >
                    <span class="field-label">Email</span>
                    <span class="field-icon">
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14 2H2a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1zm-1.5 2L8 7.5 3.5 4h9zM2 13V5.25l5.5 3.5a1 1 0 0 0 1 0L14 5.25V13H2z" fill="#8899bb"/>
                        </svg>
                    </span>
                </div>

                <div class="field">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="min. 8 characters"
                        autocomplete="new-password"
                        required
                    >
                    <span class="field-label">Password</span>
                    <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle password visibility">👁</button>
                    <div class="strength-bar" id="strengthBar">
                        <span></span><span></span><span></span><span></span>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>

                <div class="field">
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="repeat your password"
                        autocomplete="new-password"
                        required
                    >
                    <span class="field-label">Confirm Password</span>
                    <button type="button" class="toggle-pw" id="toggleConfirm" aria-label="Toggle confirm password visibility">👁</button>
                </div>

            </div>

            <div class="row-terms">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="/Fini/terms.php">Terms of Service</a> and <a href="/Fini/privacy.php">Privacy Policy</a></label>
            </div>

            <div class="btn-row">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('registerForm').reset(); resetStrength();">
                    Clear
                </button>
                <button type="submit" name="register" class="btn btn-primary" id="submitBtn">
                    Create Account
                </button>
            </div>

        </form>

    </div>

    <!-- ───── RIGHT ───── -->
    <div class="right">
        <span class="watermark">Fini.</span>
    </div>

</div>

<script>
    document.getElementById('togglePw').addEventListener('click', function () {
        const input = document.getElementById('password');
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        this.textContent = isHidden ? '🙈' : '👁';
    });

    document.getElementById('toggleConfirm').addEventListener('click', function () {
        const input = document.getElementById('confirm_password');
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        this.textContent = isHidden ? '🙈' : '👁';
    });

    const strengthBar   = document.getElementById('strengthBar');
    const strengthLabel = document.getElementById('strengthLabel');

    function getStrength(pw) {
        let score = 0;
        if (pw.length >= 8)  score++;
        if (pw.length >= 12) score++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        if (score <= 1) return { level: 'weak',   label: 'Weak' };
        if (score === 2) return { level: 'fair',   label: 'Fair' };
        if (score === 3) return { level: 'good',   label: 'Good' };
        return              { level: 'strong', label: 'Strong' };
    }

    function resetStrength() {
        strengthBar.className = 'strength-bar';
        strengthLabel.textContent = '';
    }

    document.getElementById('password').addEventListener('input', function () {
        if (!this.value) { resetStrength(); return; }
        const { level, label } = getStrength(this.value);
        strengthBar.className = 'strength-bar ' + level;
        strengthLabel.textContent = label;
        const colors = { weak: '#f07070', fair: '#f0a870', good: '#70c8f0', strong: '#52d68a' };
        strengthLabel.style.color = colors[level];
    });

    document.getElementById('registerForm').addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        btn.textContent = 'Creating account…';
        // NOTE: do NOT disable the button — disabled buttons don't submit their value
    });
</script>

</body>
</html>