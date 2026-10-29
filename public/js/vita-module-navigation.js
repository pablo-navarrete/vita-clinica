(() => {
    const slot = document.getElementById('vita-module-slot');
    if (!slot) return;
    let current = document.getElementById('vita-content-current'), pending = null;
    let sequence = 0;
    let modalLayout = null;
    const loading = document.getElementById('vita-module-loading');
    const showLoading = (error = null) => {
        loading.dataset.error = String(Boolean(error));
        const label = document.getElementById('vita-module-loading-label');
        if (label) label.textContent = error || 'Cargando...';
        loading.hidden = false;
    };
    const internalUrl = value => {
        const url = new URL(value,location.href);
        if (url.origin !== location.origin) return null;
        return url;
    };
    const contentUrl = value => { const url = internalUrl(value); if (url) { url.searchParams.set('_vita_content','1'); url.searchParams.delete('_vita_frame'); } return url; };
    const sync = (push = false) => {
        try {
            const url = new URL(current.contentWindow.location.href);
            if (url.origin !== location.origin || url.protocol === 'about:') return;
            window.VitaNavigation?.send({type:'vita:module-location',url:url.href,title:current.contentDocument.title,historyPush:push});
            const path = url.pathname;
            for (const link of document.querySelectorAll('aside nav a[href]')) {
                const selected = new URL(link.href).pathname === path;
                link.classList.toggle('bg-white/20',selected); link.classList.toggle('text-white',selected); link.classList.toggle('shadow-md',selected);
                if (selected) link.setAttribute('aria-current','page'); else link.removeAttribute('aria-current');
            }
        } catch (_) {}
    };
    const ensureContentLayout = frame => {
        try {
            const url = internalUrl(frame.contentWindow.location.href);
            if (!url || url.protocol === 'about:' || url.searchParams.has('signature') || ['/login','/logout'].includes(url.pathname)) return true;
            if (frame.contentDocument?.body?.dataset.vitaContent === 'false' && url.searchParams.get('_vita_content') !== '1') {
                // A legacy location.assign/href must not nest the application menu.
                frame.style.visibility = 'hidden';
                showLoading();
                frame.contentWindow.location.replace(contentUrl(url.href).href);
                return false;
            }
            if (frame === current) { frame.style.visibility = ''; loading.hidden = !pending; }
            return true;
        } catch (_) { return true; }
    };
    const canLeave = () => {
        try { return !current.contentWindow.vitaHasUnsavedChanges?.() || confirm('Tienes cambios sin guardar en esta atención. ¿Deseas salir?'); }
        catch (_) { return true; }
    };
    const prepare = push => {
        if (pending) pending.remove();
        const frame = document.createElement('iframe');
        frame.name = `vita-content-${++sequence}`; frame.title = 'Contenido del módulo';
        frame.style.cssText = 'border:0;position:absolute;inset:0;width:100%;height:100%;visibility:hidden;pointer-events:none;';
        pending = frame; showLoading(); slot.setAttribute('aria-busy','true');
        const activate = () => {
            if (!ensureContentLayout(frame)) return;
            if (current === frame) { sync(false); return; }
            if (pending !== frame) return;
            try {
                if (frame.contentWindow.location.href === 'about:blank') return;
                const doc = frame.contentDocument;
                // Redirects to login or non-module documents leave the shell deliberately.
                if (!doc?.body || doc.body.dataset.vitaContent !== 'true') {
                    const url = new URL(frame.contentWindow.location.href);
                    if (url.pathname === '/login') { window.top.location.assign(url.href); return; }
                    showLoading('No se pudo abrir este módulo. Intenta nuevamente.');
                    frame.remove(); pending = null; slot.removeAttribute('aria-busy'); return;
                }
                // Swap only the central document once it has finished loading.
                window.VitaModules.setModal(false);
                current.removeAttribute('id');
                const previous = current; current = frame; pending = null;
                current.id = 'vita-content-current'; current.style.cssText = 'border:0;width:100%;height:100%;display:block;';
                previous.remove(); loading.hidden = true; slot.removeAttribute('aria-busy');
                sync(push);
                current.contentWindow.dispatchEvent(new Event('vita:content-visible'));
                window.VitaNavigation?.requestState();
            } catch (_) {
                frame.remove(); pending = null; showLoading('No se pudo abrir el contenido.'); slot.removeAttribute('aria-busy');
            }
        };
        frame.vitaActivate = activate;
        frame.addEventListener('load', activate);
        slot.append(frame);
        return frame;
    };
    window.VitaModules = {
        contentReady(source) {
            if (pending?.contentWindow === source) pending.vitaActivate();
            else if (current.contentWindow === source) sync(false);
        },
        isContentWindow(source) { return source === current.contentWindow || source === pending?.contentWindow; },
        isCurrentWindow(source) { return source === current.contentWindow; },
        setModal(open) {
            if (open && current.dataset.modalFullscreen !== 'true') {
                const rect = current.getBoundingClientRect();
                const doc = current.contentDocument;
                const root = doc?.body?.firstElementChild;
                if (root && doc.head) {
                    const style = doc.createElement('style');
                    root.setAttribute('data-vita-modal-content', '');
                    style.textContent = `html,body{background:transparent!important;}body>[data-vita-modal-content]{position:fixed!important;left:${rect.left}px!important;top:${rect.top}px!important;width:${rect.width}px!important;height:${rect.height}px!important;min-height:0!important;}body>[data-vita-modal-content]>.h-screen{height:100%!important;}`;
                    doc.head.append(style);
                    modalLayout = {style,root};
                }
                current.dataset.normalStyle = current.style.cssText;
                current.dataset.modalFullscreen = 'true';
                current.style.cssText = 'position:fixed;inset:0;width:100vw;height:100dvh;border:0;display:block;z-index:80;background:transparent;';
            } else if (!open && current.dataset.modalFullscreen === 'true') {
                modalLayout?.style.remove();
                modalLayout?.root.removeAttribute('data-vita-modal-content');
                modalLayout = null;
                current.style.cssText = current.dataset.normalStyle || 'border:0;width:100%;height:100%;display:block;';
                delete current.dataset.modalFullscreen;
                delete current.dataset.normalStyle;
            }
        },
        currentUrl() {
            try { return current.contentWindow.location.href; }
            catch (_) { return current.src; }
        },
        refresh() {
            return this.navigate(this.currentUrl(), false);
        },
        navigate(value,push = true) {
            const url = contentUrl(value); if (!url || !canLeave()) return false;
            prepare(push).src = url.href;
            return true;
        },
        submit(form,submitter) {
            if (!canLeave()) return false;
            const action = contentUrl(new URL(form.getAttribute('action') || '', form.ownerDocument.baseURI || location.href).href);
            const frame = prepare(true);
            if (!action) { frame.remove(); return false; }
            form.setAttribute('action', action.href); form.setAttribute('target', frame.name);
            let marker = form.querySelector('input[name="_vita_content"]');
            if (!marker) { marker = form.ownerDocument.createElement('input'); marker.type = 'hidden'; marker.name = '_vita_content'; form.append(marker); }
            marker.value = '1';
            return true;
        },
        sync,
        sendState(data) { for (const frame of [current,pending]) frame?.contentWindow?.postMessage(data,location.origin); },
    };
    document.addEventListener('click',event => {
        const link = event.target.closest('a[href]');
        if (event.defaultPrevented || !link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target && link.target !== '_self' || link.hasAttribute('download')) return;
        const url = internalUrl(link.href); if (!url || url.pathname === '/logout' || url.searchParams.has('signature')) return;
        event.preventDefault(); window.VitaModules.navigate(url.href);
    });
    current.addEventListener('load',() => { if (ensureContentLayout(current) && current.id === 'vita-content-current') sync(false); });
    current.src = current.dataset.initialUrl;
})();
