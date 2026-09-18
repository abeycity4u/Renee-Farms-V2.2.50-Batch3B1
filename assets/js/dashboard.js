/**
 * Dashboard browser runtime.
 * Dynamic server state is supplied through dashboardConfigElement.
 *
 * This remains a classic top-level script intentionally:
 * quickStockUpdate() and showAlert() are shared runtime contracts used by
 * app-behaviors.js and dashboard permission helpers.
 * Externalized for CSP compatibility.
 */
const dashboardConfigElement =
    document.getElementById('dashboardConfig');

const dashboardParseJson = (value, fallback) => {
    try {
        return JSON.parse(value || '');
    } catch (error) {
        return fallback;
    }
};

const dashboardConfig = {
    lowStockCount: Number(
        dashboardConfigElement?.dataset.lowStockCount || 0
    ),
    farmAccess:
        dashboardConfigElement?.dataset.farmAccess || '',
    activeCycles: dashboardParseJson(
        dashboardConfigElement?.dataset.activeCycles,
        []
    ),
    csrfToken:
        dashboardConfigElement?.dataset.csrfToken || '',
    lowStockItems: dashboardParseJson(
        dashboardConfigElement?.dataset.lowStockItems,
        []
    ),
    today:
        dashboardConfigElement?.dataset.today || ''
};

