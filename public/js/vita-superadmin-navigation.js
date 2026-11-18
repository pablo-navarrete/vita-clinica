(() => {
    if (!document.getElementById('vita-module-slot') || !window.VitaNavigation) return;

    const publicUrl = value => {
        const url = new URL(value, location.href);
        if (url.origin !== location.origin) return null;
        url.searchParams.delete('_vita_content');
        url.searchParams.delete('_vita_frame');
        return url;
    };

    // The existing content bridge delegates navigation and modal positioning to this host.
    window.VitaNavigation.framed = true;
    window.VitaNavigation.send = data => {
        if (data.type !== 'vita:module-location') return;
        const url = publicUrl(data.url);
        if (!url) return;
        if (url.href !== location.href) {
            const method = data.historyPush ? 'pushState' : 'replaceState';
            history[method]({ vitaSuperadmin: true }, '', url.href);
        }
        if (data.title) document.title = data.title;
    };
    window.addEventListener('popstate', () => window.VitaModules?.navigate(location.href, false));

    // Logout deliberately leaves the persistent administration layout.
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const url = new URL(form.getAttribute('action') || '', location.href);
        if (url.pathname === '/logout') form.target = '_top';
    }, true);
})();
