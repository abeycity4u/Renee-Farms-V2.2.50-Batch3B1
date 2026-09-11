<?php
/**
 * V2.3 Billing Stage 2F route/request helpers.
 *
 * Browser checkout input is intentionally narrow. Billing amount/currency and
 * quote identities are never accepted from the browser; they are derived by the
 * server-side price book. Provider return URLs come from BILLING_PUBLIC_BASE_URL,
 * never from the request Host header.
 */

if (!function_exists('billing_route_public_base_url')) {
    function billing_route_public_base_url(): string
    {
        $raw = getenv('BILLING_PUBLIC_BASE_URL');
        if ($raw === false && array_key_exists('BILLING_PUBLIC_BASE_URL', $_ENV)) $raw = $_ENV['BILLING_PUBLIC_BASE_URL'];
        if ($raw === false && array_key_exists('BILLING_PUBLIC_BASE_URL', $_SERVER)) $raw = $_SERVER['BILLING_PUBLIC_BASE_URL'];
        $raw = trim((string)($raw === false ? '' : $raw));
        if ($raw === '') {
            throw new RuntimeException('Billing public URL is not configured. Missing deployment environment: BILLING_PUBLIC_BASE_URL.');
        }

        $parts = parse_url($raw);
        if ($parts === false
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || trim((string)($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new RuntimeException('BILLING_PUBLIC_BASE_URL must be an absolute HTTPS application URL without credentials, query or fragment.');
        }

        $path = rtrim((string)($parts['path'] ?? ''), '/');
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        return 'https://' . strtolower((string)$parts['host']) . $port . $path;
    }
}

if (!function_exists('billing_route_public_url')) {
    function billing_route_public_url(string $relativePath, array $query = []): string
    {
        $relativePath = '/' . ltrim(trim($relativePath), '/');
        if ($relativePath === '/' || str_contains($relativePath, "\0")) {
            throw new InvalidArgumentException('A valid billing route path is required.');
        }
        $url = billing_route_public_base_url() . $relativePath;
        if ($query) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }
}

if (!function_exists('billing_route_allowed_checkout_keys')) {
    function billing_route_allowed_checkout_keys(): array
    {
        return ['csrf_token', 'plan_code', 'billing_interval', 'modules', 'seat_addons', 'provider'];
    }
}

if (!function_exists('billing_route_reserved_billing_keys')) {
    function billing_route_reserved_billing_keys(): array
    {
        return [
            'amount', 'currency', 'price', 'total', 'package_amount', 'seat_addon_amount',
            'pricing_version', 'pricing_hash', 'quote_hash', 'provider_reference',
            'farm_id', 'user_id', 'status', 'subscription_status',
        ];
    }
}

if (!function_exists('billing_route_normalize_checkout_input')) {
    function billing_route_normalize_checkout_input(array $input): array
    {
        foreach (array_keys($input) as $key) {
            $key = (string)$key;
            if (in_array($key, billing_route_reserved_billing_keys(), true)) {
                throw new InvalidArgumentException('Billing-sensitive checkout values are server controlled.');
            }
            if (!in_array($key, billing_route_allowed_checkout_keys(), true)) {
                throw new InvalidArgumentException('Unsupported checkout field.');
            }
        }

        $planCode = strtolower(trim((string)($input['plan_code'] ?? '')));
        if (!function_exists('subscription_plan_is_valid') || !subscription_plan_is_valid($planCode)) {
            throw new InvalidArgumentException('Unknown subscription plan.');
        }

        $billingInterval = strtolower(trim((string)($input['billing_interval'] ?? '')));
        if (!in_array($billingInterval, ['monthly', 'annual'], true)) {
            throw new InvalidArgumentException('Billing interval must be monthly or annual.');
        }

        $modulesRaw = $input['modules'] ?? [];
        if (!is_array($modulesRaw)) $modulesRaw = [$modulesRaw];
        $modules = [];
        foreach ($modulesRaw as $module) {
            $module = strtolower(trim((string)$module));
            if (!in_array($module, ['poultry', 'ruminant'], true)) {
                throw new InvalidArgumentException('Checkout module must be Poultry or Ruminant.');
            }
            $modules[$module] = true;
        }
        $modules = array_keys($modules);
        sort($modules, SORT_STRING);
        if (!$modules) throw new InvalidArgumentException('Select Poultry, Ruminant, or both.');

        $seatRaw = $input['seat_addons'] ?? [];
        if (!is_array($seatRaw)) throw new InvalidArgumentException('Seat add-ons must be supplied as a role map.');
        $allowedSeatRoles = ['poultry_manager', 'ruminant_manager', 'sales_rep', 'viewer'];
        foreach (array_keys($seatRaw) as $role) {
            if (!in_array((string)$role, $allowedSeatRoles, true)) {
                throw new InvalidArgumentException('Unsupported extra-seat role.');
            }
        }
        $seatAddOns = [];
        foreach ($allowedSeatRoles as $role) {
            $raw = $seatRaw[$role] ?? 0;
            if (is_string($raw)) $raw = trim($raw);
            $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 500]]);
            if ($value === false) throw new InvalidArgumentException('Seat add-ons must be whole numbers between 0 and 500.');
            $seatAddOns[$role] = (int)$value;
        }
        ksort($seatAddOns, SORT_STRING);

        $provider = trim((string)($input['provider'] ?? ''));
        if ($provider !== '') {
            if (!function_exists('billing_provider_selection_normalize')) {
                throw new RuntimeException('Billing provider selection is unavailable.');
            }
            $provider = billing_provider_selection_normalize($provider);
        } else {
            $provider = null;
        }

        return [
            'plan_code' => $planCode,
            'billing_interval' => $billingInterval,
            'modules' => $modules,
            'seat_addons' => $seatAddOns,
            'provider' => $provider,
        ];
    }
}

