<?php

/**
 * V3.0.1 Production-Entry provenance foundation.
 *
 * Pure normalization/hashing only:
 * - no database access;
 * - no source-selection policy;
 * - no snapshot persistence;
 * - no approval policy.
 */

if (!function_exists('poultry_production_entry_provenance_valid_date')) {
    function poultry_production_entry_provenance_valid_date(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable
            && $date->format('Y-m-d') === $value;
    }
}

if (!function_exists('poultry_production_entry_provenance_canonicalize')) {
    function poultry_production_entry_provenance_canonicalize($value)
    {
        if (!is_array($value)) {
            if (
                $value === null
                || is_string($value)
                || is_int($value)
                || is_float($value)
                || is_bool($value)
            ) {
                return $value;
            }

            throw new InvalidArgumentException(
                'Production-Entry provenance facts must contain only scalar values, nulls, or arrays.'
            );
        }

        if ($value === []) {
            return [];
        }

        $keys = array_keys($value);
        $isList = ($keys === range(0, count($value) - 1));

        if ($isList) {
            $normalized = [];

            foreach ($value as $item) {
                $normalized[] =
                    poultry_production_entry_provenance_canonicalize(
                        $item
                    );
            }

            return $normalized;
        }

        $normalized = [];
        $stringKeys = [];

        foreach ($keys as $key) {
            $stringKeys[] = (string)$key;
        }

        sort($stringKeys, SORT_STRING);

        foreach ($stringKeys as $key) {
            $normalized[$key] =
                poultry_production_entry_provenance_canonicalize(
                    $value[$key]
                );
        }

        return $normalized;
    }
}

if (!function_exists('poultry_production_entry_provenance_revision')) {
    function poultry_production_entry_provenance_revision(
        array $facts
    ): string {
        $normalized =
            poultry_production_entry_provenance_canonicalize(
                $facts
            );

        $json = json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($json === false) {
            throw new RuntimeException(
                'Production-Entry provenance revision facts could not be encoded.'
            );
        }

        return hash('sha256', $json);
    }
}

if (!function_exists('poultry_production_entry_provenance_context')) {
    function poultry_production_entry_provenance_context(
        array $context
    ): array {
        $farmId = isset($context['farm_id'])
            ? (int)$context['farm_id']
            : 0;

        $cycleId = isset($context['cycle_id'])
            ? (int)$context['cycle_id']
            : 0;

        $mode =
            strtolower(
                trim((string)($context['mode'] ?? ''))
            );

        if ($farmId <= 0) {
            throw new InvalidArgumentException(
                'Production-Entry provenance requires a valid farm identity.'
            );
        }

        if ($cycleId <= 0) {
            throw new InvalidArgumentException(
                'Production-Entry provenance requires a valid cycle identity.'
            );
        }

        if ($mode === '') {
            throw new InvalidArgumentException(
                'Production-Entry provenance requires an economics mode.'
            );
        }

        $dateKeys = [
            'production_entry_date',
            'rearing_start_date',
            'rearing_end_date',
        ];

        $normalizedDates = [];

        foreach ($dateKeys as $key) {
            $value =
                trim((string)($context[$key] ?? ''));

            if (
                $value !== ''
                && !poultry_production_entry_provenance_valid_date(
                    $value
                )
            ) {
                throw new InvalidArgumentException(
                    'Production-Entry provenance contains an invalid date.'
                );
            }

            $normalizedDates[$key] =
                $value !== '' ? $value : null;
        }

        return [
            'farm_id' => $farmId,
            'cycle_id' => $cycleId,
            'mode' => $mode,
            'production_entry_date' =>
                $normalizedDates['production_entry_date'],
            'rearing_start_date' =>
                $normalizedDates['rearing_start_date'],
            'rearing_end_date' =>
                $normalizedDates['rearing_end_date'],
        ];
    }
}

