<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/lib/poultry_expense_compatibility.php';

poultry_expense_compatibility_redirect(
    'broiler',
    $_GET
);
