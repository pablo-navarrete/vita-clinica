(() => {
    if (!document.getElementById('portal-sidebar')) return;
    let pending, saving = false;
    const supported = url => url.origin === location.origin && /^\/mi-salud\/(panel|perfil|mis-atenciones|agendar)(\/|$)/.test(url.pathname)
        && !/\/documentos\//.test(url.pathname);
    async function navigate(url, {push = true, form = null, submitter = null, filter = false} = {}) {
        if (saving) return;
        pending?.abort(); pending = new AbortController();
        const controller = pending;
        const main = document.getElementById('contenido');
        const loading = document.getElementById('portal-content-loading');
        if (loading) loading.hidden = false;
        main.setAttribute('aria-busy', 'true');
        const options = {signal: controller.signal, headers: {Accept: 'text/html'}, credentials: 'same-origin', cache: 'no-store'};
        if (filter) options.headers['X-Mi-Salud-Summary'] = '1';
        if (form) {
            saving = true;
            options.method = form.method.toUpperCase();
            options.body = new FormData(form);
            if (submitter?.name) options.body.append(submitter.name, submitter.value);
        }
        try {
            const response = await fetch(url, options);
            const destination = new URL(response.url);
            destination.hash = new URL(url).hash;
            if (!supported(destination)) { location.assign(destination.href); return; }
            if (!response.ok && response.status !== 422) throw new Error('No se pudo cargar el contenido.');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (filter && response.headers.get('X-Mi-Salud-Summary') === '1') {
                const results = page.querySelector('[data-attention-results]');
                const current = main.querySelector('[data-attention-results]');
                if (!results || !current) throw new Error('No se pudo actualizar el resumen.');
                window.PortalCharts?.dispose(current);
                current.replaceChildren(...results.childNodes);
                window.PortalCharts?.mount(current);
                main.querySelector('[data-portal-load-error]')?.remove();
                if (location.href !== destination.href) history.replaceState({}, '', destination.href);
                return;
            }
            const content = page.getElementById('contenido');
            if (!content || !page.getElementById('portal-sidebar')) { location.assign(destination.href); return; }
            window.PortalCharts?.dispose(main);
            main.replaceChildren(...content.childNodes);
            window.PortalCharts?.mount(main);
            document.title = page.title;
            document.querySelectorAll('meta[name="csrf-token"]').forEach(meta => {
                const token = page.querySelector('meta[name="csrf-token"]');
                if (token) meta.content = token.content;
            });
            const method = push ? 'pushState' : 'replaceState';
            if (location.href !== destination.href) history[method]({}, '', destination.href);
            document.querySelectorAll('#portal-sidebar nav a').forEach(link => {
                if (new URL(link.href).pathname === destination.pathname ||
                    (link.pathname.endsWith('/mis-atenciones') && destination.pathname.startsWith(link.pathname + '/'))) link.setAttribute('aria-current', 'page');
                else link.removeAttribute('aria-current');
            });
            document.querySelectorAll('.portal-sidebar-preview').forEach(preview => { preview.hidden = true; });
            document.querySelectorAll('.portal-account-menu').forEach(menu => { menu.open = false; });
            window.initializePortalContent?.();
            if (!filter) {
                main.focus({preventScroll:true});
                const anchor = destination.hash && document.getElementById(decodeURIComponent(destination.hash.slice(1)));
                if (anchor) anchor.scrollIntoView();
                else document.querySelector('.vita-workspace-scroll')?.scrollTo(0, 0);
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (!form && !filter) { location.assign(url); return; }
            // Never automatically repeat a write after an uncertain network result.
            const notice = document.createElement('div'); notice.className = 'notice error'; notice.setAttribute('role', 'alert');
            notice.dataset.portalLoadError = '';
            main.querySelector('[data-portal-load-error]')?.remove();
            notice.textContent = filter ? 'No se pudo actualizar el resumen. Vuelve a seleccionar el período para intentarlo de nuevo.'
                : 'No se pudo confirmar el resultado. Revisa tus datos antes de volver a guardar.';
            main.prepend(notice); notice.scrollIntoView();
        } finally {
            if (pending === controller) {
                main.removeAttribute('aria-busy');
                if (loading) loading.hidden = true;
            }
            if (form) saving = false;
        }
    }
    function useLocation(button) {
        const status = document.getElementById('portal-booking-location-status');
        if (!status) return;
        if (!navigator.geolocation || !window.isSecureContext) {
            status.textContent = 'Para usar la ubicación, abre VITA con HTTPS y un navegador compatible. También puedes buscar por comuna o ciudad.';
            return;
        }
        button.disabled = true;
        status.textContent = 'Esperando permiso de ubicación…';
        navigator.geolocation.getCurrentPosition(async position => {
            if (!button.isConnected) return;
            try {
                const response = await fetch(button.dataset.url, {
                    method:'POST', credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/json',
                        'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},
                    body:JSON.stringify({latitude:Number(position.coords.latitude.toFixed(3)),longitude:Number(position.coords.longitude.toFixed(3))}),
                });
                if (!response.ok) throw new Error();
                if (!button.isConnected) return;
                const url = new URL(button.closest('form').action);
                url.search = new URLSearchParams(new FormData(button.closest('form'))).toString();
                url.searchParams.set('cercanas','1'); url.searchParams.delete('page');
                await navigate(url.href);
            } catch (_) {
                status.textContent = 'No pudimos usar tu ubicación. Intenta nuevamente o busca por comuna o ciudad.';
            } finally { button.disabled = false; }
        }, error => {
            status.textContent = error.code === 1 ? 'No autorizaste la ubicación. Puedes seguir buscando por comuna o ciudad.'
                : 'No pudimos obtener tu ubicación. Intenta nuevamente o busca por comuna o ciudad.';
            button.disabled = false;
        }, {enableHighAccuracy:false,timeout:10000,maximumAge:300000});
    }
    document.addEventListener('click', event => {
        const locationButton = event.target.closest('[data-portal-location]');
        if (locationButton) { event.preventDefault(); useLocation(locationButton); return; }
        const link = event.target.closest('a[href]');
        if (event.defaultPrevented || !link || event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
        const url = new URL(link.href);
        if (!supported(url) || (url.pathname === location.pathname && url.search === location.search && url.hash)) return;
        event.preventDefault(); navigate(url.href);
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.target) return;
        const url = new URL(form.action);
        if (!supported(url)) return;
        event.preventDefault();
        if (form.method.toLowerCase() === 'get') {
            url.search = new URLSearchParams(new FormData(form)).toString();
            navigate(url.href, {push: !form.matches('[data-attention-filters]')});
        } else navigate(url.href, {form, submitter:event.submitter});
    });
    document.addEventListener('change', event => {
        const form = event.target.closest('[data-attention-filters]');
        if (!form) return;
        const url = new URL(form.action); url.search = new URLSearchParams(new FormData(form)).toString();
        navigate(url.href, {push:false, filter:event.target.name});
    });
    window.addEventListener('popstate', () => {
        if (supported(new URL(location.href))) navigate(location.href, {push:false});
        else location.reload();
    });
})();
