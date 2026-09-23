<?php

require_once __DIR__
    . '/financial_allocation_workspace.php';

require_once __DIR__
    . '/stock_consumption_allocation_workspace.php';

/*
 * Shared Allocation Navigation
 *
 * Read/navigation adapter only.
 *
 * Allocation policy and mutation authority remain in the existing
 * canonical workspace/service stacks.
 */

if (!function_exists(
    'shared_allocation_navigation_action'
)) {
function shared_allocation_navigation_action(
    PDO $pdo,
    int $farmId,
    array $source,
    string $permissionScope = 'operational'
): ?array {
    if ($farmId < 1) {
        throw new InvalidArgumentException(
            'Shared-allocation navigation requires a valid farm.'
        );
    }

    $sourceId =
        (int)(
            $source['source_id']
            ?? 0
        );

    if ($sourceId < 1) {
        return null;
    }

    $sourceType =
        strtolower(
            trim(
                (string)(
                    $source['source_type']
                    ?? ''
                )
            )
        );

    $label =
        trim(
            (string)(
                $source['source_label']
                ?? ''
            )
        );

    $date =
        substr(
            trim(
                (string)(
                    $source['source_date']
                    ?? ''
                )
            ),
            0,
            10
        );

    try {
        if (
            in_array(
                $sourceType,
                [
                    'expense',
                    'allocated_expense',
                ],
                true
            )
        ) {
            $parent =
                financial_allocation_service_parent(
                    $pdo,
                    $farmId,
                    $sourceId,
                    false
                );

            if (
                !financial_allocation_workspace_parent_is_eligible(
                    $parent
                )
                ||
                !financial_allocation_workspace_can_access(
                    $parent,
                    $permissionScope
                )
            ) {
                return null;
            }

            return [
                'kind' =>
                    'expense',

                'source_id' =>
                    $sourceId,

                'source_type' =>
                    $sourceType,

                'source_label' =>
                    $label !== ''
                        ? $label
                        : 'Expense #' . $sourceId,

                'source_date' =>
                    $date,

                'url' =>
                    financial_allocation_workspace_url(
                        $sourceId,
                        $permissionScope
                    ),

                'action_label' =>
                    'Open exact shared-cost workspace',
            ];
        }

        if ($sourceType === 'inventory_use') {
            $movement =
                stock_consumption_allocation_persistence_movement(
                    $pdo,
                    $farmId,
                    $sourceId,
                    false
                );

            $state =
                stock_consumption_allocation_workspace_action_state(
                    $pdo,
                    $farmId,
                    $movement,
                    stock_consumption_allocation_workspace_can_manage()
                );

            if (
                empty($state['visible'])
                ||
                empty($state['eligible'])
                ||
                empty($state['url'])
            ) {
                return null;
            }

            return [
                'kind' =>
                    'stock',

                'source_id' =>
                    $sourceId,

                'source_type' =>
                    $sourceType,

                'source_label' =>
                    $label !== ''
                        ? $label
                        : 'Consumed stock #' . $sourceId,

                'source_date' =>
                    $date,

                'url' =>
                    (string)$state['url'],

                'action_label' =>
                    'Open exact consumed-stock workspace',
            ];
        }

        return null;

    } catch (PDOException $error) {
        throw $error;

    } catch (Throwable $error) {
        /*
         * A source can remain visible while mutation is unavailable
         * because of compatibility or permission policy.
         */
        return null;
    }
}
}

if (!function_exists(
    'shared_allocation_navigation_actions'
)) {
function shared_allocation_navigation_actions(
    PDO $pdo,
    int $farmId,
    array $sources,
    string $permissionScope = 'operational'
): array {
    $actions = [];

    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }

        $action =
            shared_allocation_navigation_action(
                $pdo,
                $farmId,
                $source,
                $permissionScope
            );

        if ($action === null) {
            continue;
        }

        /*
         * One parent source gets one workspace link even when the
         * analytical read model emits multiple rows for it.
         */
        $key =
            (string)$action['kind']
            . ':'
            . (int)$action['source_id'];

        if (!isset($actions[$key])) {
            $actions[$key] =
                $action;
        }
    }

    return
        array_values(
            $actions
        );
}
}
