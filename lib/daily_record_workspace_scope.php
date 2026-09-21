<?php

declare(strict_types=1);

/**
 * Shared Daily Record workspace scope labels/read-model helpers.
 *
 * Scope contract:
 * - Current Stock is a live population view.
 * - With no specific cycle selected, Current Stock aggregates active cycles.
 * - Activity summary cards remain scoped to the displayed month.
 * - "All" record views may include active-cycle and legacy/historical rows.
 */

if (!function_exists('daily_record_workspace_scope')) {
    function daily_record_workspace_scope(
        bool $cycleEnabled,
        int $selectedCycleId
    ): array {
        $allRecordsMode =
            $cycleEnabled
            && $selectedCycleId <= 0;

        return [
            'all_records_mode' => $allRecordsMode,
            'selector_all_label' =>
                'All Active Cycles / Legacy Records',
            'current_stock_label' =>
                $allRecordsMode
                    ? 'All Active Cycles'
                    : ($cycleEnabled
                        ? 'Current Cycle'
                        : 'Latest Closing'),
            'activity_label' => 'This Month',
        ];
    }
}

if (!function_exists('daily_record_workspace_distinct_days')) {
    function daily_record_workspace_distinct_days(
        array $records
    ): int {
        $dates = [];

        foreach ($records as $record) {
            $recordDate = trim(
                (string)($record['record_date'] ?? '')
            );

            if ($recordDate === '') {
                continue;
            }

            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                substr($recordDate, 0, 10)
            );

            if (!$date) {
                continue;
            }

            $normalized = $date->format('Y-m-d');

            if ($normalized !== substr($recordDate, 0, 10)) {
                continue;
            }

            $dates[$normalized] = true;
        }

        return count($dates);
    }
}
