export class VitaVoice {
    constructor({ onState = () => {}, onTranscript = () => {}, onError = () => {} } = {}) {
        this.onState = onState;
        this.onTranscript = onTranscript;
        this.onError = onError;
        this.enabled = false;
        this.recognition = null;
        this.synth = window.speechSynthesis || null;
        this.Recognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
        if (this.Recognition) {
            this.recognition = new this.Recognition();
            this.recognition.lang = 'es-CL';
            this.recognition.interimResults = false;
            this.recognition.maxAlternatives = 1;
            this.recognition.onstart = () => this.onState('listening');
            this.recognition.onend = () => this.onState('idle');
            this.recognition.onerror = event => {
                this.onState('idle');
                this.onError(this.errorMessage(event.error));
            };
            this.recognition.onresult = event => {
                const text = event.results?.[0]?.[0]?.transcript?.trim();
                if (text) this.onTranscript(text);
            };
        }
    }

    setEnabled(enabled) {
        this.enabled = Boolean(enabled);
        if (!this.enabled) this.stopSpeaking();
    }

    speak(text) {
        if (!this.enabled || !this.synth || !text) return;
        this.stopSpeaking();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'es-CL';
        utterance.rate = 0.98;
        utterance.pitch = 1.08;
        const voices = this.synth.getVoices();
        utterance.voice = voices.find(voice => /^es[-_]CL/i.test(voice.lang))
            || voices.find(voice => /^es/i.test(voice.lang))
            || null;
        utterance.onstart = () => this.onState('talking');
        utterance.onend = () => this.onState('idle');
        utterance.onerror = () => this.onState('idle');
        this.synth.speak(utterance);
    }

    startListening() {
        if (!this.recognition) {
            this.onError('Este navegador no permite dictado por voz. Puedes escribir el mensaje.');
            return;
        }
        try {
            this.recognition.start();
        } catch (error) {
            this.onError(error?.message || 'No se pudo iniciar el micrófono.');
        }
    }

    stopSpeaking() {
        if (this.synth?.speaking) this.synth.cancel();
    }

    errorMessage(code) {
        if (code === 'not-allowed' || code === 'service-not-allowed') return 'Permite el acceso al micrófono en el navegador para dictar.';
        if (code === 'no-speech') return 'No alcancé a escuchar voz. Inténtalo otra vez.';
        return 'No se pudo usar el micrófono. Puedes escribir el mensaje.';
    }
}
