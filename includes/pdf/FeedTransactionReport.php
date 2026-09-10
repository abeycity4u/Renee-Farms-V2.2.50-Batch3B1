<?php

/**
 * Central PDF renderer for Layer, Broiler and Ruminant feed histories.
 *
 * The calling page supplies the exact already-authorized transaction collection
 * used by its current Operational View or Full Audit view. This keeps browser
 * and PDF history aligned without duplicating stock-ledger query logic.
 */
function stream_feed_transaction_report_pdf(
    array $transactions,
    string $farmName,
    string $yearMonth,
    string $ledgerView,
    string $feedLabel
): never {
    $ledgerView = $ledgerView === 'audit'
        ? 'audit'
        : 'operational';

    $viewLabel = $ledgerView === 'audit'
        ? 'Full Audit'
        : 'Operational View';

    $monthLabel = date(
        'F Y',
        strtotime($yearMonth . '-01')
    );

    $reportTitle = $feedLabel
        . ' Feed Transaction History - '
        . $monthLabel
        . ' - '
        . $viewLabel;

    $safeLabel = strtolower(
        preg_replace(
            '/[^A-Za-z0-9]+/',
            '-',
            $feedLabel
        ) ?: 'feed'
    );

    $filename = $safeLabel
        . '-feed-transactions-'
        . $yearMonth
        . '-'
        . ($ledgerView === 'audit' ? 'audit' : 'operational')
        . '.pdf';

    ob_start();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($reportTitle); ?></title>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h2><?php echo htmlspecialchars(
                $feedLabel . ' Feed Transaction History'
            ); ?></h2>

            <div>
                <?php echo htmlspecialchars($monthLabel); ?>
                &nbsp;•&nbsp;
                <?php echo htmlspecialchars($viewLabel); ?>
            </div>
        </div>

        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Feed Item</th>
                        <th>Type / Status</th>
                        <th>Quantity</th>
                        <th>Previous Stock</th>
                        <th>New Stock</th>
                        <th>Unit</th>
                        <th>Production Type</th>
                        <th>Cycle</th>
                        <th>Remarks</th>
                        <th>Origin</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$transactions): ?>
                    <tr>
                        <td colspan="12">
                            No feed transactions recorded for this period
                            in <?php echo htmlspecialchars($viewLabel); ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $trans): ?>
                        <?php
                        $type = (string)($trans['transaction_type'] ?? '');

                        $status = 'Active';

                        if (!empty($trans['is_reversed'])) {
                            $status = 'Reversed';
                        } elseif (!empty($trans['reversal_of_id'])) {
                            $status = 'Restoration';
                        }

                        $quantityPrefix = $type === 'received'
                            ? '+'
                            : '-';

                        $productionLabel = attribution_label(
                            $trans['production_type']
                                ?? $trans['cycle_production_type']
                                ?? null
                        );

                        $cycleLabel = !empty($trans['cycle_code'])
                            ? (string)$trans['cycle_code']
                            : 'Pooled / Unallocated';

                        $remarks = trim(
                            (string)($trans['remarks'] ?? '')
                        );

                        if ($remarks === '') {
                            $remarks = '--';
                        }

                        $originLabel =
                            manual_feed_transaction_origin_label(
                                $trans
                            );

                        $recordedBy =
                            transaction_recorded_by_label(
                                $farmName,
                                $trans['full_name'] ?? null,
                                $trans['recorded_user_type'] ?? null
                            );
                        ?>

                        <tr>
                            <td>
                                <?php echo htmlspecialchars(
                                    date(
                                        'd/m/Y',
                                        strtotime(
                                            (string)$trans['transaction_date']
                                        )
                                    )
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    (string)($trans['item_name'] ?? '')
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    ucfirst($type)
                                ); ?>
                                <?php if ($status !== 'Active'): ?>
                                    — <?php echo htmlspecialchars($status); ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $quantityPrefix
                                    . number_format(
                                        (float)($trans['quantity'] ?? 0),
                                        2
                                    )
                                ); ?>
                            </td>

                            <td>
                                <?php echo number_format(
                                    (float)(
                                        $trans['display_previous_stock']
                                        ?? 0
                                    ),
                                    2
                                ); ?>
                            </td>

                            <td>
                                <?php echo number_format(
                                    (float)(
                                        $trans['display_new_stock']
                                        ?? 0
                                    ),
                                    2
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    (string)($trans['unit'] ?? '')
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $productionLabel
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $cycleLabel
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $remarks
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $originLabel
                                ); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars(
                                    $recordedBy
                                ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
    <?php

    $html = ob_get_clean();

    if ($html === false) {
        $html = '';
    }

    $service = new PdfReportService();

    $service->streamHtml(
        $html,
        $filename,
        'landscape',
        $reportTitle
    );
}
