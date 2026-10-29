(() => {
    const moduleFrame = document.getElementById('vita-module-frame');
    const callFrame = document.getElementById('vita-call-frame');
    const panel = document.getElementById('vita-persistent-call');
    const toolbar = document.getElementById('vita-call-toolbar');
    let room = null, joined = false, drag = null, noticeTimer, closingTimer;
    const sameOrigin = value => {
        try { const url = new URL(value, location.origin); return url.origin === location.origin ? url : null; }
        catch (_) { return null; }
    };
    const notify = message => {
        const notice = document.getElementById('vita-shell-notice');
        notice.textContent = message; notice.hidden = false;
        clearTimeout(noticeTimer); noticeTimer = setTimeout(() => notice.hidden = true, 4500);
    };
    const syncModuleLocation = () => {
        try {
            if (moduleFrame.contentWindow.VitaModules) return;
            const contentFrame = moduleFrame.contentDocument?.getElementById('vita-content-current');
            const url = sameOrigin(contentFrame?.contentWindow?.location?.href || moduleFrame.contentWindow.location.href);
            if (!url || url.href === 'about:blank') return;
            url.searchParams.delete('_vita_frame');
            url.searchParams.delete('_vita_content');
            if (url.pathname === '/login' || url.pathname === '/logout') {
                clearRoom(); location.replace(url.href); return;
            }
            if (url.href !== location.href) history.replaceState(history.state, '', url.href);
            const title = moduleFrame.contentDocument?.title;
            if (title) document.title = title;
        } catch (_) { /* A page from another origin cannot update the clinic URL. */ }
    };
    const broadcastState = () => moduleFrame.contentWindow?.postMessage({ type:'vita:call-state', sessionId:room?.sessionId || null, joined }, location.origin);
    const place = (x, y) => {
        const viewport = window.visualViewport;
        const left = viewport?.offsetLeft || 0, top = viewport?.offsetTop || 0;
        const width = viewport?.width || innerWidth, height = viewport?.height || innerHeight;
        const rect = panel.getBoundingClientRect();
        panel.style.setProperty('--call-x', `${Math.max(left + 8, Math.min(x, left + width - rect.width - 8))}px`);
        panel.style.setProperty('--call-y', `${Math.max(top + 8, Math.min(y, top + height - rect.height - 8))}px`);
    };
    const clamp = () => { const rect = panel.getBoundingClientRect(); place(rect.left,rect.top); };
    const clearRoom = () => {
        clearTimeout(closingTimer); joined = false; room = null; panel.hidden = true;
        document.getElementById('vita-call-timer').textContent = '';
        callFrame.src = 'about:blank'; broadcastState();
    };
    const closeRoom = () => {
        if (joined && !confirm('La videollamada sigue activa. Cerrar desconecta tu audio y video. ¿Continuar?')) return;
        if (!joined) { clearRoom(); return; }
        callFrame.contentWindow.postMessage({type:'vita:stop-call'},location.origin);
        closingTimer = setTimeout(clearRoom,4000);
    };
    window.addEventListener('message', event => {
        if (event.origin !== location.origin || !event.data || typeof event.data !== 'object') return;
        const data = event.data;
        if (event.source === moduleFrame.contentWindow) {
            if (data.type === 'vita:module-location') {
                const url = sameOrigin(data.url);
                if (!url) return;
                url.searchParams.delete('_vita_frame');
            url.searchParams.delete('_vita_content');
                if (url.pathname === '/login' || url.pathname === '/logout') { clearRoom(); location.replace(url); return; }
                if (url.href !== location.href) {
                    if (data.historyPush) history.pushState({},'',url);
                    else history.replaceState({},'',url);
                }
                if (typeof data.title === 'string') document.title = data.title;
                broadcastState();
            }
            if (data.type === 'vita:open-call') {
                const url = sameOrigin(data.url), recordUrl = sameOrigin(data.recordUrl);
                if (!url || !recordUrl || !/^\/telemedicine\/\d+$/.test(url.pathname) || url.searchParams.get('embedded') !== '1') return;
                const sessionId = String(data.sessionId);
                if (room && room.sessionId !== sessionId) {
                    notify('Ya tienes una sala abierta. Vuelve a esa atención o cierra la sala antes de abrir otra.'); broadcastState(); return;
                }
                if (!room) {
                    room = { sessionId, recordUrl:recordUrl.href };
                    document.getElementById('vita-call-patient').textContent = data.patient || 'Paciente';
                    panel.hidden = false; panel.dataset.minimized = 'false'; panel.dataset.expanded = 'false';
                    callFrame.src = url.href;
                    const rect = panel.getBoundingClientRect(); place(innerWidth - rect.width - 20,innerHeight - rect.height - 20);
                } else { panel.dataset.minimized = 'false'; clamp(); }
                broadcastState();
            }
            if (data.type === 'vita:request-call-state') broadcastState();
            if (data.type === 'vita:close-call') closeRoom();
        } else if (event.source === callFrame.contentWindow) {
            if (data.type === 'vita:room-state') { joined = Boolean(data.joined); broadcastState(); }
            if (data.type === 'vita:room-stopped') clearRoom();
            if (data.type === 'vita:room-clock' && typeof data.display === 'string') document.getElementById('vita-call-timer').textContent = data.display;
            if (data.type === 'vita:consultation-warning' && typeof data.message === 'string') notify(data.message);

        }
    });
    moduleFrame.addEventListener('load', () => {
        syncModuleLocation();
        broadcastState();
    });
    // Also covers modules without the bridge and History API updates inside the frame.
    window.setInterval(syncModuleLocation, 500);
    window.addEventListener('popstate',() => {
        moduleFrame.contentWindow.VitaModules?.navigate(location.href,false);
    });
    window.addEventListener('beforeunload', event => {
        if (!joined) return;
        event.preventDefault(); event.returnValue = '';
    });
    document.getElementById('vita-call-close').addEventListener('click',closeRoom);
    document.getElementById('vita-call-return').addEventListener('click',() => {
        if (!room) return;
        if (moduleFrame.contentWindow.VitaModules) moduleFrame.contentWindow.VitaModules.navigate(room.recordUrl);
        else moduleFrame.src = room.recordUrl;
    });
    document.getElementById('vita-call-minimize').addEventListener('click',() => {
        const mini = panel.dataset.minimized !== 'true';
        panel.dataset.expanded = 'false';
        panel.dataset.minimized = String(mini);
        document.getElementById('vita-call-minimize').setAttribute('aria-label',mini ? 'Restaurar videollamada' : 'Minimizar videollamada'); clamp();
    });
    document.getElementById('vita-call-expand').addEventListener('click',() => {
        panel.dataset.minimized = 'false'; panel.dataset.expanded = String(panel.dataset.expanded !== 'true');
        document.getElementById('vita-call-minimize').setAttribute('aria-label','Minimizar videollamada');
        clamp();
    });
    toolbar.addEventListener('pointerdown',event => {
        if (event.button !== 0 || event.target.closest('button')) return;
        const rect = panel.getBoundingClientRect();
        drag = { id:event.pointerId,x:event.clientX,y:event.clientY,left:rect.left,top:rect.top };
        toolbar.setPointerCapture(event.pointerId); panel.dataset.dragging = 'true'; event.preventDefault();
    });
    toolbar.addEventListener('pointermove',event => { if (drag && drag.id === event.pointerId) place(drag.left + event.clientX - drag.x,drag.top + event.clientY - drag.y); });
    const stopDrag = () => {
        const id = drag?.id; drag = null; delete panel.dataset.dragging;
        if (id !== undefined && toolbar.hasPointerCapture(id)) toolbar.releasePointerCapture(id);
    };
    for (const type of ['pointerup','pointercancel','lostpointercapture']) toolbar.addEventListener(type,stopDrag);
    document.getElementById('vita-call-handle').addEventListener('keydown',event => {
        const direction = {ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowUp:[0,-1],ArrowDown:[0,1]}[event.key];
        if (!direction) return;
        event.preventDefault(); const rect = panel.getBoundingClientRect(),step = event.shiftKey ? 40 : 10;
        place(rect.left + direction[0] * step,rect.top + direction[1] * step);
    });
    const setSize = (width,height) => {
        const viewport = window.visualViewport;
        const maxWidth = (viewport?.width || innerWidth) - 24;
        const maxHeight = (viewport?.height || innerHeight) - 24;
        const size = { width:Math.max(Math.min(240,maxWidth),Math.min(width,maxWidth)),
            height:Math.max(Math.min(260,maxHeight),Math.min(height,maxHeight)) };
        panel.style.setProperty('--call-width',`${size.width}px`);
        panel.style.setProperty('--call-height',`${size.height}px`);
        panel.dataset.expanded = 'false';
        return size;
    };
    for (const edge of ['se','nw']) {
        const grip = document.getElementById(`vita-call-resize-${edge}`);
        let resize = null;
        grip.addEventListener('pointerdown',event => {
            if (event.button !== 0 || panel.dataset.minimized === 'true') return;
            const rect = panel.getBoundingClientRect();
            resize = {id:event.pointerId,x:event.clientX,y:event.clientY,left:rect.left,top:rect.top,width:rect.width,height:rect.height};
            setSize(rect.width,rect.height);
            grip.setPointerCapture(event.pointerId); panel.dataset.resizing = 'true';
            event.preventDefault(); event.stopPropagation();
        });
        grip.addEventListener('pointermove',event => {
            if (!resize || resize.id !== event.pointerId) return;
            const sign = edge === 'nw' ? -1 : 1;
            const size = setSize(resize.width + (event.clientX - resize.x) * sign,resize.height + (event.clientY - resize.y) * sign);
            place(edge === 'nw' ? resize.left + resize.width - size.width : resize.left,
                edge === 'nw' ? resize.top + resize.height - size.height : resize.top);
        });
        const stopResize = () => {
            const id = resize?.id; resize = null; delete panel.dataset.resizing;
            if (id !== undefined && grip.hasPointerCapture(id)) grip.releasePointerCapture(id);
        };
        for (const type of ['pointerup','pointercancel','lostpointercapture']) grip.addEventListener(type,stopResize);
        grip.addEventListener('keydown',event => {
            const direction = {ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowUp:[0,-1],ArrowDown:[0,1]}[event.key];
            if (!direction) return;
            event.preventDefault(); const rect = panel.getBoundingClientRect(),step = event.shiftKey ? 40 : 10;
            setSize(rect.width + direction[0] * step,rect.height + direction[1] * step); clamp();
        });
    }
    window.addEventListener('resize',clamp);
    window.visualViewport?.addEventListener('resize',clamp);
})();
