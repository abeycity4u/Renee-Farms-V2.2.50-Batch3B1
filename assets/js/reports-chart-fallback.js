/**
 * Reports Chart.js fallback loader.
 * Loaded before the primary Chart.js request so shared app-behaviors.js
 * can invoke window.loadChartFallback if the primary script fails.
 * Externalized for CSP compatibility.
 */
(function () {
    const loaderScript = document.currentScript;
    const fallbackSource =
        loaderScript?.dataset.fallbackSrc || '';

window.loadChartFallback = function loadChartFallback() {
            if (window.fmChartFallbackLoaded) return;
            window.fmChartFallbackLoaded = true;
            var fallbackScript = document.createElement('script');
            fallbackScript.src = fallbackSource;
            document.head.appendChild(fallbackScript);
        };
})();
