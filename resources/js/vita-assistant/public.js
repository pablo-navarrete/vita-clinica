document.addEventListener('DOMContentLoaded', async () => {
    const root = document.querySelector('[data-vita-assistant]');
    if (!root) return;

    try {
        const { mountAssistant } = await import('./chat.js');
        mountAssistant(false);
    } catch (error) {
        console.error('No se pudo iniciar Viti en la web pública.', error);
    }
});
