/**
 * Shared Daily Record population-movement UI helpers.
 *
 * Browser pages consume canonical movement_totals returned by
 * api/get_previous_stock.php. No population movement is inferred here.
 */
(function (global) {
    'use strict';

    function createSoldStockDisplay(options = {}) {
        const type = String(options.type || '').trim();
        const cycleId = Number(options.cycleId || 0);
        const endpoint = String(
            options.endpoint || '../api/get_previous_stock.php'
        );
        const containerId = String(
            options.containerId || 'soldStockContainer'
        );
        const displayId = String(
            options.displayId || 'soldStockDisplay'
        );

        function elements() {
            return {
                container: document.getElementById(containerId),
                display: document.getElementById(displayId)
            };
        }

        function clear() {
            const { container, display } = elements();

            if (!container || !display) {
                return;
            }

            display.textContent = '';
            container.classList.add('d-none');
        }

        function render(payload) {
            const { container, display } = elements();

            if (!container || !display) {
                return;
            }

            const saleDelta = Number(
                payload?.movement_totals?.sale ?? 0
            );
            const soldStock =
                Number.isFinite(saleDelta) && saleDelta < 0
                    ? Math.abs(saleDelta)
                    : 0;

            if (soldStock <= 0) {
                clear();
                return;
            }

            display.textContent =
                `Sold Stock: ${soldStock.toLocaleString()}`;
            container.classList.remove('d-none');
        }

        function fetchForDate(selectedDate) {
            if (!type || cycleId <= 0 || !selectedDate) {
                clear();
                return Promise.resolve(null);
            }

            const query = new URLSearchParams({
                type,
                date: selectedDate,
                cycle_id: String(cycleId)
            });

            return fetch(`${endpoint}?${query.toString()}`)
                .then(response => response.json())
                .then(payload => {
                    if (payload && payload.success === false) {
                        throw new Error(
                            payload.error
                                || 'Failed to fetch population movements'
                        );
                    }

                    render(payload);
                    return payload;
                })
                .catch(error => {
                    clear();
                    console.error(error);
                    return null;
                });
        }

        return Object.freeze({
            clear,
            render,
            fetchForDate
        });
    }

    function ruminantMovementFacts(
        payload = {},
        dailyRecordMortality = 0
    ) {
        const totals = payload?.movement_totals || {};

        function removalQuantity(type) {
            const delta = Number(totals[type] ?? 0);

            return Number.isFinite(delta) && delta < 0
                ? Math.abs(delta)
                : 0;
        }

        const groupMortality = Math.max(
            0,
            Number(dailyRecordMortality) || 0
        );
        const totalMortality = removalQuantity('mortality');

        return Object.freeze({
            soldStock: removalQuantity('sale'),
            culledStock: removalQuantity('cull'),
            taggedMortality: Math.max(
                0,
                totalMortality - groupMortality
            )
        });
    }

    function createRuminantMovementDisplay(options = {}) {
        const container = document.getElementById(
            String(
                options.containerId
                    || 'ruminantPopulationMovements'
            )
        );

        const rows = {
            soldStock: document.getElementById(
                String(
                    options.soldRowId
                        || 'ruminantSoldStockRow'
                )
            ),
            culledStock: document.getElementById(
                String(
                    options.culledRowId
                        || 'ruminantCulledStockRow'
                )
            ),
            taggedMortality: document.getElementById(
                String(
                    options.taggedMortalityRowId
                        || 'ruminantTaggedMortalityRow'
                )
            )
        };

        const values = {
            soldStock: document.getElementById(
                String(
                    options.soldValueId
                        || 'ruminantSoldStockValue'
                )
            ),
            culledStock: document.getElementById(
                String(
                    options.culledValueId
                        || 'ruminantCulledStockValue'
                )
            ),
            taggedMortality: document.getElementById(
                String(
                    options.taggedMortalityValueId
                        || 'ruminantTaggedMortalityValue'
                )
            )
        };

        function clear() {
            Object.values(rows).forEach(row => {
                row?.classList.add('d-none');
            });

            Object.values(values).forEach(value => {
                if (value) value.textContent = '0';
            });

            container?.classList.add('d-none');
        }

        function render(payload, dailyRecordMortality = 0) {
            const facts = ruminantMovementFacts(
                payload,
                dailyRecordMortality
            );
            let visible = false;

            Object.entries(facts).forEach(([key, quantity]) => {
                const row = rows[key];
                const value = values[key];

                if (quantity > 0) {
                    if (value) {
                        value.textContent =
                            quantity.toLocaleString();
                    }
                    row?.classList.remove('d-none');
                    visible = true;
                } else {
                    row?.classList.add('d-none');
                }
            });

            container?.classList.toggle('d-none', !visible);

            return facts;
        }

        clear();

        return Object.freeze({
            clear,
            render
        });
    }

    global.DailyPopulationMovementUI = Object.freeze({
        createSoldStockDisplay,
        ruminantMovementFacts,
        createRuminantMovementDisplay
    });
})(window);
