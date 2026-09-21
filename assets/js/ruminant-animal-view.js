/**
 * Ruminant Animal Profile browser behavior.
 *
 * Externalized to remain compatible with the platform-wide
 * script-src 'self' Content Security Policy.
 */
(function () {
    'use strict';

    function showModal(element) {
        if (
            !element
            || !window.bootstrap
            || !window.bootstrap.Modal
        ) {
            return;
        }

        window.bootstrap.Modal
            .getOrCreateInstance(element)
            .show();
    }

    function wireTransferReversalModal() {
        var modalElement =
            document.getElementById(
                'cycleTransferReverseModal'
            );

        if (!modalElement) {
            return;
        }

        modalElement.addEventListener(
            'show.bs.modal',
            function (event) {
                var trigger =
                    event.relatedTarget;

                if (!trigger) {
                    return;
                }

                var idInput =
                    document.getElementById(
                        'cycle_transfer_reverse_id'
                    );

                var fromInput =
                    document.getElementById(
                        'cycle_transfer_reverse_from'
                    );

                var toInput =
                    document.getElementById(
                        'cycle_transfer_reverse_to'
                    );

                var dateInput =
                    document.getElementById(
                        'cycle_transfer_reverse_date'
                    );

                var reasonInput =
                    modalElement.querySelector(
                        '[name="transfer_reversal_reason"]'
                    );

                if (idInput) {
                    idInput.value =
                        trigger.getAttribute(
                            'data-transfer-reverse-id'
                        ) || '';
                }

                if (fromInput) {
                    fromInput.value =
                        trigger.getAttribute(
                            'data-transfer-from'
                        ) || '—';
                }

                if (toInput) {
                    toInput.value =
                        trigger.getAttribute(
                            'data-transfer-to'
                        ) || '—';
                }

                if (dateInput) {
                    dateInput.value =
                        trigger.getAttribute(
                            'data-transfer-date'
                        ) || '—';
                }

                if (reasonInput) {
                    reasonInput.value = '';
                }
            }
        );
    }

    function restoreFailedModalState() {
        var config =
            document.getElementById(
                'ruminantAnimalViewConfig'
            );

        if (!config) {
            return;
        }

        if (
            config.dataset.reopenTransferReverse
            === '1'
        ) {
            showModal(
                document.getElementById(
                    'cycleTransferReverseModal'
                )
            );
        }

        if (
            config.dataset.reopenTransfer
            === '1'
        ) {
            showModal(
                document.getElementById(
                    'cycleTransferModal'
                )
            );
        }
    }

    function initializeAnimalView() {
        wireTransferReversalModal();
        restoreFailedModalState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initializeAnimalView
        );
    } else {
        initializeAnimalView();
    }
})();
