(() => {
    const form = document.querySelector('[data-signature-configuration]');
    if (!form) return;
    const canvas = form.querySelector('[data-signature-canvas]');
    const context = canvas.getContext('2d');
    const save = form.querySelector('[data-signature-save]');
    const error = form.querySelector('[data-signature-error]');
    const urls = {};
    let pointer = null;
    let version = 0;
    let hasDrawing = false;
    const fileInput = kind => form.querySelector(`[data-signature-file="${kind}"]`);
    const removeInput = kind => form.querySelector(`[data-remove="${kind}"]`);
    const notify = message => { error.textContent = message; error.hidden = !message; };
    const preview = (kind, file) => {
        if (urls[kind]) URL.revokeObjectURL(urls[kind]);
        const image = form.querySelector(`[data-preview="${kind}"]`);
        image.hidden = !file;
        form.querySelector(`[data-empty="${kind}"]`).hidden = Boolean(file);
        if (file) { urls[kind] = URL.createObjectURL(file); image.src = urls[kind]; }
        else image.removeAttribute('src');
    };
    const clearDrawing = () => {
        version++;
        context.clearRect(0, 0, canvas.width, canvas.height);
        hasDrawing = false;
        save.disabled = false;
    };
    const point = event => {
        const rect = canvas.getBoundingClientRect();
        return [(event.clientX - rect.left) * canvas.width / rect.width,
            (event.clientY - rect.top) * canvas.height / rect.height];
    };
    const exportDrawing = () => {
        const current = ++version;
        save.disabled = true;
        canvas.toBlob(blob => {
            if (current !== version) return;
            try {
                if (!blob) throw new Error('No se pudo preparar el dibujo.');
                const transfer = new DataTransfer();
                const file = new File([blob], 'firma.png', { type: 'image/png' });
                transfer.items.add(file);
                fileInput('signature').files = transfer.files;
                removeInput('signature').checked = false;
                preview('signature', file);
                notify('');
            } catch (_) { notify('No se pudo preparar el dibujo. Puedes subir una imagen PNG o JPG.'); }
            finally { save.disabled = false; }
        }, 'image/png');
    };
    canvas.addEventListener('pointerdown', event => {
        if (pointer !== null || (event.pointerType === 'mouse' && event.button !== 0)) return;
        event.preventDefault();
        pointer = event.pointerId;
        canvas.setPointerCapture(pointer);
        save.disabled = true;
        version++;
        if (!hasDrawing) { context.clearRect(0, 0, canvas.width, canvas.height); hasDrawing = true; }
        const [x, y] = point(event);
        context.strokeStyle = '#172033';
        context.fillStyle = '#172033';
        context.lineWidth = 3;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.beginPath(); context.arc(x, y, 1.5, 0, Math.PI * 2); context.fill();
        context.beginPath(); context.moveTo(x, y);
    });
    canvas.addEventListener('pointermove', event => {
        if (event.pointerId !== pointer) return;
        context.lineTo(...point(event)); context.stroke();
    });
    const end = event => {
        if (event.pointerId !== pointer) return;
        pointer = null;
        exportDrawing();
    };
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', end);
    canvas.addEventListener('lostpointercapture', end);
    form.querySelector('[data-clear-canvas]').addEventListener('click', () => {
        clearDrawing(); fileInput('signature').value = '';
        // Restore the saved preview by reloading its original URL; clearing a drawing does not delete it.
        const image = form.querySelector('[data-preview="signature"]');
        const original = image.dataset.original;
        if (urls.signature) URL.revokeObjectURL(urls.signature);
        image.hidden = !original;
        form.querySelector('[data-empty="signature"]').hidden = Boolean(original);
        if (original) image.src = original;
        removeInput('signature').checked = false;
    });
    for (const kind of ['signature', 'stamp']) {
        const dropzone = form.querySelector(`[data-signature-dropzone="${kind}"]`);
        for (const eventName of ['dragenter', 'dragover']) {
            dropzone?.addEventListener(eventName, event => {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.add('bg-gray-50', 'border-gray-400');
            });
        }
        for (const eventName of ['dragleave', 'drop']) {
            dropzone?.addEventListener(eventName, event => {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('bg-gray-50', 'border-gray-400');
                if (eventName !== 'drop' || !event.dataTransfer?.files.length) return;
                const transfer = new DataTransfer();
                transfer.items.add(event.dataTransfer.files[0]);
                fileInput(kind).files = transfer.files;
                fileInput(kind).dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
        const image = form.querySelector(`[data-preview="${kind}"]`);
        image.dataset.original = image.getAttribute('src') || '';
        fileInput(kind).addEventListener('change', event => {
            const file = event.target.files[0];
            if (!file) return;
            if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                event.target.value = ''; notify('Selecciona una imagen PNG o JPG de hasta 2 MB.'); return;
            }
            if (kind === 'signature') clearDrawing();
            removeInput(kind).checked = false;
            preview(kind, file); notify('');
        });
        removeInput(kind).addEventListener('change', event => {
            if (event.target.checked) {
                fileInput(kind).value = '';
                if (kind === 'signature') clearDrawing();
                preview(kind, null);
            } else {
                image.hidden = !image.dataset.original;
                form.querySelector(`[data-empty="${kind}"]`).hidden = Boolean(image.dataset.original);
                if (image.dataset.original) image.src = image.dataset.original;
            }
        });
    }
    window.addEventListener('pagehide', () => Object.values(urls).forEach(url => URL.revokeObjectURL(url)));
})();
