/**
 * Centralized application calendar runtime.
 * Values are supplied by navbar_head.php through this script element.
 * Externalized for CSP compatibility.
 */
(function () {
    const calendarScript = document.currentScript;

    if (!calendarScript) return;

window.ReneeCalendar = window.ReneeCalendar || {
    today: calendarScript.dataset.today || '',
    timezone: calendarScript.dataset.timezone || '',
    currentMonth: calendarScript.dataset.currentMonth || ''
};
})();
