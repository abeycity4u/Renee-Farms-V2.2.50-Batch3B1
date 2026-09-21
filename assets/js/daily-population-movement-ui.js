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

    global.DailyPopulationMovementUI = Object.freeze({
        createSoldStockDisplay
    });
})(window);
