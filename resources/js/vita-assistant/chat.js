import { VitaVoice } from './voice.js';

const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
}[character]));

export function mountAssistant(openInitially = false) {
    const root = document.querySelector('[data-vita-assistant]');
    if (!root || root.dataset.initialized === 'true') return;
    const avatarVisibilityKey = `vita-assistant-avatar-hidden:${window.location.hostname}:${root.dataset.persistConversation || 'guest'}`;
    try {
        if (openInitially) {
            window.sessionStorage.removeItem(avatarVisibilityKey);
            root.classList.remove('is-avatar-hidden');
        }
        else if (window.sessionStorage.getItem(avatarVisibilityKey) === '1') root.classList.add('is-avatar-hidden');
    } catch (_) {}
    root.dataset.initialized = 'true';
    delete root.dataset.loading;
    root.classList.add('vita-assistant--loaded');

    const toggle = root.querySelector('[data-assistant-toggle]');
    const panel = root.querySelector('[data-assistant-panel]');
    const form = root.querySelector('form');
    const input = root.querySelector('textarea');
    const phoneField = root.querySelector('[data-assistant-phone-field]');
    const phoneInput = root.querySelector('[data-assistant-phone]');
    const messages = root.querySelector('[data-messages]');
    const sendButton = root.querySelector('[data-send]');
    const micButton = root.querySelector('[data-mic]');
    const voiceButton = root.querySelector('[data-voice]');
    const status = root.querySelector('[data-assistant-status]');
    const connectedStatus = root.dataset.connectedStatus || 'Modo demostración · No consulta información de pacientes';
    status.textContent = connectedStatus;
    const robot = { state: 'idle', setState(state) { this.state = state; root.dataset.vitiState = state; }, dispose() {} };
    let opened = false;
    let voiceEnabled = false;
    let busy = false;
    let greeted = false;
    let dragStart = null;
    let movedAssistant = false;
    let ignoreNextToggleClick = false;
    let interactionTimer;

    const assistantPositionKey = 'vita-assistant-position';
    const isPublicAssistant = root.dataset.persistConversation === 'public';
    const conversationStorageKey = isPublicAssistant
        ? `vita-public-conversation:${window.location.hostname}`
        : (root.dataset.persistConversation
            ? `vita-admin-conversation:${window.location.hostname}:${root.dataset.persistConversation}`
            : null);
    const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max));

    function assistantPositionStorage() {
        return isPublicAssistant ? window.sessionStorage : window.localStorage;
    }

    function persistConversation() {
        if (!conversationStorageKey) return;
        try {
            const history = [...messages.children]
                .filter(item => !item.classList.contains('vita-message--typing'))
                .map(item => ({
                    text: item.dataset.message || '',
                    sender: item.dataset.sender || 'assistant',
                    label: item.dataset.label || '',
                    actions: JSON.parse(item.dataset.actions || '[]'),
                }))
                .slice(-80);
            window.sessionStorage.setItem(conversationStorageKey, JSON.stringify(history));
        } catch (_) {}
    }

    function keepAssistantInViewport(left, top) {
        const bounds = root.getBoundingClientRect();
        const nextLeft = clamp(left, 8, window.innerWidth - bounds.width - 8);
        const nextTop = clamp(top, 8, window.innerHeight - bounds.height - 8);
        root.style.left = `${nextLeft}px`;
        root.style.top = `${nextTop}px`;
        root.style.right = 'auto';
        root.style.bottom = 'auto';
        return { left: nextLeft, top: nextTop };
    }

    function placePanelNearAssistant() {
        if (!opened) return;
        const anchor = toggle.getBoundingClientRect();
        const panelBounds = panel.getBoundingClientRect();
        const roomLeft = anchor.left - 16;
        const roomRight = window.innerWidth - anchor.right - 16;
        const side = roomRight >= roomLeft ? 'right' : 'left';
        const sideSpace = side === 'right' ? roomRight : roomLeft;
        const canSitBeside = sideSpace >= Math.min(320, window.innerWidth * 0.62);
        const panelWidth = canSitBeside
            ? Math.min(410, window.innerWidth - 28, sideSpace)
            : Math.min(410, window.innerWidth - 28);
        panel.style.width = `${panelWidth}px`;
        const measuredHeight = panel.getBoundingClientRect().height;
        const left = canSitBeside
            ? (side === 'right' ? anchor.right + 16 : anchor.left - panelWidth - 16)
            : clamp(anchor.left + anchor.width - panelWidth, 8, window.innerWidth - panelWidth - 8);
        const top = canSitBeside
            ? clamp(anchor.bottom - measuredHeight, 8, window.innerHeight - measuredHeight - 8)
            : (anchor.top - 12 >= measuredHeight
                ? anchor.top - measuredHeight - 12
                : clamp(anchor.bottom + 12, 8, window.innerHeight - measuredHeight - 8));
        panel.style.left = `${clamp(left, 8, window.innerWidth - panelWidth - 8)}px`;
        panel.style.top = `${top}px`;
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
    }

    try {
        if (isPublicAssistant) window.localStorage.removeItem(assistantPositionKey);
        const savedPosition = JSON.parse(assistantPositionStorage().getItem(assistantPositionKey) || 'null');
        if (Number.isFinite(savedPosition?.left) && Number.isFinite(savedPosition?.top)) {
            window.requestAnimationFrame(() => keepAssistantInViewport(savedPosition.left, savedPosition.top));
        }
    } catch (_) {
        // Si el navegador bloquea localStorage, Viti sigue funcionando en su posición inicial.
    }

    toggle.addEventListener('pointerdown', event => {
        if (event.button !== 0) return;
        const bounds = root.getBoundingClientRect();
        dragStart = { pointerId: event.pointerId, x: event.clientX, y: event.clientY, left: bounds.left, top: bounds.top };
        movedAssistant = false;
        toggle.setPointerCapture?.(event.pointerId);
    });

    toggle.addEventListener('pointermove', event => {
        if (!dragStart || dragStart.pointerId !== event.pointerId) return;
        const deltaX = event.clientX - dragStart.x;
        const deltaY = event.clientY - dragStart.y;
        if (!movedAssistant && Math.hypot(deltaX, deltaY) < 6) return;
        movedAssistant = true;
        root.classList.add('is-dragging');
        document.body.classList.add('viti-avatar-dragging');
        const trash = document.querySelector('[data-viti-trash]');
        if (trash) {
            const bounds = trash.getBoundingClientRect();
            trash.classList.toggle('is-drop-target', event.clientX >= bounds.left && event.clientX <= bounds.right && event.clientY >= bounds.top && event.clientY <= bounds.bottom);
        }
        event.preventDefault();
        const position = keepAssistantInViewport(dragStart.left + deltaX, dragStart.top + deltaY);
        if (opened) placePanelNearAssistant();
        try { assistantPositionStorage().setItem(assistantPositionKey, JSON.stringify(position)); } catch (_) {}
    });

    function finishAssistantDrag(event) {
        if (!dragStart || (event && dragStart.pointerId !== event.pointerId)) return;
        if (movedAssistant) {
            const trash = document.querySelector('[data-viti-trash]');
            const trashBounds = trash?.getBoundingClientRect();
            const droppedInTrash = Boolean(trashBounds && event && event.clientX >= trashBounds.left && event.clientX <= trashBounds.right && event.clientY >= trashBounds.top && event.clientY <= trashBounds.bottom);
            document.body.classList.remove('viti-avatar-dragging');
            trash?.classList.remove('is-drop-target');
            root.classList.remove('is-dragging');
            if (droppedInTrash) {
                setOpen(false);
                root.style.left = '';
                root.style.top = '';
                root.style.right = '';
                root.style.bottom = '';
                try { assistantPositionStorage().removeItem(assistantPositionKey); } catch (_) {}
                root.classList.add('is-avatar-hidden');
                try { window.sessionStorage.setItem(avatarVisibilityKey, '1'); } catch (_) {}
                document.querySelector('[data-viti-nav-toggle]')?.focus();
                ignoreNextToggleClick = true;
                window.setTimeout(() => { ignoreNextToggleClick = false; }, 0);
                dragStart = null;
                movedAssistant = false;
                return;
            }
            root.classList.add('is-dropping');
            window.clearTimeout(interactionTimer);
            interactionTimer = window.setTimeout(() => root.classList.remove('is-dropping'), 500);
            ignoreNextToggleClick = true;
            window.setTimeout(() => { ignoreNextToggleClick = false; }, 0);
        }
        dragStart = null;
        movedAssistant = false;
        document.body.classList.remove('viti-avatar-dragging');
        document.querySelector('[data-viti-trash]')?.classList.remove('is-drop-target');
    }

    toggle.addEventListener('pointerup', finishAssistantDrag);
    toggle.addEventListener('pointercancel', finishAssistantDrag);
    window.addEventListener('resize', () => {
        if (root.style.left && root.style.top) {
            const bounds = root.getBoundingClientRect();
            keepAssistantInViewport(bounds.left, bounds.top);
        }
        placePanelNearAssistant();
    });

    const voice = new VitaVoice({
        onState: state => {
            robot.setState(state);
            const labels = { listening: 'Viti te está escuchando…', thinking: 'Viti está pensando…', talking: 'Viti te está respondiendo…', idle: connectedStatus };
            status.textContent = labels[state] || status.textContent;
        },
        onTranscript: text => {
            input.value = `${input.value}${input.value ? ' ' : ''}${text}`;
            if (phoneInput && !phoneInput.disabled) phoneInput.value = input.value;
            input.focus();
        },
        onError: text => announce(text),
    });

    function announce(text) {
        status.textContent = text;
        window.setTimeout(() => {
            if (status.textContent === text) status.textContent = connectedStatus;
        }, 5000);
    }

    function scrollMessages() {
        messages.scrollTop = messages.scrollHeight;
    }

    function addMessage(text, sender = 'assistant', label = '', actions = [], persist = true) {
        const item = document.createElement('article');
        item.className = `vita-message vita-message--${sender}`;
        item.setAttribute('role', 'listitem');
        item.dataset.message = String(text);
        item.dataset.sender = sender;
        item.dataset.label = label;
        item.dataset.actions = JSON.stringify(actions);
        item.innerHTML = `${label ? `<span class="vita-message__label">${escapeHtml(label)}</span>` : ''}<p>${escapeHtml(text).replace(/\\n|\n/g, '<br>')}</p>`;
        if (Array.isArray(actions) && actions.length) {
            const actionList = document.createElement('div');
            actionList.className = 'vita-message__actions';
            actions.forEach(action => {
                try {
                    const target = new URL(String(action.url || ''), window.location.origin);
                    if (target.origin !== window.location.origin) return;
                    const link = document.createElement('a');
                    link.className = 'vita-message__action';
                    link.href = target.href;
                    link.textContent = String(action.label || 'Abrir');
                    actionList.appendChild(link);
                } catch (_) {}
            });
            if (actionList.childElementCount) item.appendChild(actionList);
        }
        messages.appendChild(item);
        if (persist) persistConversation();
        scrollMessages();
        return item;
    }

    function restoreConversation() {
        if (!conversationStorageKey) return false;
        try {
            const history = JSON.parse(window.sessionStorage.getItem(conversationStorageKey) || '[]');
            if (!Array.isArray(history) || history.length === 0) return false;
            history.slice(-80).forEach(message => {
                if (typeof message?.text !== 'string') return;
                addMessage(
                    message.text,
                    message.sender === 'user' ? 'user' : 'assistant',
                    String(message.label || ''),
                    Array.isArray(message.actions) ? message.actions : [],
                    false
                );
            });
            greeted = true;
            return messages.childElementCount > 0;
        } catch (_) {
            return false;
        }
    }

    function setOpen(next) {
        opened = Boolean(next);
        panel.hidden = !opened;
        toggle.setAttribute('aria-expanded', String(opened));
        root.classList.toggle('is-open', opened);
        toggle.classList.toggle('is-open', opened);
        if (opened) {
            placePanelNearAssistant();
            input.focus();
            if (!greeted) {
                greeted = true;
                robot.setState('greeting');
                window.setTimeout(() => {
                    if (robot.state === 'greeting') robot.setState('idle');
                }, 1800);
            }
        } else {
            voice.stopSpeaking();
            robot.setState('idle');
            toggle.focus();
        }
    }

    function demoReply(prompt) {
        const query = prompt.toLocaleLowerCase('es-CL');
        if (/paciente|rut|diagn[oó]stic|ficha|historial|cl[ií]nic/.test(query)) {
            return 'En esta etapa estoy en modo demostración. No consulto ni proceso fichas, diagnósticos, RUT ni datos reales de pacientes.';
        }
        if (/cita|agenda|hora|telemedicina/.test(query)) {
            return 'Puedo orientarte sobre cómo usar VITA, pero esta demostración no consulta la agenda ni confirma citas. Para gestionar una cita, usa el módulo Agenda.';
        }
        if (/hola|buenos d[ií]as|buenas tardes|buenas noches/.test(query)) {
            robot.setState('happy');
            return '¡Hola! Soy Viti, el robotito de VITA. Estoy en modo demostración, pero feliz de acompañarte mientras exploras el sistema.';
        }
        return 'Gracias por escribirme. Soy una demostración visual: todavía no estoy conectada a una IA ni a la información de VITA. No compartas datos de pacientes aquí.';
    }

    async function getReply(prompt) {
        const endpoint = root.dataset.endpoint?.trim();
        if (!endpoint) return { reply: demoReply(prompt), actions: [] };
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ message: prompt }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'No pude obtener una respuesta. Inténtalo nuevamente.');
        return {
            reply: String(payload.reply ?? payload.message ?? 'No recibí una respuesta.'),
            actions: Array.isArray(payload.actions) ? payload.actions : [],
            inputType: payload.input_type === 'tel' ? 'tel' : 'text',
        };
    }

    async function sendMessage(text) {
        const content = text.trim();
        if (!content || busy) return;
        addMessage(content, 'user', 'Tú');
        input.value = '';
        busy = true;
        sendButton.disabled = true;
        robot.setState('thinking');
        status.textContent = 'Pensando…';
        const typing = addMessage('Estoy pensando…', 'assistant', 'Viti');
        typing.classList.add('vita-message--typing');
        persistConversation();
        await new Promise(resolve => window.setTimeout(resolve, 650));
        let result;
        try {
            result = await getReply(content);
        } catch (error) {
            result = { reply: error.message || 'No pude responder en este momento.', actions: [] };
        }
        typing.remove();
        addMessage(result.reply, 'assistant', 'Viti', result.actions);
        if (phoneField && phoneInput) {
            const wantsPhone = result.inputType === 'tel';
            phoneField.hidden = !wantsPhone;
            phoneInput.disabled = !wantsPhone;
            phoneInput.value = '';
            input.hidden = wantsPhone;
            window.VitaPhone?.refresh();
        }
        busy = false;
        sendButton.disabled = false;
        if (voiceEnabled) voice.speak(result.reply);
        else {
            robot.setState('happy');
            status.textContent = connectedStatus;
        }
        window.setTimeout(() => {
            if (robot.state === 'happy') robot.setState('idle');
        }, 1600);
        if (phoneInput && !phoneInput.disabled) phoneInput.focus(); else input.focus();
    }

    toggle.addEventListener('click', event => {
        if (ignoreNextToggleClick) {
            event.preventDefault();
            event.stopPropagation();
            ignoreNextToggleClick = false;
            return;
        }
        root.classList.remove('is-dropping');
        root.classList.add('is-selecting');
        window.clearTimeout(interactionTimer);
        interactionTimer = window.setTimeout(() => root.classList.remove('is-selecting'), 650);
        setOpen(!opened);
    });
    document.querySelector('[data-viti-trash]')?.addEventListener('click', () => {
        setOpen(false);
        root.classList.add('is-avatar-hidden');
        try { window.sessionStorage.setItem(avatarVisibilityKey, '1'); } catch (_) {}
        document.querySelector('[data-viti-nav-toggle]')?.focus();
    });
    root.querySelector('[data-minimize]').addEventListener('click', () => setOpen(false));
    root.querySelector('[data-close]').addEventListener('click', () => setOpen(false));
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (phoneInput && !phoneInput.disabled) {
            if (window.VitaPhone && !window.VitaPhone.validate(phoneInput)) return;
            sendMessage(window.VitaPhone?.getNumber(phoneInput) || phoneInput.value);
        } else sendMessage(input.value);
    });
    root.querySelector('[data-assistant-phone-cancel]')?.addEventListener('click', () => sendMessage('cancelar'));
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    micButton.addEventListener('click', () => voice.startListening());
    voiceButton.addEventListener('click', () => {
        voiceEnabled = !voiceEnabled;
        voice.setEnabled(voiceEnabled);
        voiceButton.setAttribute('aria-pressed', String(voiceEnabled));
        voiceButton.innerHTML = voiceEnabled ? '<i class="fa-solid fa-volume-high" aria-hidden="true"></i><span class="sr-only">Desactivar voz</span>' : '<i class="fa-solid fa-volume-xmark" aria-hidden="true"></i><span class="sr-only">Activar voz</span>';
        announce(voiceEnabled ? 'Voz activada' : 'Voz desactivada');
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && opened) setOpen(false);
    });
    if (!restoreConversation()) {
        addMessage(root.dataset.welcome || 'Hola, soy Viti. Que necesitas?', 'assistant', 'Viti');
    }
    if (openInitially) setOpen(true);
    window.addEventListener('beforeunload', () => robot.dispose(), { once: true });
}
