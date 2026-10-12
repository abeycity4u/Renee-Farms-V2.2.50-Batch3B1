<?php
declare(strict_types=1);

/**
 * B12 early-bootstrap response seam. SOURCE ONLY; NOT IMPORTED by live code.
 *
 * This does not authenticate a user, choose an access decision, query accounts,
 * update subscriptions, mutate sessions, issue headers, or terminate a request.
 * An existing CANONICAL guard must have decided the denial. The future refactor
 * must extract that guard's original side effects into a completion callback.
 * Only after that completion may a separately supplied emitter format a result.
 *
 * At present canonical guards call header()/exit() themselves. The callback
 * model here is a testable sequencing contract, NOT a drop-in replacement.
 */
require_once __DIR__ . '/b12_auth_response_policy.php';

if (!function_exists('b12_auth_early_bootstrap_event_site')) {
    function b12_auth_early_bootstrap_event_site(string $event): string
    {
        return match ($event) {
            'session_expired' => 'config.session_timeout',
            'unauthenticated', 'invalid_account' => 'config.require_login',
            'farm_invalid' => 'config.require_farm',
            'subscription_recovery', 'subscription_expired' =>
                'subscription_lifecycle.request_guard',
            'entitlement_denied' => 'farm_entitlement_runtime.module_guard',
            'permission_denied' => 'daily_record_api.permission_guard',
            default => throw new InvalidArgumentException('Unsupported canonical denial event.'),
        };
    }
}

if (!function_exists('b12_auth_early_bootstrap_denial')) {
    /**
     * @param callable():bool $completeCanonical A separately extracted canonical
     *        DENIAL's existing security/recovery side effects; must return true
     *        only after they have completed. Never infer a denial in this seam.
     * @param callable(array<string,mixed>):mixed $emitPlan Future existing JSON/
     *        HTML emitter; MUST terminate after issuing one response in runtime.
     * @return mixed For offline simulation only. No actual headers/exit here.
     */
    function b12_auth_early_bootstrap_denial(
        string $scriptPath,
        string $method,
        string $event,
        string $canonicalSite,
        callable $completeCanonical,
        callable $emitPlan
    ): mixed {
        if ($canonicalSite !== b12_auth_early_bootstrap_event_site($event)) {
            throw new LogicException('Canonical denial source does not match the event.');
        }
        // Validate transport before the canonical callback; failures must not
        // partially clear identity due to a malformed event/HTTP method.
        $plan = b12_auth_response_plan($scriptPath, $event, $method);

        // The app's existing guard owns session teardown, role refresh,
        // subscription persistence, and recovery seeding. Nothing is copied.
        if ($completeCanonical() !== true) {
            throw new LogicException('Canonical denial handling was not completed.');
        }
        return $emitPlan($plan);
    }
}
