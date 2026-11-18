(() => {
    const groups = Array.from(document.querySelectorAll('[data-portal-access]'));
    if (!groups.length) return;
    let pending = false;
    const display = authenticated => groups.forEach(group => {
        group.querySelectorAll('[data-portal-guest]').forEach(link => { link.hidden = authenticated; });
        group.querySelector('[data-portal-panel]').hidden = !authenticated;
    });
    async function refresh() {
        if (pending) return;
        pending = true;
        try {
            const url = new URL(groups[0].dataset.sessionUrl, window.location.href);
            if (url.origin !== window.location.origin) return;
            const response = await fetch(url.href, { credentials:'same-origin', cache:'no-store', headers:{ Accept:'application/json' } });
            const data = response.ok ? await response.json() : {};
            display(data.authenticated === true);
        } catch { display(false); }
        finally { pending = false; }
    }
    refresh();
    window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
