<?php
/**
 * V3.2 trial onboarding request lifecycle authority.
 *
 * Owns request-state vocabulary and transition policy only.
 *
 * Deliberately does NOT:
 * - create farms;
 * - create users;
 * - issue credential tokens;
 * - send activation email;
 * - start the 14-day tenant trial;
 * - change tenant subscription state;
 * - apply billing.
 */

if (!function_exists('trial_onboarding_request_statuses')) {
    function trial_onboarding_request_statuses(): array
    {
        return [
            'pending_review',
            'approved',
            'provisioning',
            'provisioned',
            'activated',
            'rejected',
            'cancelled',
        ];
    }
}

if (!function_exists('trial_onboarding_request_approval_modes')) {
    function trial_onboarding_request_approval_modes(): array
    {
        return [
            'auto',
            'manual',
        ];
    }
}

if (!function_exists('trial_onboarding_request_normalize_status')) {
    function trial_onboarding_request_normalize_status(
        string $status
    ): string {
        $status = strtolower(trim($status));

        if (!in_array(
            $status,
            trial_onboarding_request_statuses(),
            true
        )) {
            throw new InvalidArgumentException(
                'Unsupported trial onboarding request status.'
            );
        }

        return $status;
    }
}

if (!function_exists('trial_onboarding_request_normalize_approval_mode')) {
    function trial_onboarding_request_normalize_approval_mode(
        string $mode
    ): string {
        $mode = strtolower(trim($mode));

        if (!in_array(
            $mode,
            trial_onboarding_request_approval_modes(),
            true
        )) {
            throw new InvalidArgumentException(
                'Unsupported trial onboarding approval mode.'
            );
        }

        return $mode;
    }
}

if (!function_exists('trial_onboarding_request_transition_map')) {
    function trial_onboarding_request_transition_map(): array
    {
        return [
            'pending_review' => [
                'approved',
                'rejected',
                'cancelled',
            ],

            'approved' => [
                'provisioning',
                'cancelled',
            ],

            'provisioning' => [
                'provisioned',
            ],

            'provisioned' => [
                'activated',
            ],

            'activated' => [],
            'rejected' => [],
            'cancelled' => [],
        ];
    }
}

if (!function_exists('trial_onboarding_request_can_transition')) {
    function trial_onboarding_request_can_transition(
        string $from,
        string $to
    ): bool {
        $from =
            trial_onboarding_request_normalize_status($from);

        $to =
            trial_onboarding_request_normalize_status($to);

        if ($from === $to) {
            return true;
        }

        $map =
            trial_onboarding_request_transition_map();

        return in_array(
            $to,
            $map[$from] ?? [],
            true
        );
    }
}

if (!function_exists('trial_onboarding_request_assert_transition')) {
    function trial_onboarding_request_assert_transition(
        string $from,
        string $to
    ): void {
        if (!trial_onboarding_request_can_transition(
            $from,
            $to
        )) {
            throw new RuntimeException(
                'Invalid trial onboarding request transition.'
            );
        }
    }
}

if (!function_exists('trial_onboarding_request_terminal_statuses')) {
    function trial_onboarding_request_terminal_statuses(): array
    {
        return [
            'activated',
            'rejected',
            'cancelled',
        ];
    }
}

if (!function_exists('trial_onboarding_request_is_terminal')) {
    function trial_onboarding_request_is_terminal(
        string $status
    ): bool {
        $status =
            trial_onboarding_request_normalize_status(
                $status
            );

        return in_array(
            $status,
            trial_onboarding_request_terminal_statuses(),
            true
        );
    }
}

if (!function_exists('trial_onboarding_request_is_provisionable')) {
    function trial_onboarding_request_is_provisionable(
        string $status
    ): bool {
        return trial_onboarding_request_normalize_status(
            $status
        ) === 'approved';
    }
}

if (!function_exists('trial_onboarding_request_reference')) {
    function trial_onboarding_request_reference(): string
    {
        return bin2hex(random_bytes(16));
    }
}

if (!function_exists('trial_onboarding_trial_days')) {
    function trial_onboarding_trial_days(): int
    {
        return 14;
    }
}
