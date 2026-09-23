<?php

/**
 * Canonical Ruminant animal farm-entry contract.
 *
 * farm_entry_date:
 *   Date this physical animal became part of the farm/operation.
 *
 * It is deliberately independent from:
 * - tag/registry creation time;
 * - purchase date;
 * - birth date;
 * - production-cycle transfer dates.
 *
 * A livestock animal may physically enter a herd before it is individually
 * tagged in Renee AgriSuite.
 */

if (!class_exists('RuminantAnimalEntryException')) {
    class RuminantAnimalEntryException extends RuntimeException
    {
    }
}

if (!function_exists(
    'ruminant_animal_entry_valid_date'
)) {
function ruminant_animal_entry_valid_date(
    string $date
): bool {
    $parsed =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

    return
        $parsed instanceof DateTimeImmutable
        &&
        $parsed->format('Y-m-d') === $date;
}
}

if (!function_exists(
    'ruminant_animal_farm_entry_date_from_row'
)) {
function ruminant_animal_farm_entry_date_from_row(
    array $animal
): ?string {
    /*
     * farm_entry_date is authoritative after migration 075.
     *
     * Older fields remain transitional fallbacks so source code can retain
     * historical compatibility while environments complete the migration.
     */
    foreach (
        [
            'farm_entry_date',
            'purchase_date',
            'birth_date',
        ]
        as $field
    ) {
        $value =
            trim(
                (string)(
                    $animal[$field]
                    ?? ''
                )
            );

        if (
            $value !== ''
            &&
            ruminant_animal_entry_valid_date(
                $value
            )
        ) {
            return $value;
        }
    }

    $createdAt =
        trim(
            (string)(
                $animal['created_at']
                ?? ''
            )
        );

    if (
        strlen($createdAt) >= 10
    ) {
        $createdDate =
            substr(
                $createdAt,
                0,
                10
            );

        if (
            ruminant_animal_entry_valid_date(
                $createdDate
            )
        ) {
            return $createdDate;
        }
    }

    return null;
}
}

if (!function_exists(
    'ruminant_animal_assert_farm_entry_date'
)) {
function ruminant_animal_assert_farm_entry_date(
    string $farmEntryDate,
    ?string $birthDate,
    string $today
): string {
    $farmEntryDate =
        trim(
            $farmEntryDate
        );

    if (
        !ruminant_animal_entry_valid_date(
            $farmEntryDate
        )
    ) {
        throw new RuminantAnimalEntryException(
            'Enter the date this animal physically entered the farm/operation.'
        );
    }

    if (
        !ruminant_animal_entry_valid_date(
            $today
        )
    ) {
        throw new InvalidArgumentException(
            'Current farm date is invalid.'
        );
    }

    if ($farmEntryDate > $today) {
        throw new RuminantAnimalEntryException(
            'Farm entry date cannot be in the future.'
        );
    }

    $birthDate =
        trim(
            (string)$birthDate
        );

    if ($birthDate !== '') {
        if (
            !ruminant_animal_entry_valid_date(
                $birthDate
            )
        ) {
            throw new RuminantAnimalEntryException(
                'Enter a valid animal birth date.'
            );
        }

        if ($farmEntryDate < $birthDate) {
            throw new RuminantAnimalEntryException(
                'Farm entry date cannot be earlier than the animal birth date.'
            );
        }
    }

    return $farmEntryDate;
}
}
