(() => {
    const start = () => {
        const root = document.getElementById('vita-system-notifications');
        if (!root || window.VitaUserRealtime) return;
        const state = window.VitaUserRealtime = { ready: false };
        const customer = root.dataset.customerId, user = root.dataset.userId;
        const bell = document.getElementById('system-notification-badge');
        const count = document.getElementById('system-notification-count');
        let timer, request, echo;
        const chat = () => {
            window.dispatchEvent(new CustomEvent('vita:chat-sync'));
            try { document.getElementById('vita-content-current')?.contentWindow?.dispatchEvent(new CustomEvent('vita:chat-sync')); } catch (_) {}
        };
        const system = () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                request?.abort(); request = new AbortController();
                try {
                    const response = await fetch(root.dataset.summaryUrl, {headers:{Accept:'application/json'}, cache:'no-store', signal:request.signal});
                    if (!response.ok) return;
                    const data = await response.json();
                    const unread = Number(data.unread) || 0;
                    bell.textContent = unread > 99 ? '99+' : String(unread);
                    bell.hidden = !unread; bell.style.display = unread ? 'flex' : 'none';
                    count.textContent = `${unread} nuevas`; count.hidden = !unread;
                    document.getElementById('system-notification-list').innerHTML = data.html;
                    try { document.getElementById('vita-content-current')?.contentWindow?.dispatchEvent(new CustomEvent('vita:system-sync')); } catch (_) {}
                } catch (_) { /* Keep the last confirmed state while offline. */ }
            }, 150);
        };
        const sync = () => { if (!document.hidden) { system(); chat(); } };
        const connect = () => {
            if (!window.Echo) return;
            echo = window.Echo;
            const channel = echo.private(`clinic-user.${customer}.${user}`);
            channel.listen('.user.updated', event => {
                if (event.area === 'system') system();
                else if (event.area === 'chat') chat();
            });
            channel.subscribed(() => { state.ready = true; sync(); });
            channel.error(() => { state.ready = false; });
            const connection = echo.connector?.pusher?.connection;
            connection?.bind('connected', sync);
            connection?.bind('state_change', change => { if (change.current !== 'connected') state.ready = false; });
        };
        connect();
        const retry = setInterval(() => { if (!echo) connect(); else clearInterval(retry); }, 1000);
        setInterval(() => { if (!state.ready) sync(); }, 30000);
        window.addEventListener('focus', sync);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
        root.addEventListener('click', system);
        sync();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true});
    else start();
})();
