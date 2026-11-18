import { mountAssistant } from './vita-assistant/chat';

document.addEventListener('DOMContentLoaded', () => {
    mountAssistant(false);
    document.querySelector('[data-viti-nav-toggle]')?.addEventListener('click', () => {
        const assistant = document.querySelector('[data-vita-assistant]');
        if (!assistant) return;
        try {
            sessionStorage.removeItem(`vita-assistant-avatar-hidden:${window.location.hostname}:${assistant.dataset.persistConversation || 'guest'}`);
        } catch (_) {}
        assistant.classList.remove('is-avatar-hidden');
        document.querySelector('[data-assistant-toggle]')?.click();
    });
});
