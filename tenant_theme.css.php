<?php

require_once __DIR__ . '/init.php';

header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$tenantPrimaryColor = currentFarm()['primary_color'] ?? '#198754';

if (!is_string($tenantPrimaryColor)
    || !preg_match('/^#[0-9a-fA-F]{6}$/', $tenantPrimaryColor)) {
    $tenantPrimaryColor = '#198754';
}

echo ':root{'
    . '--farm-primary:' . $tenantPrimaryColor . ';'
    . '--bs-primary:var(--farm-primary);'
    . '--bs-link-color:var(--farm-primary);'
    . '--bs-link-hover-color:var(--farm-primary);'
    . '}';

echo '.btn-primary{'
    . '--bs-btn-bg:var(--farm-primary);'
    . '--bs-btn-border-color:var(--farm-primary);'
    . '--bs-btn-hover-bg:var(--farm-primary);'
    . '--bs-btn-hover-border-color:var(--farm-primary);'
    . '--bs-btn-active-bg:var(--farm-primary);'
    . '--bs-btn-active-border-color:var(--farm-primary);'
    . '}';

echo '.text-primary{color:var(--farm-primary)!important;}';