if (!function_exists('billing_route_allowed_seat_topup_keys')) {
    function billing_route_allowed_seat_topup_keys(): array
    {
        return [
            'csrf_token',
            'role_code',
            'quantity',
            'provider',
        ];
    }
}

if (!function_exists('billing_route_normalize_seat_topup_input')) {
    function billing_route_normalize_seat_topup_input(
        array $input
    ): array {
        foreach (array_keys($input) as $key) {
            $key = (string)$key;

            if (in_array(
                $key,
                billing_route_reserved_billing_keys(),
                true
            )) {
                throw new InvalidArgumentException(
                    'Billing-sensitive seat-top-up values are server controlled.'
                );
            }

            if (!in_array(
                $key,
                billing_route_allowed_seat_topup_keys(),
                true
            )) {
                throw new InvalidArgumentException(
                    'Unsupported seat-top-up field.'
                );
            }
        }

        if (!function_exists('subscription_seat_roles')) {
            throw new RuntimeException(
                'Subscription seat policy is unavailable.'
            );
        }

        $roleCode = strtolower(trim(
            (string)($input['role_code'] ?? '')
        ));

        if (!array_key_exists(
            $roleCode,
            subscription_seat_roles()
        )) {
            throw new InvalidArgumentException(
                'Unknown extra-seat role.'
            );
        }

        $quantityRaw = $input['quantity'] ?? null;

        if (is_string($quantityRaw)) {
            $quantityRaw = trim($quantityRaw);
        }

        $quantity = filter_var(
            $quantityRaw,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 500,
                ],
            ]
        );

        if ($quantity === false) {
            throw new InvalidArgumentException(
                'Extra-seat quantity must be a whole number between 1 and 500.'
            );
        }

        $provider = trim(
            (string)($input['provider'] ?? '')
        );

        if ($provider !== '') {
            if (!function_exists(
                'billing_provider_selection_normalize'
            )) {
                throw new RuntimeException(
                    'Billing provider selection is unavailable.'
                );
            }

            $provider =
                billing_provider_selection_normalize(
                    $provider
                );
        } else {
            $provider = null;
        }

        return [
            'role_code' => $roleCode,
            'quantity' => (int)$quantity,
            'provider' => $provider,
        ];
    }
}

if (!function_exists('billing_route_provider_reference')) {
    function billing_route_provider_reference(int $farmId, string $provider): string
    {
        if ($farmId < 1) throw new InvalidArgumentException('A tenant farm is required for billing reference generation.');
        $provider = strtolower(trim($provider));
        if (!preg_match('/^[a-z0-9][a-z0-9_.-]{0,39}$/', $provider)) {
            throw new InvalidArgumentException('A valid billing provider is required.');
        }
        return 'rf-' . $farmId . '-' . $provider . '-' . bin2hex(random_bytes(16));
    }
}

if (!function_exists('billing_route_request_headers')) {
    function billing_route_request_headers(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (!is_string($value) && !is_numeric($value)) continue;
            if (str_starts_with($name, 'HTTP_')) {
                $header = strtolower(str_replace('_', '-', substr($name, 5)));
                $headers[$header] = trim((string)$value);
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $header = strtolower(str_replace('_', '-', $name));
                $headers[$header] = trim((string)$value);
            }
        }
        return $headers;
    }
}

if (!function_exists('billing_route_raw_body')) {
    function billing_route_raw_body(int $maxBytes = 1048576): string
    {
        $length = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : null;
        if ($length !== null && $length > $maxBytes) {
            throw new RuntimeException('Billing webhook payload is too large.');
        }
        $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
        if ($raw === false) throw new RuntimeException('Unable to read billing webhook payload.');
        if (strlen($raw) > $maxBytes) throw new RuntimeException('Billing webhook payload is too large.');
        return $raw;
    }
}
