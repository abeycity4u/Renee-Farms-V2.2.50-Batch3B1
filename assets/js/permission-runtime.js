/**
 * Runtime permission UI enforcement.
 * Server-side capabilities are supplied through #permissionRuntimeConfig.
 * Externalized for CSP compatibility.
 */
(function () {
    const configElement = document.getElementById('permissionRuntimeConfig');
    if (!configElement) return;

    let cfg = {};

    try {
        cfg = JSON.parse(configElement.dataset.config || '{}');
    } catch (error) {
        cfg = {};
    }

    function blockCalendar(btn) {
        btn.classList.remove('add-record-btn', 'edit-record-btn');
        btn.classList.add('no-action');
        btn.style.cursor = 'default';
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);
    }

    function stripActionColumn() {
        document.querySelectorAll('table').forEach(function (table) {
            const headers = Array.from(table.querySelectorAll('thead th'));

            headers.forEach(function (th, index) {
                if (th.textContent.trim().toLowerCase() === 'actions') {
                    table.querySelectorAll('tr').forEach(function (row) {
                        const cells = row.children;
                        if (cells[index]) cells[index].remove();
                    });
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const d = (cfg.daily && Object.keys(cfg.daily).length)
            ? cfg.daily
            : null;

        if (d) {
            if (!d.add) {
                document.querySelectorAll(
                    'button[data-open-record-modal],'
                    + 'button[onclick*="openRecordModal"]'
                ).forEach(function (el) {
                    el.remove();
                });
            }

            document.querySelectorAll('.calendar-day').forEach(function (btn) {
                const text = btn.textContent || '';
                const hasRecord =
                    btn.classList.contains('has-record')
                    || btn.hasAttribute('data-opening-stock')
                    || /record\(s\)/i.test(text);

                if (
                    (hasRecord && !d.edit)
                    || (!hasRecord && !d.add)
                ) {
                    blockCalendar(btn);
                }
            });

            if (!d.edit) {
                document.querySelectorAll(
                    'table .edit-record-btn'
                ).forEach(function (el) {
                    el.remove();
                });
            }

            if (!d.delete) {
                document.querySelectorAll(
                    'table button.btn-outline-danger,'
                    + 'table button[onclick*="deleteLayerDailyRecord"],'
                    + 'table button[onclick*="deleteBroilerDailyRecord"]'
                ).forEach(function (el) {
                    el.remove();
                });
            }

            if (!d.edit && !d.delete) {
                stripActionColumn();
            }
        }

        const x = cfg.extra || {};

        if (x.expenseAdd === false) {
            document.querySelectorAll(
                'button[data-bs-target="#addExpenseModal"]'
            ).forEach(function (el) {
                el.remove();
            });
        }

        if (x.expenseEdit === false) {
            document.querySelectorAll(
                '.edit-expense-btn'
            ).forEach(function (el) {
                el.remove();
            });
        }

        if (x.expenseDelete === false) {
            document.querySelectorAll(
                'button[data-delete-expense-id],'
                + 'button[onclick*="deleteExpense"]'
            ).forEach(function (el) {
                el.remove();
            });
        }

        if (x.feedAdd === false) {
            document.querySelectorAll(
                'button[data-bs-target="#addTransactionModal"]'
            ).forEach(function (el) {
                el.remove();
            });
        }

        if (x.salesAdd === false) {
            document.querySelectorAll(
                'button[data-bs-target="#addSaleModal"],'
                + 'button[onclick*="addSale"]'
            ).forEach(function (el) {
                el.remove();
            });
        }

        if (x.salesPayment === false) {
            document.querySelectorAll(
                'button[data-bs-target*="payment" i],'
                + 'button[onclick*="payment" i],'
                + 'form button[name="record_payment"]'
            ).forEach(function (el) {
                el.remove();
            });
        }

        if (x.salesEdit === false) {
            document.querySelectorAll(
                '.edit-sale-btn'
            ).forEach(function (el) {
                el.remove();
            });
        }

        if (x.salesDelete === false) {
            document.querySelectorAll(
                'button[data-sale-delete-id],'
                + 'button[onclick*="deleteSale"]'
            ).forEach(function (el) {
                el.remove();
            });
        }

        if (x.animalAdd === false) {
            document.querySelectorAll(
                'button[data-ruminant-animal-new],'
                + 'button[onclick*="newAnimal"]'
            ).forEach(function (el) {
                el.remove();
            });
        }

        if (x.animalEdit === false) {
            document.querySelectorAll(
                'button[data-ruminant-animal-edit],'
                + 'button[onclick*="editAnimal"]'
            ).forEach(function (el) {
                el.remove();
            });
        }

        if (x.animalExit === false) {
            document.querySelectorAll(
                'button[data-ruminant-animal-exit],'
                + 'button[onclick*="exitAnimal"]'
            ).forEach(function (el) {
                el.remove();
            });
        }

        const nav = cfg.nav || {};

        Object.keys(nav).forEach(function (suffix) {
            if (nav[suffix]) return;

            document.querySelectorAll(
                '#appNavbar a[href]'
            ).forEach(function (anchor) {
                try {
                    const path = new URL(
                        anchor.href,
                        window.location.origin
                    ).pathname;

                    if (path.endsWith(suffix)) {
                        anchor.closest('li')?.remove();
                    }
                } catch (error) {
                    // Ignore malformed navigation URLs.
                }
            });
        });

        document.querySelectorAll(
            '#appNavbar .dropdown'
        ).forEach(function (drop) {
            if (
                drop.querySelector('#manageMenu')
                && !drop.querySelector('.dropdown-item[href]')
            ) {
                drop.remove();
            }
        });
    });
})();
