<?php
/**
 * V2.3 launch commercial price book — Nigeria / NGN.
 *
 * Commercial policy:
 * - Poultry and Ruminant are the only separately priced operational modules;
 * - shared basic Sales is included and is never a separate module price;
 * - annual billing is ten times the monthly amount (two months free);
 * - extra-seat prices use the same ten-times annual rule;
 * - changing any live amount requires a new price-book version.
 */

if (!function_exists('billing_price_book_ngn')) {
    function billing_price_book_ngn(): array
    {
        $seatPrices = [
            'poultry_manager' => [
                'monthly' => '2000.00',
                'annual' => '20000.00',
            ],
            'ruminant_manager' => [
                'monthly' => '2000.00',
                'annual' => '20000.00',
            ],
            'sales_rep' => [
                'monthly' => '1500.00',
                'annual' => '15000.00',
            ],
            'viewer' => [
                'monthly' => '1000.00',
                'annual' => '10000.00',
            ],
        ];

        return [
            'version' => 'ngn-launch-v1',
            'currency' => 'NGN',
            'packages' => [
                'starter' => [
                    'poultry' => [
                        'monthly' => '10000.00',
                        'annual' => '100000.00',
                    ],
                    'ruminant' => [
                        'monthly' => '10000.00',
                        'annual' => '100000.00',
                    ],
                    'poultry+ruminant' => [
                        'monthly' => '15000.00',
                        'annual' => '150000.00',
                    ],
                ],
                'growth' => [
                    'poultry' => [
                        'monthly' => '20000.00',
                        'annual' => '200000.00',
                    ],
                    'ruminant' => [
                        'monthly' => '20000.00',
                        'annual' => '200000.00',
                    ],
                    'poultry+ruminant' => [
                        'monthly' => '30000.00',
                        'annual' => '300000.00',
                    ],
                ],
                'pro' => [
                    'poultry' => [
                        'monthly' => '35000.00',
                        'annual' => '350000.00',
                    ],
                    'ruminant' => [
                        'monthly' => '35000.00',
                        'annual' => '350000.00',
                    ],
                    'poultry+ruminant' => [
                        'monthly' => '50000.00',
                        'annual' => '500000.00',
                    ],
                ],
            ],
            'seat_unit_prices' => [
                'starter' => $seatPrices,
                'growth' => $seatPrices,
                'pro' => $seatPrices,
            ],
        ];
    }
}
