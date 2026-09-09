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
    <link rel="stylesheet" href="/assets/css/error-page.css">
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
