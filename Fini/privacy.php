<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fini — Privacy Policy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #12141a;
            --panel:    #181b24;
            --border:   #252b38;
            --accent:   #4a8fff;
            --text:     #e9eaf0;
            --muted:    #5c6478;
            --subtle:   #2e3547;
            --radius:   10px;
        }

        html, body {
            min-height: 100%;
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 28px 52px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            background: rgba(18, 20, 26, 0.92);
            backdrop-filter: blur(10px);
            z-index: 10;
        }

        .brand {
            font-family: 'Instrument Serif', serif;
            font-size: 1.35rem;
            letter-spacing: -.3px;
            color: var(--text);
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 28px;
            list-style: none;
        }

        .nav-links a {
            font-size: .85rem;
            color: var(--muted);
            text-decoration: none;
            transition: color .2s;
        }
        .nav-links a:hover { color: var(--text); }

        .hero {
            padding: 72px 52px 48px;
            max-width: 780px;
            margin: 0 auto;
        }

        .eyebrow {
            font-size: .72rem;
            font-weight: 500;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 14px;
        }

        .hero h1 {
            font-family: 'Instrument Serif', serif;
            font-size: 3rem;
            line-height: 1.12;
            letter-spacing: -.5px;
            color: var(--text);
            margin-bottom: 16px;
        }

        .hero h1 span { color: var(--accent); }
        .hero .meta { font-size: .82rem; color: var(--muted); }

        .content {
            max-width: 780px;
            margin: 0 auto;
            padding: 0 52px 80px;
        }

        .divider {
            height: 1px;
            background: var(--border);
            margin: 40px 0;
        }

        .section { margin-bottom: 40px; }

        .section h2 {
            font-family: 'Instrument Serif', serif;
            font-size: 1.4rem;
            font-weight: 400;
            color: var(--text);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section h2 .num {
            font-family: 'Outfit', sans-serif;
            font-size: .7rem;
            font-weight: 500;
            letter-spacing: 1.4px;
            color: var(--accent);
            background: rgba(74,143,255,.1);
            border: 1px solid rgba(74,143,255,.2);
            border-radius: 5px;
            padding: 3px 8px;
        }

        .section p {
            font-size: .95rem;
            line-height: 1.8;
            color: #b0b5c4;
            margin-bottom: 12px;
        }

        .section ul {
            padding-left: 0;
            list-style: none;
            margin-bottom: 12px;
        }

        .section ul li {
            font-size: .95rem;
            line-height: 1.8;
            color: #b0b5c4;
            padding-left: 20px;
            position: relative;
            margin-bottom: 6px;
        }

        .section ul li::before {
            content: '—';
            position: absolute;
            left: 0;
            color: var(--accent);
            font-size: .8rem;
        }

        /* highlight box for key info */
        .highlight-box {
            background: rgba(74,143,255,.06);
            border: 1px solid rgba(74,143,255,.15);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 16px;
        }

        .highlight-box p {
            color: #c8d4e8;
            margin: 0;
            font-size: .9rem;
        }

        .contact-box {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px 28px;
            margin-top: 48px;
        }

        .contact-box p {
            font-size: .9rem;
            color: var(--muted);
            line-height: 1.7;
        }

        .contact-box a {
            color: var(--accent);
            text-decoration: none;
        }
        .contact-box a:hover { text-decoration: underline; }

        .footer {
            border-top: 1px solid var(--border);
            padding: 28px 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer p { font-size: .78rem; color: var(--muted); }

        .footer a {
            color: var(--accent);
            text-decoration: none;
            font-size: .78rem;
        }
        .footer a:hover { text-decoration: underline; }

        .hero > *, .content > * {
            opacity: 0;
            animation: rise .5s cubic-bezier(.22,.68,0,1.15) forwards;
        }
        .hero .eyebrow { animation-delay: .05s; }
        .hero h1       { animation-delay: .12s; }
        .hero .meta    { animation-delay: .18s; }
        .content .divider              { animation-delay: .22s; }
        .content .section:nth-child(2) { animation-delay: .26s; }
        .content .section:nth-child(3) { animation-delay: .30s; }
        .content .section:nth-child(4) { animation-delay: .34s; }
        .content .section:nth-child(5) { animation-delay: .38s; }
        .content .section:nth-child(6) { animation-delay: .42s; }
        .content .section:nth-child(7) { animation-delay: .46s; }
        .content .contact-box          { animation-delay: .50s; }

        @keyframes rise {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 680px) {
            .nav, .hero, .content, .footer { padding-left: 24px; padding-right: 24px; }
            .hero h1 { font-size: 2.2rem; }
        }
    </style>
</head>
<body>

<nav class="nav">
    <a href="/login.php" class="brand">Fini.</a>
    <ul class="nav-links">
        <li><a href="/login.php">Sign In</a></li>
        <li><a href="/register.php">Register</a></li>
    </ul>
</nav>

<div class="hero">
    <p class="eyebrow">Legal</p>
    <h1>Privacy Policy<span>.</span></h1>
    <p class="meta">Last updated: <?= date('F j, Y') ?></p>
</div>

<div class="content">
    <div class="divider"></div>

    <div class="section">
        <h2><span class="num">01</span> Overview</h2>
        <div class="highlight-box">
            <p>We believe privacy is a right, not a feature. Fini collects only what's necessary to provide the service, never sells your data, and gives you full control over your information.</p>
        </div>
        <p>This Privacy Policy explains how Fini collects, uses, and protects your personal information when you use our platform.</p>
    </div>

    <div class="section">
        <h2><span class="num">02</span> Information We Collect</h2>
        <p>We collect information you provide directly to us, including:</p>
        <ul>
            <li>Account information — username, email address, and password (stored encrypted)</li>
            <li>Profile data you choose to add to your account</li>
            <li>Communications you send to us for support or feedback</li>
        </ul>
        <p>We also automatically collect limited technical data such as IP address, browser type, and pages visited to ensure service reliability and security.</p>
    </div>

    <div class="section">
        <h2><span class="num">03</span> How We Use Your Information</h2>
        <p>Your information is used solely to operate and improve Fini:</p>
        <ul>
            <li>Authenticating your account and keeping it secure</li>
            <li>Sending important account-related notifications</li>
            <li>Diagnosing and fixing technical issues</li>
            <li>Improving the platform based on usage patterns</li>
        </ul>
        <p>We do not use your data for advertising, and we never sell or share your personal information with third parties for their marketing purposes.</p>
    </div>

    <div class="section">
        <h2><span class="num">04</span> Data Storage & Security</h2>
        <p>Your data is stored on secure servers. Passwords are hashed and never stored in plain text. We use industry-standard encryption for data in transit (HTTPS/TLS).</p>
        <p>While we take every reasonable precaution, no system is perfectly secure. We encourage you to use a strong, unique password and to contact us immediately if you suspect unauthorized access.</p>
    </div>

    <div class="section">
        <h2><span class="num">05</span> Your Rights</h2>
        <p>You have full control over your data. You can:</p>
        <ul>
            <li>Access and export your personal data at any time</li>
            <li>Request correction of inaccurate information</li>
            <li>Delete your account and all associated data permanently</li>
            <li>Opt out of non-essential communications</li>
        </ul>
    </div>

    <div class="section">
        <h2><span class="num">06</span> Cookies</h2>
        <p>Fini uses only essential session cookies required for authentication and security. We do not use tracking cookies, analytics cookies, or third-party advertising cookies.</p>
    </div>

    <div class="contact-box">
        <p>Questions about your privacy or data? Contact our privacy team at <a href="mailto:privacy@fini.app">privacy@fini.app</a>. We'll respond within 5 business days.</p>
    </div>
</div>

<footer class="footer">
    <p>© <?= date('Y') ?> Fini. All rights reserved.</p>
    <a href="/terms.php">Terms of Service →</a>
</footer>

</body>
</html>
