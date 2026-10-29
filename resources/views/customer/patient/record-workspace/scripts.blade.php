@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.getElementById('clinical-workspace');
    if (!workspace) return;
    const recordToast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 4000, timerProgressBar: true,
    });
    window.showClinicalRecordToast = (message, icon = 'error') => recordToast.fire({ icon, titleText: message, timer: icon === 'error' ? 6000 : 4000 });
    const showRecordMessages = async (container, initial = false) => {
        for (const message of container.querySelectorAll('[data-record-toast]')) {
            const icon = message.dataset.recordToast;
            // The layout already displays initial success/error flash messages.
            if (initial && icon !== 'info' && !message.hasAttribute('data-record-validation')) continue;
            await recordToast.fire({ icon, titleText: message.textContent.trim(), timer: icon === 'error' ? 6000 : 4000 });
        }
    };
    showRecordMessages(document.getElementById('clinical-notices'), true);
    const fitWorkspace = () => {
        const viewport = window.visualViewport;
        const bottom = (viewport?.height ?? window.innerHeight) + (viewport?.offsetTop ?? 0);
        const minimumHeight = window.matchMedia('(min-width: 768px)').matches ? 780 : 660;
        workspace.style.setProperty('--clinical-workspace-height', `${Math.max(minimumHeight, bottom - workspace.getBoundingClientRect().top - 16)}px`);
    };
    requestAnimationFrame(fitWorkspace);
    window.addEventListener('resize', fitWorkspace);
    window.visualViewport?.addEventListener('resize', fitWorkspace);
    new ResizeObserver(() => requestAnimationFrame(fitWorkspace)).observe(document.getElementById('clinical-notices'));

    const floatingVideo = workspace.querySelector('.clinical-video');
    if (floatingVideo && !window.VitaNavigation?.framed) {
        const toolbar = floatingVideo.querySelector('.clinical-video-toolbar');
        const handle = floatingVideo.querySelector('.clinical-video-drag-handle');
        let position = null;
        let drag = null;
        const bounds = () => {
            const viewport = window.visualViewport;
            return { left: (viewport?.offsetLeft ?? 0) + 8, top: (viewport?.offsetTop ?? 0) + 8,
                right: (viewport?.offsetLeft ?? 0) + (viewport?.width ?? window.innerWidth) - 8,
                bottom: (viewport?.offsetTop ?? 0) + (viewport?.height ?? window.innerHeight) - 8 };
        };
        const placeVideo = (x, y) => {
            const area = bounds();
            const rect = floatingVideo.getBoundingClientRect();
            position = { x: Math.max(area.left, Math.min(x, area.right - rect.width)),
                y: Math.max(area.top, Math.min(y, area.bottom - rect.height)) };
            floatingVideo.style.setProperty('--video-x', `${position.x}px`);
            floatingVideo.style.setProperty('--video-y', `${position.y}px`);
        };
        const finishDrag = () => {
            if (drag && toolbar.hasPointerCapture(drag.id)) toolbar.releasePointerCapture(drag.id);
            drag = null;
            delete floatingVideo.dataset.dragging;
        };
        const syncFloatingVideo = () => {
            const floating = workspace.dataset.videoMode === 'mini' ||
                (window.matchMedia('(max-width: 767px)').matches && workspace.dataset.mobilePanel === 'record' &&
                    workspace.dataset.videoMode === 'normal' && workspace.dataset.videoLoaded === 'true');
            floatingVideo.dataset.floating = String(floating);
            handle.tabIndex = floating ? 0 : -1;
            if (floating) {
                // The top layer escapes scrolling panels without recreating the call iframe.
                if (typeof floatingVideo.showPopover === 'function') {
                    floatingVideo.setAttribute('popover', 'manual');
                    if (!floatingVideo.matches(':popover-open')) floatingVideo.showPopover();
                }
                const area = bounds();
                const rect = floatingVideo.getBoundingClientRect();
                placeVideo(position?.x ?? area.right - rect.width, position?.y ?? area.bottom - rect.height);
            } else {
                finishDrag();
                if (floatingVideo.hasAttribute('popover')) {
                    floatingVideo.hidePopover();
                    floatingVideo.removeAttribute('popover');
                }
            }
        };
        toolbar.addEventListener('pointerdown', event => {
            if (floatingVideo.dataset.floating !== 'true' || event.button !== 0 || event.target.closest('button,a,input')) return;
            const rect = floatingVideo.getBoundingClientRect();
            drag = { id: event.pointerId, x: event.clientX, y: event.clientY, left: rect.left, top: rect.top };
            toolbar.setPointerCapture(event.pointerId);
            floatingVideo.dataset.dragging = 'true';
            event.preventDefault();
        });
        toolbar.addEventListener('pointermove', event => {
            if (!drag || drag.id !== event.pointerId) return;
            placeVideo(drag.left + event.clientX - drag.x, drag.top + event.clientY - drag.y);
        });
        for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) toolbar.addEventListener(type, finishDrag);
        handle.addEventListener('keydown', event => {
            if (floatingVideo.dataset.floating !== 'true') return;
            const direction = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[event.key];
            if (!direction) return;
            event.preventDefault();
            const step = event.shiftKey ? 40 : 10;
            placeVideo(position.x + direction[0] * step, position.y + direction[1] * step);
        });
        new MutationObserver(syncFloatingVideo).observe(workspace, { attributes: true,
            attributeFilter: ['data-video-mode', 'data-mobile-panel', 'data-video-loaded'] });
        new ResizeObserver(() => {
            if (floatingVideo.dataset.floating === 'true' && position) placeVideo(position.x, position.y);
        }).observe(floatingVideo);
        window.addEventListener('resize', syncFloatingVideo);
        window.visualViewport?.addEventListener('resize', syncFloatingVideo);
        window.visualViewport?.addEventListener('scroll', syncFloatingVideo);
        syncFloatingVideo();
    }

    let dirty = false;
    let leaving = false;
    window.vitaHasUnsavedChanges = () => dirty && !leaving;
    document.addEventListener('input', event => {
        if (event.target.matches('#encounter-assessment, #encounter-treatment-plan')) dirty = true;
    });
    window.addEventListener('beforeunload', event => {
        if (!dirty || leaving) return;
        event.preventDefault();
        event.returnValue = '';
    });
    const modalIds = ['modalAllergies', 'modalChronicDiseases', 'modalRegularMedications', 'modalPreviousSurgeries'];
    const listIds = ['allergyList', 'chronicDiseasesList', 'regularMedicationsList', 'previousSurgeriesList'];
    let saving = false;
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const modal = modalIds.map(id => document.getElementById(id)).find(element => element?.contains(form));
        const observation = form.closest('#encounter-history') && form.method.toLowerCase() === 'post';
        const upload = form.contains(document.getElementById('encounter-document-input'));
        if (!modal && !observation && !upload) {
            if (form.closest('#current-encounter-section') && form.method.toLowerCase() === 'post') leaving = true;
            return;
        }
        event.preventDefault();
        if (saving) return;
        saving = true;
        const buttons = [...form.querySelectorAll('button[type="submit"]')];
        buttons.forEach(button => button.disabled = true);
        const notices = document.getElementById('clinical-notices');
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: new FormData(form),
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = parsed.getElementById('clinical-record-content');
            const returnedNotices = parsed.getElementById('clinical-notices');
            if (!response.ok || !updated || !returnedNotices) throw new Error('No se pudo actualizar la ficha. Tu registro de atención sigue en pantalla.');
            notices.innerHTML = returnedNotices.innerHTML;
            showRecordMessages(returnedNotices);
            if (returnedNotices.querySelector('[data-record-errors]')) {
                if (modal) modal.querySelector('input:not([type="hidden"]), textarea')?.focus();
                return;
            }
            const content = document.getElementById('clinical-record-content');
            const scrollTop = content.scrollTop;
            content.innerHTML = updated.innerHTML;
            content.scrollTop = scrollTop;
            const attentionDocuments = document.getElementById('attention-document-list');
            const nextDocuments = parsed.getElementById('attention-document-list');
            if (attentionDocuments && nextDocuments) attentionDocuments.innerHTML = nextDocuments.innerHTML;
            listIds.forEach(id => {
                const current = document.getElementById(id);
                const next = parsed.getElementById(id);
                if (current && next) current.innerHTML = next.innerHTML;
            });
            if (modal) {
                form.reset();
                modal.classList.add('hidden');
            }
            if (upload) {
                form.reset();
                document.getElementById('encounter-document-input')?.dispatchEvent(new Event('change', { bubbles: true }));
            }
            window.dispatchEvent(new CustomEvent('clinical-record-refreshed'));
        } catch (error) {
            notices.replaceChildren();
            recordToast.fire({ icon: 'error', titleText: error.message || 'No se pudo guardar. Intenta nuevamente.', timer: 6000 });
        } finally {
            saving = false;
            buttons.forEach(button => button.disabled = false);
            fitWorkspace();
        }
    });
});
</script>
@endpush
