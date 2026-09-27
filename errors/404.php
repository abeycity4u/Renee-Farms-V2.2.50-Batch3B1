<?php
require_once dirname(__DIR__) . '/includes/platform_brand.php';
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo htmlspecialchars(platform_brand_document_title('Page not found'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/css/error-page.css">
</head>
<body>
<main class="card">
    <p class="brand"><?php echo htmlspecialchars(platform_brand_product_name(), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="code" aria-hidden="true">404</p>
    <h1>Page not found</h1>
    <p>The page you requested could not be found.</p>
    <a href="/">Return to homepage</a>
</main>
</body>
</html>
