<?php
/**
 * Canonical farm contact-email service.
 *
 * Contract:
 * - farms.contact_email is the tenant's billing/contact email source;
 * - Farm Admin may update only the current tenant through a tenant-pinned call;
 * - if the current Farm Admin user has no user email yet, the same address is
 *   backfilled there for profile continuity without overwriting a different one;
 * - Platform Owner create/edit may use the pair helper so one supplied address
 *   can safely fill the other field while still allowing distinct addresses.
 */

if (!function_exists('farm_contact_email_normalize')) {
    function farm_contact_email_normalize($value, bool $allowEmpty = false): string
    {
        $email = strtolower(trim((string)$value));
        if ($email === '' && $allowEmpty) return '';
        if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid farm contact email address.');
        }
        return $email;
    }
}

if (!function_exists('farm_contact_email_pair')) {
    function farm_contact_email_pair($ownerEmail, $contactEmail): array
    {
        $owner = farm_contact_email_normalize($ownerEmail, true);
        $contact = farm_contact_email_normalize($contactEmail, true);

        if ($contact === '' && $owner !== '') $contact = $owner;
        if ($owner === '' && $contact !== '') $owner = $contact;

        return [
            'owner_email' => $owner,
            'contact_email' => $contact,
        ];
    }
}

if (!function_exists('farm_contact_email_update')) {
    function farm_contact_email_update(
        PDO $pdo,
        int $farmId,
        int $actorUserId,
        string $email
    ): array {
        if ($farmId < 1 || $actorUserId < 1) {
            throw new InvalidArgumentException('A valid Farm Admin tenant is required.');
        }

        $email = farm_contact_email_normalize($email);
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) $pdo->beginTransaction();

        try {
            $farmStmt = $pdo->prepare(
                "SELECT id, contact_email
                 FROM farms
                 WHERE id = ? AND slug <> 'owner'
                 LIMIT 1
                 FOR UPDATE"
            );
            $farmStmt->execute([$farmId]);
            $farm = $farmStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$farm) {
                throw new RuntimeException('Tenant farm could not be found for contact email update.');
            }

            $userStmt = $pdo->prepare(
                "SELECT id, email
                 FROM users
                 WHERE id = ? AND farm_id = ?
                 LIMIT 1"
            );
            $userStmt->execute([$actorUserId, $farmId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$user) {
                throw new RuntimeException('Farm Admin account could not be found for contact email update.');
            }

            $pdo->prepare(
                "UPDATE farms
                 SET contact_email = ?
                 WHERE id = ? AND slug <> 'owner'"
            )->execute([$email, $farmId]);

            $currentUserEmail = trim((string)($user['email'] ?? ''));
            if ($currentUserEmail === '' || filter_var($currentUserEmail, FILTER_VALIDATE_EMAIL) === false) {
                $pdo->prepare(
                    'UPDATE users SET email = ? WHERE id = ? AND farm_id = ?'
                )->execute([$email, $actorUserId, $farmId]);
            }

            if ($startedTransaction) $pdo->commit();

            return [
                'farm_id' => $farmId,
                'user_id' => $actorUserId,
                'contact_email' => $email,
                'changed' => !hash_equals(strtolower(trim((string)($farm['contact_email'] ?? ''))), $email),
            ];
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
