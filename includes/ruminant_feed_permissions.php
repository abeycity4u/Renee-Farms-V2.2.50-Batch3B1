<?php
/**
 * Ruminant Feed Records Add-permission bridge.
 *
 * The legacy feed page still combines View and Record behavior internally.
 * Keep View on ruminant_feeds, while this bridge independently controls the
 * New Transaction action until the page is safely migrated to local checks.
 */

require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) return;

$path = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($path === '/ruminant/ruminant_feeds_record.php' || str_ends_with($path, '/ruminant/ruminant_feeds_record.php'))) return;

$privileged = isPlatformOwner() || hasRole('farm_admin');
$canAddRuminantFeed = $privileged || hasPermission(getUserType(), 'ruminant_feeds_add');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST' && isset($_POST['add_transaction']) && !$canAddRuminantFeed) {
    http_response_code(403);
    exit('You do not have permission to record Ruminant feed transactions.');
}

if ($method === 'GET' && !$canAddRuminantFeed) {
    $cssAsset = BASE_URL . '/assets/css/prepaint-ruminant-feed-readonly.css';
    if (function_exists('versioned_asset')) {
        $cssAsset = BASE_URL . versioned_asset('/assets/css/prepaint-ruminant-feed-readonly.css');
    }
    $css = '<link rel="stylesheet" href="'
        . htmlspecialchars(
            $cssAsset,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
        . '">';
    ob_start(static function (string $html) use ($css): string {
        if (stripos($html, '</head>') === false) return $html;
        return str_ireplace('</head>', $css . '</head>', $html);
    });
}
