(() => {
    const button = document.getElementById('directory-nearby');
    const status = document.getElementById('directory-location-status');
    if (!button || !status) return;
    button.addEventListener('click', () => {
        if (!navigator.geolocation || !window.isSecureContext) {
            status.textContent = 'Tu navegador requiere HTTPS o localhost para usar la ubicación. Puedes buscar por ciudad o dirección.';
            return;
        }
        button.disabled = true;
        status.textContent = 'Esperando permiso de ubicación…';
        navigator.geolocation.getCurrentPosition(async position => {
            try {
                const response = await fetch(button.dataset.url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ latitude: Number(position.coords.latitude.toFixed(3)), longitude: Number(position.coords.longitude.toFixed(3)) }),
                });
                if (!response.ok) throw new Error();
                const url = new URL(window.location.href);
                url.searchParams.set('cercanas', '1');
                url.searchParams.delete('page');
                window.location.assign(url.toString());
            } catch (_) {
                status.textContent = 'No se pudo consultar la ubicación. Intenta de nuevo o busca por ciudad.';
                button.disabled = false;
            }
        }, error => {
            status.textContent = error.code === 1
                ? 'No autorizaste la ubicación. Puedes seguir buscando por ciudad o dirección.'
                : 'No pudimos obtener tu ubicación. Intenta de nuevo o busca por ciudad.';
            button.disabled = false;
        }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
    });
})();
