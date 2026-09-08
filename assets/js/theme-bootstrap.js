/**
 * Early theme bootstrap.
 * Must remain synchronous in <head> to apply the saved theme before paint.
 * Externalized for CSP compatibility.
 */
(function(){var t='light';try{t=localStorage.getItem('farm-theme')||'light';}catch(e){}if(t!=='dark'&&t!=='light')t='light';document.documentElement.setAttribute('data-theme',t);document.documentElement.setAttribute('data-bs-theme',t);})();
