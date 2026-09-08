<?php
http_response_code(403);
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Access denied | Renee Farms</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #1f2937;
            background: #f6f8f6;
        }
        .card {
            width: min(560px, 100%);
            padding: 36px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
            text-align: center;
        }
        .brand { margin: 0 0 20px; font-size: 14px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .code { margin: 0; font-size: clamp(48px, 12vw, 88px); line-height: 1; font-weight: 800; }
        h1 { margin: 16px 0 8px; font-size: clamp(24px, 5vw, 32px); }
        p { margin: 0 auto 24px; max-width: 440px; color: #6b7280; line-height: 1.6; }
        a {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 10px;
            color: #fff;
            background: #1f5f3b;
            text-decoration: none;
            font-weight: 600;
        }
        a:focus-visible { outline: 3px solid #9ca3af; outline-offset: 3px; }
    </style>
</head>
<body>
<main class="card">
    <p class="brand">Renee Farms</p>
    <p class="code" aria-hidden="true">403</p>
    <h1>Access denied</h1>
    <p>You do not have permission to access this resource.</p>
    <a href="/">Return to homepage</a>
</main>
</body>
</html>
