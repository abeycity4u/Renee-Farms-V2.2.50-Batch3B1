<?php
/**
 * Renee Farms V3.0.1 — human-facing record reference foundation.
 *
 * Public references are immutable display/audit identities only.
 *
 * They never replace:
 * - database primary keys;
 * - foreign keys;
 * - tenant ownership;
 * - authorization;
 * - source/reversal identity;
 * - economic/provenance identity.
 *
 * Initial supported business records:
 * - stock movement;
 * - expense;
 * - sale.
 */

if (!function_exists('record_reference_entities')) {
    function record_reference_entities(): array
    {
        return [
            'stock_movement' => [
                'code' => 'SM',
                'label' => 'Stock movement',
            ],

            'expense' => [
                'code' => 'EX',
                'label' => 'Expense',
            ],

            'sale' => [
                'code' => 'SA',
                'label' => 'Sale',
            ],
        ];
    }
}

if (!function_exists('record_reference_entity_config')) {
    function record_reference_entity_config(
        string $entity
    ): array {
        $entity =
            strtolower(
                trim($entity)
            );

        $entities =
            record_reference_entities();

        if (!isset($entities[$entity])) {
            throw new InvalidArgumentException(
                'Unsupported record reference entity.'
            );
        }

        return [
            'entity' => $entity,
            'code' =>
                (string)$entities[$entity]['code'],
            'label' =>
                (string)$entities[$entity]['label'],
        ];
    }
}

if (!function_exists('record_reference_date_token')) {
    function record_reference_date_token(
        ?string $createdAt = null
    ): string {
        $createdAt =
            trim((string)$createdAt);

        if ($createdAt === '') {
            return date('Ymd');
        }

        try {
            $date =
                new DateTimeImmutable(
                    $createdAt
                );
        } catch (Throwable $error) {
            throw new InvalidArgumentException(
                'Record reference creation date is invalid.',
                0,
                $error
            );
        }

        return $date->format('Ymd');
    }
}

if (!function_exists('record_reference_random_suffix')) {
    function record_reference_random_suffix(
        int $length = 10
    ): string {
        /*
         * 32-character alphabet.
         *
         * 0/O and 1/I are deliberately excluded to improve
         * readability when references are copied verbally or manually.
         */
        $alphabet =
            '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

        if (
            $length < 8
            || $length > 32
        ) {
            throw new InvalidArgumentException(
                'Record reference suffix length is invalid.'
            );
        }

        $maximum =
            strlen($alphabet) - 1;

        $suffix = '';

        for (
            $index = 0;
            $index < $length;
            $index++
        ) {
            $suffix .=
                $alphabet[
                    random_int(
                        0,
                        $maximum
                    )
                ];
        }

        return $suffix;
    }
}

if (!function_exists('record_reference_generate')) {
    function record_reference_generate(
        string $entity,
        ?string $createdAt = null
    ): string {
        $config =
            record_reference_entity_config(
                $entity
            );

        return
            'RA-'
            . $config['code']
            . '-'
            . record_reference_date_token(
                $createdAt
            )
            . '-'
            . record_reference_random_suffix();
    }
}

if (!function_exists('record_reference_is_valid')) {
    function record_reference_is_valid(
        string $reference,
        ?string $entity = null
    ): bool {
        $reference =
            trim($reference);

        if (
            preg_match(
                '/^RA-(SM|EX|SA)-([0-9]{8})-([23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{10})$/',
                $reference,
                $matches
            ) !== 1
        ) {
            return false;
        }

        $date =
            DateTimeImmutable::createFromFormat(
                '!Ymd',
                (string)$matches[2]
            );

        if (
            !$date
            || $date->format('Ymd')
                !== (string)$matches[2]
        ) {
            return false;
        }

        if ($entity !== null) {
            try {
                $config =
                    record_reference_entity_config(
                        $entity
                    );
            } catch (InvalidArgumentException $error) {
                return false;
            }

            if (
                (string)$matches[1]
                !== (string)$config['code']
            ) {
                return false;
            }
        }

        return true;
    }
}
