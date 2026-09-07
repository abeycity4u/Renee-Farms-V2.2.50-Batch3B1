<?php
/**
 * V2.3 Billing Stage 2A server-authoritative pricing contract.
 *
 * Product contract:
 * - subscription_plan_catalog.php owns included seat/capacity policy, not money;
 * - Poultry/Ruminant/both is a separate purchased module bundle;
 * - shared basic Sales is never a pricing dimension;
 * - checkout callers never supply amount or currency;
 * - a versioned server-side price book resolves the exact amount/currency;
 * - Stage 2C launch-currency policy is enforced when loaded;
 * - until a deliberate commercial price book is configured, pricing fails closed.
 *
 * No prices or payment-provider choice are introduced in this file.
 */

if (!function_exists('billing_pricing_price_book')) {
    function billing_pricing_price_book(): array
    {
        // Intentionally unconfigured. Populate only after the commercial pricing
        // decision is approved. Changing live prices must also change version.
        return [
            'version' => '',
            'currency' => '',
            'packages' => [],
            'seat_unit_prices' => [],
        ];
    }
}

if (!function_exists('billing_pricing_normalize_interval')) {
    function billing_pricing_normalize_interval(string $interval): string
    {
        if (function_exists('billing_payment_normalize_interval')) {
            return billing_payment_normalize_interval($interval);
        }
        $interval = strtolower(trim($interval));
        if (!in_array($interval, ['monthly', 'annual'], true)) {
            throw new InvalidArgumentException('Billing interval must be monthly or annual.');
        }
        return $interval;
    }
}

if (!function_exists('billing_pricing_normalize_modules')) {
    function billing_pricing_normalize_modules(array $modules): array
    {
        if (function_exists('billing_payment_normalize_modules')) {
            return billing_payment_normalize_modules($modules);
        }
        $normalized = [];
        foreach ($modules as $module) {
            $module = strtolower(trim((string)$module));
            if (in_array($module, ['poultry', 'ruminant'], true)) $normalized[$module] = true;
        }
        $normalized = array_keys($normalized);
        sort($normalized, SORT_STRING);
        if (!$normalized) {
            throw new InvalidArgumentException('A billing quote requires Poultry, Ruminant, or both.');
        }
        return $normalized;
    }
}

if (!function_exists('billing_pricing_bundle_key')) {
    function billing_pricing_bundle_key(array $modules): string
    {
        $modules = billing_pricing_normalize_modules($modules);
        if ($modules === ['poultry']) return 'poultry';
        if ($modules === ['ruminant']) return 'ruminant';
        if ($modules === ['poultry', 'ruminant']) return 'poultry+ruminant';
        throw new InvalidArgumentException('Unsupported commercial module bundle.');
    }
}

if (!function_exists('billing_pricing_normalize_seat_addons')) {
    function billing_pricing_normalize_seat_addons(array $seatAddOns): array
    {
        if (function_exists('subscription_seat_normalize_addons')) {
            $seatAddOns = subscription_seat_normalize_addons($seatAddOns);
        } else {
            $normalized = [];
            foreach (['poultry_manager', 'ruminant_manager', 'sales_rep', 'viewer'] as $role) {
                $value = filter_var(
                    $seatAddOns[$role] ?? 0,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 0, 'max_range' => 500]]
                );
                if ($value === false) throw new InvalidArgumentException('Seat add-ons must be non-negative integers.');
                $normalized[$role] = (int)$value;
            }
            $seatAddOns = $normalized;
        }
        ksort($seatAddOns, SORT_STRING);
        return $seatAddOns;
    }
}

if (!function_exists('billing_pricing_decimal_to_minor')) {
    function billing_pricing_decimal_to_minor($amount, bool $allowZero = false): int
    {
        if (is_int($amount)) $amount = (string)$amount;
        if (!is_string($amount)) {
            throw new InvalidArgumentException('Price amounts must be supplied as exact decimal strings.');
        }
        $amount = trim($amount);
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $match)) {
            throw new InvalidArgumentException('Price amount is not a valid DECIMAL(12,2) value.');
        }
        $whole = (int)$match[1];
        $fraction = str_pad((string)($match[2] ?? ''), 2, '0', STR_PAD_RIGHT);
        $minor = ($whole * 100) + (int)$fraction;
        if (!$allowZero && $minor < 1) {
            throw new InvalidArgumentException('Price amount must be greater than zero.');
        }
        return $minor;
    }
}

if (!function_exists('billing_pricing_minor_to_decimal')) {
    function billing_pricing_minor_to_decimal(int $minor): string
    {
        if ($minor < 0) throw new InvalidArgumentException('Price amount cannot be negative.');
        return intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('billing_pricing_canonicalize')) {
    function billing_pricing_canonicalize($value)
    {
        if (!is_array($value)) return $value;
        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            return array_map('billing_pricing_canonicalize', $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $child) {
            $value[$key] = billing_pricing_canonicalize($child);
        }
        return $value;
    }
}

if (!function_exists('billing_pricing_price_book_hash')) {
    function billing_pricing_price_book_hash(array $book): string
    {
        $canonical = billing_pricing_canonicalize($book);
        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) throw new RuntimeException('Unable to encode the billing price book.');
        return hash('sha256', $json);
    }
}

