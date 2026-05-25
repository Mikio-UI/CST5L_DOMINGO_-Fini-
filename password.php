<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

//require_once __DIR__ . '/models/account.php';
//require_once __DIR__ . '/controllers/account.php';
//require_once __DIR__ . '/public/database.config.php';

$errors  = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reset"])) {
    $email = trim($_POST["email"] ?? "");

    if (empty($email)) {
        $errors = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors = "Please enter a valid email address.";
    } else {
        // TODO: hook up your AccountController reset logic here
        // $controller = new AccountController($SERVER_NAME, $USERNAME, $PASSWORD, $DB_NAME);
        // $result = $controller->sendPasswordReset($email);

        // Placeholder: always show success to avoid email enumeration
        $message = "If that email is registered, you'll receive a reset link shortly.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fini — Forgot Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Fini/css/password.css">
</head>
<body>

<div class="layout">
    <video autoplay muted loop playsinline class="bg-video">
        <source src="assets/bg.mp4" type="video/mp4">
    </video>

    <!-- ───── LEFT ───── -->
    <div class="left">

        <nav class="nav">
            <a href="/" class="brand">Fini.</a>
            <ul class="nav-links">
                <li><a href="/">Dashboard</a></li>
                <li><a href="/Fini/register.php">Join</a></li>
            </ul>
        </nav>

        <p class="eyebrow">Account recovery</p>
        <h1>Forgot your<br>password<span>?</span></h1>
        <p class="subtitle">
            No worries. Enter the email linked to your account and we'll send you a reset link.<br><br>
            Remembered it? <a href="/Fini/login.php">Back to sign in</a>
        </p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm3.78 6.28-4.5 4.5a.75.75 0 0 1-1.06 0l-2-2a.75.75 0 1 1 1.06-1.06l1.47 1.47 3.97-3.97a.75.75 0 1 1 1.06 1.06z" fill="#52d68a"/>
                </svg>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm.75 4.75a.75.75 0 0 0-1.5 0v4a.75.75 0 0 0 1.5 0v-4zm-.75 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" fill="#f07070"/>
                </svg>
                <?= htmlspecialchars($errors) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="resetForm" novalidate>

            <div class="form-grid">
                <div class="field">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="e.g. bob@example.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        autocomplete="email"
                        required
                    >
                    <span class="field-label">Email address</span>
                    <span class="field-icon">
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14 2H2a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1zm-1.27 2L8 7.7 3.27 4h9.46zM2 12V5.16l5.37 3.76a1 1 0 0 0 1.26 0L14 5.16V12H2z" fill="#8899bb"/>
                        </svg>
                    </span>
                </div>
            </div>

            <div class="btn-row">
                <a href="/Fini/login.php" class="btn btn-secondary">
                    Back
                </a>
                <button type="submit" name="reset" class="btn btn-primary" id="submitBtn">
                    Send Reset Link
                </button>
            </div>

        </form>

    </div>

    <!-- ───── RIGHT (scenic panel) ───── -->
    <div class="right">
        <span class="watermark">Fini.</span>
    </div>

</div>

<script>
    document.getElementById('resetForm').addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        btn.disabled    = true;
        btn.textContent = 'Sending…';
    });
</script>

</body>
</html>