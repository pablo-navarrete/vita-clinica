<style>
    [data-presence-user] { position: relative; overflow: visible; }
    .chat-presence-dot { position: absolute; right: -1px; bottom: -1px; width: 12px; height: 12px; border: 2px solid white; border-radius: 50%; background: #dc2626; pointer-events: none; }
    .chat-presence-dot[data-online="true"] { background: #16a34a; }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let online = new Set();
    let busy = false;
    let lastSuccess = 0;
    const paint = () => {
        document.querySelectorAll('[data-presence-user]').forEach(avatar => {
            let dot = avatar.querySelector('.chat-presence-dot');
            if (!dot) {
                dot = document.createElement('span');
                dot.className = 'chat-presence-dot';
                dot.setAttribute('role', 'img');
                avatar.appendChild(dot);
            }
            const connected = online.has(Number(avatar.dataset.presenceUser));
            dot.dataset.online = String(connected);
            dot.title = connected ? 'En línea' : 'Desconectado';
            dot.setAttribute('aria-label', dot.title);
        });
    };
    const observer = new MutationObserver(records => {
        if (records.some(record => [...record.addedNodes].some(node => node.nodeType === 1 && !node.classList.contains('chat-presence-dot')))) paint();
    });
    observer.observe(document.body, { childList: true, subtree: true });
    const refresh = async () => {
        if (busy) return;
        busy = true;
        try {
            const response = await fetch(@json(route('customers.presence', ['customer' => request()->route('customer')])), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            if (!response.ok) throw new Error('Presence unavailable');
            const result = await response.json();
            online = new Set(result.online.map(Number));
            lastSuccess = Date.now();
        } catch (_) {
            if (Date.now() - lastSuccess >= 60000) online.clear();
        } finally {
            busy = false;
            paint();
        }
    };
    paint();
    refresh();
    setInterval(refresh, 20000);
    window.addEventListener('online', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
});
</script>