if (!function_exists('billing_pricing_validate_price_book')) {
    function billing_pricing_validate_price_book(array $book): array
    {
        $version = trim((string)($book['version'] ?? ''));
        $currency = strtoupper(trim((string)($book['currency'] ?? '')));
        $packages = $book['packages'] ?? null;
        $seatPrices = $book['seat_unit_prices'] ?? null;

        if ($version === '' || strlen($version) > 80 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $version)) {
            throw new RuntimeException('Billing pricing is not configured with a valid version.');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new RuntimeException('Billing pricing is not configured with a valid currency.');
        }
        if (function_exists('billing_currency_policy_normalize')) {
            $currency = billing_currency_policy_normalize($currency);
        }
        if (!is_array($packages) || !$packages) {
            throw new RuntimeException('Billing pricing is not configured with package prices.');
        }
        if (!is_array($seatPrices)) {
            throw new RuntimeException('Billing pricing seat-unit prices are invalid.');
        }

        return [
            'version' => $version,
            'currency' => $currency,
            'packages' => $packages,
            'seat_unit_prices' => $seatPrices,
            'price_book_hash' => billing_pricing_price_book_hash([
                'version' => $version,
                'currency' => $currency,
                'packages' => $packages,
                'seat_unit_prices' => $seatPrices,
            ]),
        ];
    }
}

if (!function_exists('billing_pricing_is_configured')) {
    function billing_pricing_is_configured(): bool
    {
        try {
            billing_pricing_validate_price_book(billing_pricing_price_book());
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('billing_pricing_resolve')) {
    function billing_pricing_resolve(
        string $planCode,
        string $billingInterval,
        array $modules,
        array $seatAddOns = []
    ): array {
        $book = billing_pricing_validate_price_book(billing_pricing_price_book());

        $planCode = strtolower(trim($planCode));
        if (!function_exists('subscription_plan_is_valid') || !subscription_plan_is_valid($planCode)) {
            throw new InvalidArgumentException('Unknown subscription plan.');
        }

        $billingInterval = billing_pricing_normalize_interval($billingInterval);
        $modules = billing_pricing_normalize_modules($modules);
        $bundleKey = billing_pricing_bundle_key($modules);
        $seatAddOns = billing_pricing_normalize_seat_addons($seatAddOns);

        $packageRaw = $book['packages'][$planCode][$bundleKey][$billingInterval] ?? null;
        if ($packageRaw === null) {
            throw new RuntimeException('No commercial package price is configured for the selected plan, module bundle and interval.');
        }
        $packageMinor = billing_pricing_decimal_to_minor($packageRaw);

        $seatMinor = 0;
        $seatComponents = [];
        foreach ($seatAddOns as $role => $quantity) {
            $quantity = (int)$quantity;
            if ($quantity < 1) continue;
            $unitRaw = $book['seat_unit_prices'][$planCode][$role][$billingInterval] ?? null;
            if ($unitRaw === null) {
                throw new RuntimeException('No extra-seat unit price is configured for role ' . $role . '.');
            }
            $unitMinor = billing_pricing_decimal_to_minor($unitRaw, true);
            $lineMinor = $unitMinor * $quantity;
            $seatMinor += $lineMinor;
            $seatComponents[$role] = [
                'quantity' => $quantity,
                'unit_amount' => billing_pricing_minor_to_decimal($unitMinor),
                'line_amount' => billing_pricing_minor_to_decimal($lineMinor),
            ];
        }

        $totalMinor = $packageMinor + $seatMinor;
        if ($totalMinor < 1) throw new RuntimeException('Resolved billing amount must be greater than zero.');

        return [
            'pricing_version' => $book['version'],
            'pricing_hash' => $book['price_book_hash'],
            'plan_code' => $planCode,
            'billing_interval' => $billingInterval,
            'modules' => $modules,
            'module_bundle' => $bundleKey,
            'seat_addons' => $seatAddOns,
            'currency' => $book['currency'],
            'package_amount' => billing_pricing_minor_to_decimal($packageMinor),
            'seat_addon_amount' => billing_pricing_minor_to_decimal($seatMinor),
            'amount' => billing_pricing_minor_to_decimal($totalMinor),
            'seat_components' => $seatComponents,
        ];
    }
}

if (!function_exists('billing_pricing_build_payment_quote')) {
    function billing_pricing_build_payment_quote(
        string $planCode,
        string $billingInterval,
        array $modules,
        array $seatAddOns = []
    ): array {
        if (!function_exists('billing_payment_build_quote')) {
            throw new RuntimeException('Billing payment foundation must be loaded before building a priced payment quote.');
        }

        // Amount/currency come only from the canonical server-side price book.
        $pricing = billing_pricing_resolve($planCode, $billingInterval, $modules, $seatAddOns);
        $paymentQuote = billing_payment_build_quote(
            $pricing['plan_code'],
            $pricing['billing_interval'],
            $pricing['amount'],
            $pricing['currency'],
            $pricing['modules'],
            $pricing['seat_addons']
        );

        return [
            'pricing' => $pricing,
            'payment_quote' => $paymentQuote,
        ];
    }
}
