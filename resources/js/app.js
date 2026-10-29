import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    const assistantRoot = document.querySelector('[data-vita-assistant]');
    const assistantButton = document.querySelector('[data-assistant-toggle]');
    if (assistantRoot) {
        const avatarVisibilityKey = `vita-assistant-avatar-hidden:${window.location.hostname}:${assistantRoot.dataset.persistConversation || 'guest'}`;
        try {
            if (window.sessionStorage.getItem(avatarVisibilityKey) === '1') assistantRoot.classList.add('is-avatar-hidden');
        } catch (_) {}
    }
    if (!assistantRoot || !assistantButton) return;

    const initializeAssistant = async (openAfterLoad = false) => {
        if (assistantRoot.dataset.initialized === 'true') return;
        if (assistantRoot.dataset.loading === 'true') {
            if (openAfterLoad) assistantRoot.dataset.openAfterLoad = 'true';
            return;
        }
        assistantRoot.dataset.loading = 'true';
        assistantButton.disabled = true;
        try {
            const { mountAssistant } = await import('./vita-assistant/chat.js');
            mountAssistant(openAfterLoad || assistantRoot.dataset.openAfterLoad === 'true');
        } catch (error) {
            console.error('No se pudo iniciar el asistente VITA.', error);
            if (assistantRoot) delete assistantRoot.dataset.loading;
        } finally {
            assistantButton.disabled = false;
        }
    };

    assistantButton.addEventListener('click', () => initializeAssistant(true));
    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(() => initializeAssistant(false), { timeout: 1800 });
    } else {
        window.setTimeout(() => initializeAssistant(false), 700);
    }
});
