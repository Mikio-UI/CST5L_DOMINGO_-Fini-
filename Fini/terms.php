<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fini — Terms of Service</title>
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
            --danger:   #f07070;
            --success:  #52d68a;
            --radius:   10px;
        }

        html, body {
            min-height: 100%;
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        /* ─── nav ─── */
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
            font-weight: 400;
            transition: color .2s;
        }
        .nav-links a:hover { color: var(--text); }

        /* ─── hero ─── */
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

        .hero .meta {
            font-size: .82rem;
            color: var(--muted);
        }

        /* ─── content ─── */
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

        /* ─── contact box ─── */
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

        /* ─── footer ─── */
        .footer {
            border-top: 1px solid var(--border);
            padding: 28px 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer p {
            font-size: .78rem;
            color: var(--muted);
        }

        .footer a {
            color: var(--accent);
            text-decoration: none;
            font-size: .78rem;
        }
        .footer a:hover { text-decoration: underline; }

        /* ─── animations ─── */
        .hero > *, .content > * {
            opacity: 0;
            animation: rise .5s cubic-bezier(.22,.68,0,1.15) forwards;
        }
        .hero .eyebrow { animation-delay: .05s; }
        .hero h1       { animation-delay: .12s; }
        .hero .meta    { animation-delay: .18s; }
        .content .divider { animation-delay: .22s; }
        .content .section:nth-child(2)  { animation-delay: .26s; }
        .content .section:nth-child(3)  { animation-delay: .30s; }
        .content .section:nth-child(4)  { animation-delay: .34s; }
        .content .section:nth-child(5)  { animation-delay: .38s; }
        .content .section:nth-child(6)  { animation-delay: .42s; }
        .content .section:nth-child(7)  { animation-delay: .46s; }
        .content .contact-box { animation-delay: .50s; }

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
    <a href="/Fini/login.php" class="brand">Fini.</a>
    <ul class="nav-links">
        <li><a href="/Fini/login.php">Sign In</a></li>
        <li><a href="/Fini/register.php">Register</a></li>
    </ul>
</nav>

<div class="hero">
    <p class="eyebrow">Legal</p>
    <h1>Terms of Service<span>.</span></h1>
    <p class="meta">Last updated: <?= date('F j, Y') ?></p>
</div>

<div class="content">
    <div class="divider"></div>

    <div class="section">
        <h2><span class="num">01</span> Acceptance of Terms</h2>
        <p>By accessing or using Fini, you agree to be bound by these Terms of Service and all applicable laws and regulations. If you do not agree with any part of these terms, you may not use our service.</p>
        <p>We reserve the right to update these terms at any time. Continued use of the platform after changes constitutes your acceptance of the new terms.</p>
    </div>

    <div class="section">
        <h2><span class="num">02</span> Use of the Service</h2>
        <p>Fini grants you a limited, non-exclusive, non-transferable license to use the platform for your personal or internal business purposes. You agree not to:</p>
        <ul>
            <li>Use the service for any unlawful purpose or in violation of any regulations</li>
            <li>Attempt to gain unauthorized access to any part of the platform</li>
            <li>Transmit harmful, offensive, or disruptive content</li>
            <li>Reverse-engineer, decompile, or disassemble any part of the service</li>
            <li>Use automated tools to scrape or extract data without permission</li>
        </ul>
    </div>

    <div class="section">
        <h2><span class="num">03</span> Account Responsibility</h2>
        <p>You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. Notify us immediately of any unauthorized use or security breach.</p>
        <p>You must provide accurate, current, and complete information when creating your account and keep it up to date.</p>
    </div>

    <div class="section">
        <h2><span class="num">04</span> Intellectual Property</h2>
        <p>All content, features, and functionality of Fini — including but not limited to text, graphics, logos, and software — are the exclusive property of Fini and are protected by applicable intellectual property laws.</p>
        <p>You retain ownership of any content you submit to the platform, but grant Fini a license to use, store, and process that content to provide the service.</p>
    </div>

    <div class="section">
        <h2><span class="num">05</span> Limitation of Liability</h2>
        <p>Fini is provided on an "as is" and "as available" basis without warranties of any kind. To the fullest extent permitted by law, Fini shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of the service.</p>
    </div>

    <div class="section">
        <h2><span class="num">06</span> Termination</h2>
        <p>We may suspend or terminate your access to Fini at any time, with or without notice, for conduct that we believe violates these Terms or is harmful to other users, us, or third parties.</p>
        <p>You may delete your account at any time from your account settings.</p>
    </div>

    <div class="contact-box">
        <p>Questions about these Terms? Reach us at <a href="mailto:legal@fini.app">legal@fini.app</a>. We'll get back to you within 2 business days.</p>
    </div>
</div>

<footer class="footer">
    <p>© <?= date('Y') ?> Fini. All rights reserved.</p>
    <a href="/Fini/privacy.php">Privacy Policy →</a>
</footer>

</body>
</html>