(() => {
    let framed = false, contentHost = false;
    try { framed = window.parent !== window && window.parent.document.getElementById('vita-module-frame')?.contentWindow === window; } catch (_) {}
    try {
        contentHost = window.parent !== window && window.parent.VitaModules?.isContentWindow(window);
        if (contentHost) framed = Boolean(window.parent.VitaNavigation?.framed);
    } catch (_) {}
    let state = { sessionId:null, joined:false };
    const send = data => {
        if (contentHost) window.parent.VitaNavigation?.relay(data, window);
        else if (framed) window.parent.postMessage(data,location.origin);
    };
    window.VitaNavigation = {
        framed,
        send,
        changeContext(value) {
            if (contentHost) return window.parent.VitaNavigation.changeContext(value);
            const url = new URL(value, location.href);
            if (url.origin !== location.origin || state.joined) return false;
            url.searchParams.delete('_vita_frame');
            url.searchParams.delete('_vita_content');
            window.top.location.assign(url.href);
            return true;
        },
        currentUrl() {
            if (contentHost) return location.href;
            if (window.VitaModules) return window.VitaModules.currentUrl();
            return location.href;
        },
        refresh() {
            if (contentHost) return window.parent.VitaModules.refresh();
            if (window.VitaModules) return window.VitaModules.refresh();
            window.location.reload();
            return true;
        },
        navigate(url) {
            if (contentHost) return window.parent.VitaModules.navigate(url);
            if (window.VitaModules) return window.VitaModules.navigate(url);
            window.location.assign(url);
            return true;
        },
        requestState() { send({type:'vita:request-call-state'}); },
        relay(data, source) {
            if (!window.VitaModules?.isContentWindow(source)) return;
            if (data.type === 'vita:module-location') {
                if (window.VitaModules.isCurrentWindow(source)) window.VitaModules.sync(false);
            } else if (data.type === 'vita:content-pointer' && window.VitaModules.isCurrentWindow(source)) {
                document.body.dispatchEvent(new MouseEvent('click', { bubbles:true }));
            } else if (data.type === 'vita:modal-state' && window.VitaModules.isCurrentWindow(source)) window.VitaModules.setModal(Boolean(data.open));
            else if (data.type === 'vita:open-call' && window.VitaModules.isCurrentWindow(source)) send(data);
            else if (data.type === 'vita:request-call-state') send(data);
        },
        openRoom(element) {
            if (!element?.dataset.vitaRoomUrl) return;
            if (framed) {
                send({type:'vita:open-call',url:element.dataset.vitaRoomUrl,sessionId:element.dataset.vitaSessionId,
                    patient:element.dataset.vitaPatient,recordUrl:element.dataset.vitaRecordUrl});
            } else {
                const slot = element.querySelector('[data-vita-call-slot]');
                if (!slot || slot.querySelector('iframe')) return;
                const iframe = document.createElement('iframe');
                iframe.title = 'Sala de teleconsulta'; iframe.allow = 'camera; microphone; autoplay; fullscreen';
                iframe.src = element.dataset.vitaRoomUrl; slot.replaceChildren(iframe);
            }
        },
    };
    if (!framed) return;
    const locationChanged = () => send({type:'vita:module-location',url:location.href,title:document.title});
    for (const method of ['pushState','replaceState']) {
        const original = history[method].bind(history);
        history[method] = (...args) => { original(...args); locationChanged(); };
    }
    window.addEventListener('popstate',locationChanged);
    window.addEventListener('hashchange',locationChanged);
    window.addEventListener('pageshow',locationChanged);
    locationChanged();
    send({type:'vita:request-call-state'});
    window.addEventListener('message',event => {
        if (event.source !== window.parent || event.origin !== location.origin || event.data?.type !== 'vita:call-state') return;
        state = event.data;
        window.VitaModules?.sendState(state);
        for (const slot of document.querySelectorAll('[data-vita-call-slot]')) {
            const owner = slot.closest('[data-vita-session-id]');
            const active = state.sessionId === owner?.dataset.vitaSessionId;
            const label = slot.querySelector('[data-vita-call-status]');
            if (label) label.textContent = active ? 'La sala sigue abierta en el cuadro flotante mientras navegas por Vita.' : 'Abre la sala flotante para ingresar a esta teleconsulta.';
        }
    });
    if (contentHost) {
        const notifyContentReady = () => setTimeout(() => window.parent.VitaModules?.contentReady(window), 0);
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', notifyContentReady, {once:true});
        else notifyContentReady();
        document.addEventListener('pointerdown', () => send({type:'vita:content-pointer'}), true);
        let modalOpen = false, modalFrame = null;
        const publishModalState = (force = false) => {
            const candidates = document.querySelectorAll('.fixed.inset-0, [role="dialog"], [aria-modal="true"], .swal2-container, dialog[open], .modal.show');
            const open = [...candidates].some(element => {
                const style = getComputedStyle(element);
                return element.getClientRects().length > 0 && style.display !== 'none' && style.visibility !== 'hidden' && !element.hasAttribute('x-cloak');
            });
            if (force || open !== modalOpen) {
                modalOpen = open;
                send({type:'vita:modal-state',open});
            }
        };
        const scheduleModalCheck = () => {
            if (modalFrame !== null) return;
            modalFrame = requestAnimationFrame(() => { modalFrame = null; publishModalState(); });
        };
        new MutationObserver(scheduleModalCheck).observe(document.body, { subtree:true, childList:true, attributes:true,
            attributeFilter:['class','style','hidden','open','aria-hidden','x-cloak'] });
        window.addEventListener('resize',scheduleModalCheck);
        window.addEventListener('vita:content-visible', () => publishModalState(true));
        publishModalState(true);
        window.addEventListener('vita:content-visible', () => {
            const owner = document.querySelector('[data-vita-room-url]');
            const slot = owner?.querySelector('[data-vita-call-slot]');
            if (owner?.dataset.vitaRoomUrl && slot && getComputedStyle(slot).display !== 'none') window.VitaNavigation.openRoom(owner);
        });
        document.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (event.defaultPrevented || !link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target && link.target !== '_self' || link.hasAttribute('download')) return;
            const url = new URL(link.href,location.href);
            if (url.origin !== location.origin || url.searchParams.has('signature') || /\/export(?:\/|$)|\.(pdf|zip|xlsx?)$/i.test(url.pathname)) return;
            if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
            event.preventDefault(); window.parent.VitaModules.navigate(url.href);
        });
        document.addEventListener('submit',event => {
            if (event.defaultPrevented || event.target.getAttribute('target') && event.target.getAttribute('target') !== '_self') return;
            if (new URL(event.target.getAttribute('action') || '',location.href).pathname === '/logout') return;
            if (!window.parent.VitaModules.submit(event.target,event.submitter,event)) event.preventDefault();
        });
    }
    document.addEventListener('submit',event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (new URL(form.getAttribute('action') || '',location.href).pathname === '/logout') {
            if (state.joined && !confirm('Cerrar sesión desconectará tu videollamada. ¿Continuar?')) { event.preventDefault(); event.stopImmediatePropagation(); return; }
            form.setAttribute('target', '_top');
        }
    },true);
    document.addEventListener('click',event => {
        if (!state.joined || !event.target.closest('#clinic-branch-switcher [data-branch-id]')) return;
        event.preventDefault(); event.stopImmediatePropagation();
        window.Swal?.fire({icon:'info',title:'Videollamada activa',text:'Finaliza la llamada antes de cambiar de sede.'});
    },true);
})();