let dashboardLowStockCount = dashboardConfig.lowStockCount;

    // Initialize dashboard
    $(document).ready(function() {
        // Native title hints do not require Bootstrap Tooltip or Popper.
        
        // Auto-refresh stock every 30 seconds without reloading or moving the page.
        setInterval(refreshStockData, 30000);
        
        // Check for new notifications
        checkNotifications();
    });
    
    // Filter stock table
    function filterStock(filterType) {
        const rows = document.querySelectorAll('#stockTable tbody tr');

        rows.forEach(row => {
            let showRow = true;
            const farmType = row.getAttribute('data-farm-type');
            const stockStatus = row.getAttribute('data-stock-status');

            // Keep placeholder row visible only in "all" mode
            if (!farmType && !stockStatus) {
                row.style.display = filterType === 'all' ? '' : 'none';
                return;
            }

            if (filterType === 'low') {
                showRow = stockStatus === 'danger';
            } else if (filterType === 'poultry') {
                showRow = farmType === 'poultry' || farmType === 'both';
            } else if (filterType === 'ruminant') {
                showRow = farmType === 'ruminant' || farmType === 'both';
            }

            row.style.display = showRow ? '' : 'none';
        });
    }

    document.getElementById('stockFilterMenu')?.addEventListener('click', function(event) {
        const filterLink = event.target.closest('[data-stock-filter]');
        if (!filterLink) {
            return;
        }

        event.preventDefault();
        filterStock(filterLink.getAttribute('data-stock-filter'));

        const filterMenu = document.getElementById('stockFilterMenu');
        const filterButton = document.getElementById('stockFilterButton');
        filterMenu?.classList.remove('show');
        filterButton?.setAttribute('aria-expanded', 'false');
    });

    // Custom dropdown toggle for stock filter (avoids Bootstrap Popper dependency issues).
    (function initStockFilterDropdown() {
        const dropdown = document.getElementById('stockFilterDropdown');
        const button = document.getElementById('stockFilterButton');
        const menu = document.getElementById('stockFilterMenu');

        if (!dropdown || !button || !menu) {
            return;
        }

        button.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const isOpen = menu.classList.contains('show');
            menu.classList.toggle('show', !isOpen);
            button.setAttribute('aria-expanded', String(!isOpen));
        });

        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target)) {
                menu.classList.remove('show');
                button.setAttribute('aria-expanded', 'false');
            }
        });
    })();
    
    // Quick stock update
    let dashboardQuickStockItem = null;

    function dashboardQuickStockProductionOptions(item) {
        const farmType =
            String(item?.farm_type || 'both')
                .toLowerCase();

        if (farmType === 'poultry') {
            return [
                ['shared', 'Shared Poultry'],
                ['layer', 'Layer'],
                ['broiler', 'Broiler']
            ];
        }

        if (farmType === 'ruminant') {
            return [
                ['shared', 'Shared Ruminant'],
                ['cattle', 'Cattle'],
                ['goat', 'Goat'],
                ['sheep', 'Sheep'],
                ['other', 'Other']
            ];
        }

        return [
            ['shared', 'Shared / Farm-wide']
        ];
    }

    function dashboardPopulateQuickStockProduction(item) {
        const select =
            document.getElementById(
                'quickStockProductionType'
            );

        if (!select) {
            return;
        }

        const options =
            dashboardQuickStockProductionOptions(
                item
            );

        select.replaceChildren();

        options.forEach(function(option) {
            select.add(
                new Option(
                    option[1],
                    option[0]
                )
            );
        });

        const requestedDefault =
            String(
                item?.default_production_type
                || 'shared'
            ).toLowerCase();

        const hasRequestedDefault =
            options.some(function(option) {
                return option[0]
                    === requestedDefault;
            });

        select.value =
            hasRequestedDefault
                ? requestedDefault
                : 'shared';
    }

    function dashboardUpdateQuickStockFields() {
        const item =
            dashboardQuickStockItem;

        if (!item) {
            return;
        }

        const typeSelect =
            document.getElementById(
                'transType'
            );

        const productionWrap =
            document.getElementById(
                'quickStockProductionWrap'
            );

        const productionSelect =
            document.getElementById(
                'quickStockProductionType'
            );

        const cycleWrap =
            document.getElementById(
                'quickStockCycleWrap'
            );

        const cycleSelect =
            document.getElementById(
                'quickStockCycleId'
            );

        const transactionType =
            String(
                typeSelect?.value
                || 'received'
            ).toLowerCase();

        const usage =
            String(
                item.feed_category
                || 'general'
            ).toLowerCase();

        const farmType =
            String(
                item.farm_type
                || 'both'
            ).toLowerCase();

        const generalStock =
            usage === 'general';

        if (productionWrap) {
            productionWrap.classList.toggle(
                'd-none',
                !generalStock
            );
        }

        const productionType =
            generalStock
                ? String(
                    productionSelect?.value
                    || 'shared'
                ).toLowerCase()
                : 'shared';

        const showCycle =
            transactionType === 'used'
            &&
            generalStock
            &&
            productionType !== 'shared'
            &&
            farmType !== 'both';

        if (cycleSelect) {
            const previousCycle =
                cycleSelect.value;

            cycleSelect.replaceChildren(
                new Option(
                    'No specific cycle / pooled usage',
                    ''
                )
            );

            if (showCycle) {
                dashboardConfig.activeCycles
                    .filter(function(cycle) {
                        return (
                            String(
                                cycle.farm_type
                                || ''
                            ).toLowerCase()
                                === farmType
                            &&
                            String(
                                cycle.production_type
                                || ''
                            ).toLowerCase()
                                === productionType
                        );
                    })
                    .forEach(function(cycle) {
                        cycleSelect.add(
                            new Option(
                                String(
                                    cycle.cycle_code
                                    || ''
                                ),
                                String(
                                    cycle.id
                                    || ''
                                )
                            )
                        );
                    });

                const previousStillExists =
                    Array.from(
                        cycleSelect.options
                    ).some(function(option) {
                        return option.value
                            === previousCycle;
                    });

                if (previousStillExists) {
                    cycleSelect.value =
                        previousCycle;
                }
            }
        }

        if (cycleWrap) {
            cycleWrap.classList.toggle(
                'd-none',
                !showCycle
            );
        }


    }

    function quickStockUpdate(itemId) {
        fetch(
            `api/get_item_details.php?id=${encodeURIComponent(itemId)}`
        )
            .then(function(response) {
                return response.json()
                    .then(function(data) {
                        return {
                            ok: response.ok,
                            data: data
                        };
                    });
            })
            .then(function(result) {
                const data =
                    result.data || {};

                if (
                    !result.ok
                    ||
                    data.error
                ) {
                    throw new Error(
                        data.error
                        || 'Unable to load inventory item.'
                    );
                }

                dashboardQuickStockItem =
                    data;

                const quickStockForm =
                    document.getElementById(
                        'quickStockForm'
                    );

                if (quickStockForm) {
                    quickStockForm.dataset.feedCategory =
                        String(
                            data.feed_category
                            || 'general'
                        ).toLowerCase();
                }

                const itemIdInput =
                    document.getElementById(
                        'stockItemId'
                    );

                const itemNameInput =
                    document.getElementById(
                        'stockItemName'
                    );

                const details =
                    document.getElementById(
                        'stockItemDetails'
                    );

                const quantityInput =
                    document.getElementById(
                        'quantity'
                    );

                const remarksInput =
                    document.getElementById(
                        'remarks'
                    );

                if (itemIdInput) {
                    itemIdInput.value =
                        itemId;
                }

                if (itemNameInput) {
                    itemNameInput.value =
                        data.item_name || '';
                }

                if (details) {
                    details.textContent =
                        `Current stock: ${data.current_stock} ${data.unit}`
                        + ` • Min: ${data.min_stock_level} ${data.unit}`;
                }

                if (quantityInput) {
                    quantityInput.value = '';
                }

                if (remarksInput) {
                    remarksInput.value = '';
                }

                dashboardPopulateQuickStockProduction(
                    data
                );

                dashboardUpdateQuickStockFields();

                const modal =
                    new bootstrap.Modal(
                        document.getElementById(
                            'quickStockModal'
                        )
                    );

                modal.show();
            })
            .catch(function(error) {
                showAlert(
                    'danger',
                    'Error loading item details: '
                    + error.message
                );
            });
    }

    document.getElementById(
        'transType'
    )?.addEventListener(
        'change',
        dashboardUpdateQuickStockFields
    );

    document.getElementById(
        'quickStockProductionType'
    )?.addEventListener(
        'change',
        dashboardUpdateQuickStockFields
    );

    /*
     * Mutation submission belongs exclusively to
     * dashboard-quick-stock.js, which is injected by the
     * Dashboard permission bridge for users allowed to update stock.
     *
     * dashboard.js owns only item loading and attribution UI composition.
     */
    // Refresh low-stock status in the background without changing scroll position.
    function refreshStockData() {
        fetch(`api/get_stock_summary.php?farm_type=${encodeURIComponent(dashboardConfig.farmAccess)}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.updated) {
                    const lowStockCount = Number(data.low_stock_count || 0);
                    const previousLowStockCount = dashboardLowStockCount;

                    if (lowStockCount !== previousLowStockCount) {
                        updateNotificationBadge(lowStockCount);
                        if (lowStockCount > previousLowStockCount) {
                            const added = lowStockCount - previousLowStockCount;
                            showAlert('warning', `${added} new item${added > 1 ? 's are' : ' is'} now low on stock!`);
                        }
                        dashboardLowStockCount = lowStockCount;
                    }
                }
            });
    }
    
    // Check for notifications
    function checkNotifications() {
        // Check for low stock notifications
        const lowStockItems = dashboardConfig.lowStockItems;
        if (lowStockItems.length > 0) {
            const notificationCount = lowStockItems.length;
            if (notificationCount > 0) {
                // Show persistent notification badge
                updateNotificationBadge(notificationCount);
                
                // Show initial alert if first visit
                if (!sessionStorage.getItem('stockAlertShown')) {
                    showAlert('warning', 
                        `You have ${notificationCount} item${notificationCount > 1 ? 's' : ''} with low stock. ` +
                        `Please reorder soon.`, 
                        10000);
                    sessionStorage.setItem('stockAlertShown', 'true');
                }
            }
        }
        
        // Check for pending tasks
        const today = dashboardConfig.today;
        fetch(`api/check_pending_tasks.php?farm_type=${encodeURIComponent(dashboardConfig.farmAccess)}&date=${encodeURIComponent(today)}`)
            .then(response => response.json())
            .then(data => {
                if (data.pending_tasks > 0) {
                    showAlert('info', 
                        `You have ${data.pending_tasks} pending task${data.pending_tasks > 1 ? 's' : ''} for today.`, 
                        8000);
                }
            });
    }
    
    // Update notification badge
    function updateNotificationBadge(count) {
        let badge = document.getElementById('notificationBadge');
        if (!badge) {
            badge = document.createElement('span');
            badge.id = 'notificationBadge';
            badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
            badge.style.fontSize = '0.6rem';
            
            const bellIcon = document.querySelector('.bi-bell');
            if (bellIcon) {
                bellIcon.parentElement.style.position = 'relative';
                bellIcon.parentElement.appendChild(badge);
            }
        }
        
        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = count > 0 ? 'block' : 'none';
    }
    
    // Use the platform-wide notification system for all dashboard pop notifications.
    function showAlert(type, message, duration = 5000) {
        const mapped = type === 'danger' ? 'error' : type;
        if (window.AppNotify) {
            return AppNotify.show(mapped, message, null, null, duration);
        }
    }
    
    // Auto-update time
    function updateCurrentTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true
        });
        const dateString = now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        const timeElement = document.querySelector('.current-time');
        if (timeElement) {
            timeElement.textContent = `${dateString} • ${timeString}`;
        }
    }
    
    // Update time every minute
    setInterval(updateCurrentTime, 60000);
    updateCurrentTime(); // Initial call
