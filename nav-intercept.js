/**
 * nav-intercept.js
 * Loaded by every inner page. When running inside the shell iframe,
 * intercepts all same-origin anchor clicks and notifies the shell
 * to swap the iframe src — so the outer shell (and its music player)
 * never reloads.
 */
(function () {
    if (window.self === window.top) return; // not inside an iframe, do nothing

    // Tell shell which page just loaded (for nav highlight)
    window.parent.postMessage({ type: 'fini:loaded', url: location.href }, '*');

    // Inject CSS to remove the inner page's bottom-player-bar reservation
    // (shell.php owns the player bar outside the iframe)
    var style = document.createElement('style');
    style.textContent = [
        /* remove bottom gap left for inner player bar */
        '.mel-layout { bottom: 0 !important; }',
        /* hide the inner page duplicate player bar */
        '.mel-player { display: none !important; }',
        /* hide the inner page duplicate YT popup */
        '#yt-player { display: none !important; }',
    ].join('\n');
    document.head.appendChild(style);

    // Intercept nav link clicks
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a) return;

        var href = a.getAttribute('href');
        if (!href) return;

        var url = new URL(href, location.href);
        if (url.origin !== location.origin) return;  // external
        if (href.startsWith('#')) return;             // hash anchor
        if (url.pathname.includes('logout')) return;  // full reload for logout
        if (a.target === '_blank') return;            // new tab

        e.preventDefault();
        window.parent.postMessage({ type: 'fini:navigate', url: url.href }, '*');
    }, true);
})();