if (!function_exists('poultry_production_entry_provenance_source')) {
    function poultry_production_entry_provenance_source(
        array $source
    ): array {
        $role =
            strtolower(
                trim((string)($source['role'] ?? ''))
            );

        $sourceType =
            strtolower(
                trim(
                    (string)($source['source_type'] ?? '')
                )
            );

        $sourceId =
            trim(
                (string)($source['source_id'] ?? '')
            );

        if ($role === '') {
            throw new InvalidArgumentException(
                'Production-Entry provenance source role is required.'
            );
        }

        if ($sourceType === '') {
            throw new InvalidArgumentException(
                'Production-Entry provenance source type is required.'
            );
        }

        if ($sourceId === '') {
            throw new InvalidArgumentException(
                'Production-Entry provenance source identity is required.'
            );
        }

        $sourceVersion = null;

        if (
            array_key_exists('source_version', $source)
            && $source['source_version'] !== null
            && trim((string)$source['source_version']) !== ''
        ) {
            $sourceVersion =
                trim((string)$source['source_version']);
        }

        $sourceRevision = null;

        if (
            array_key_exists('source_revision', $source)
            && $source['source_revision'] !== null
            && trim((string)$source['source_revision']) !== ''
        ) {
            $sourceRevision =
                strtolower(
                    trim(
                        (string)$source['source_revision']
                    )
                );

            if (
                preg_match(
                    '/^[a-f0-9]{64}$/',
                    $sourceRevision
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Production-Entry provenance source revision must be a SHA-256 digest.'
                );
            }
        }

        $effectiveDate =
            trim(
                (string)($source['effective_date'] ?? '')
            );

        if (
            $effectiveDate !== ''
            && !poultry_production_entry_provenance_valid_date(
                $effectiveDate
            )
        ) {
            throw new InvalidArgumentException(
                'Production-Entry provenance source contains an invalid effective date.'
            );
        }

        return [
            'role' => $role,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_version' => $sourceVersion,
            'source_revision' => $sourceRevision,
            'effective_date' =>
                $effectiveDate !== ''
                    ? $effectiveDate
                    : null,
        ];
    }
}

if (!function_exists('poultry_production_entry_provenance_source_key')) {
    function poultry_production_entry_provenance_source_key(
        array $source
    ): string {
        return implode("\x1F", [
            (string)$source['role'],
            (string)$source['source_type'],
            (string)$source['source_id'],
            $source['source_version'] === null
                ? ''
                : (string)$source['source_version'],
            $source['source_revision'] === null
                ? ''
                : (string)$source['source_revision'],
            $source['effective_date'] === null
                ? ''
                : (string)$source['effective_date'],
        ]);
    }
}

if (!function_exists('poultry_production_entry_provenance_manifest')) {
    function poultry_production_entry_provenance_manifest(
        array $context,
        array $sources
    ): array {
        $normalizedContext =
            poultry_production_entry_provenance_context(
                $context
            );

        $unique = [];

        foreach ($sources as $source) {
            if (!is_array($source)) {
                throw new InvalidArgumentException(
                    'Production-Entry provenance sources must be structured records.'
                );
            }

            $normalized =
                poultry_production_entry_provenance_source(
                    $source
                );

            $key =
                poultry_production_entry_provenance_source_key(
                    $normalized
                );

            $unique[$key] = $normalized;
        }

        ksort($unique, SORT_STRING);

        return [
            'schema' =>
                'poultry_production_entry_provenance',
            'schema_version' => 1,
            'context' => $normalizedContext,
            'sources' => array_values($unique),
        ];
    }
}

if (!function_exists('poultry_production_entry_provenance_fingerprint')) {
    function poultry_production_entry_provenance_fingerprint(
        array $manifest
    ): string {
        if (
            ($manifest['schema'] ?? '')
                !== 'poultry_production_entry_provenance'
            || (int)($manifest['schema_version'] ?? 0) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid Production-Entry provenance manifest.'
            );
        }

        $normalized =
            poultry_production_entry_provenance_manifest(
                isset($manifest['context'])
                    && is_array($manifest['context'])
                        ? $manifest['context']
                        : [],
                isset($manifest['sources'])
                    && is_array($manifest['sources'])
                        ? $manifest['sources']
                        : []
            );

        $json = json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($json === false) {
            throw new RuntimeException(
                'Production-Entry provenance manifest could not be encoded.'
            );
        }

        return hash('sha256', $json);
    }
}

if (!function_exists('poultry_production_entry_provenance_build')) {
    function poultry_production_entry_provenance_build(
        array $context,
        array $sources
    ): array {
        $manifest =
            poultry_production_entry_provenance_manifest(
                $context,
                $sources
            );

        return [
            'manifest' => $manifest,
            'fingerprint' =>
                poultry_production_entry_provenance_fingerprint(
                    $manifest
                ),
        ];
    }
}
