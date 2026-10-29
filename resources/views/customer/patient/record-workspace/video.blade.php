@if($currentEncounter?->modality === 'telemedicine')
    <section data-vita-session-id="{{ $currentEncounter->telemedicine_session_id }}"
        data-vita-room-url="{{ $currentEncounter->telemedicine_session_id && $canManageCurrentEncounter ? route('customers.telemedicine.room', ['customer' => request()->route('customer'), 'sessionId' => $currentEncounter->telemedicine_session_id, 'embedded' => 1]) : '' }}"
        data-vita-record-url="{{ route('patients.medical-record.show', ['customer' => request()->route('customer'), 'id' => $patient->id, 'encounter_id' => $currentEncounter->id]) }}"
        data-vita-patient="{{ $patient->name }}"
        x-init="$nextTick(() => { if (videoLoaded) window.VitaNavigation?.openRoom($el); }); $watch('videoLoaded', value => { if (value) window.VitaNavigation?.openRoom($el); })"
        class="clinical-video" aria-label="Videollamada de la atención">
        <div class="clinical-video-toolbar">
            <span class="clinical-video-drag-handle font-semibold text-gray-700" tabindex="0" role="button" aria-label="Mover videollamada con las flechas del teclado" title="Arrastra para mover la videollamada"><i class="fa-solid fa-video mr-1" aria-hidden="true"></i>Teleconsulta</span>
            <div class="flex flex-wrap gap-1" x-show="!window.VitaNavigation?.framed">
                <button type="button" @click="videoMode = videoMode === 'mini' ? 'normal' : 'mini'" :aria-label="videoMode === 'mini' ? 'Restaurar video' : 'Minimizar video'"><i class="fa-solid" :class="videoMode === 'mini' ? 'fa-window-restore' : 'fa-minus'" aria-hidden="true"></i></button>
                <button type="button" @click="videoMode = videoMode === 'expanded' ? 'normal' : 'expanded'" aria-label="Ampliar o restaurar video"><i class="fa-solid fa-expand" aria-hidden="true"></i></button>
            </div>
        </div>
        @if($currentEncounter->telemedicineSession && $canManageCurrentEncounter && $currentEncounter->telemedicineSession->status !== 'ended')
            @php
                $videoSession = $currentEncounter->telemedicineSession;
                $patientLinkExpires = $videoSession->appointment_id
                    ? \Illuminate\Support\Carbon::parse($videoSession->appointment->end_at)->addHours(2)
                    : $videoSession->expires_at;
                $videoPatientUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('telemedicine.patient.room', $patientLinkExpires, [
                    'customer' => request()->route('customer'), 'sessionId' => $videoSession->id,
                ]);
            @endphp
            <div class="clinical-patient-invite" x-data="{
                copied: false, copying: false, linkVisible: false, resetTimer: null,
                copyFallback(text) {
                    const field = document.createElement('textarea');
                    const active = document.activeElement;
                    field.value = text;
                    field.readOnly = true;
                    field.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;font-size:16px;pointer-events:none;';
                    this.$el.appendChild(field);
                    try {
                        field.focus({ preventScroll: true });
                        field.select();
                        field.setSelectionRange(0, field.value.length);
                        return document.execCommand('copy');
                    } catch (_) { return false; }
                    finally {
                        field.remove();
                        active?.focus({ preventScroll: true });
                    }
                },
                async copyLink() {
                    if (this.copying) return;
                    if (this.linkVisible) {
                        this.linkVisible = false;
                        this.copied = false;
                        clearTimeout(this.resetTimer);
                        return;
                    }
                    this.linkVisible = true;
                    this.copying = true;
                    try {
                        const url = this.$refs.copyButton.dataset.patientUrl;
                        let success = false;
                        if (window.isSecureContext && navigator.clipboard?.writeText) {
                            try { await navigator.clipboard.writeText(url); success = true; }
                            catch (_) { /* Try the browser selection-based copy below. */ }
                        }
                        if (!success) success = this.copyFallback(url);
                        if (!success) throw new Error('Clipboard unavailable');
                        this.copied = true;
                        window.showClinicalRecordToast('Enlace copiado al portapapeles.', 'success');
                        clearTimeout(this.resetTimer);
                        this.resetTimer = setTimeout(() => this.copied = false, 3000);
                    } catch (_) {
                        this.$nextTick(() => { this.$refs.manualLink.focus(); this.$refs.manualLink.select(); });
                        window.showClinicalRecordToast('No se pudo copiar. Puedes copiar el enlace desde el campo visible.', 'info');
                    } finally { this.copying = false; }
                }
            }">
                <div class="clinical-patient-invite-heading">
                    <span class="clinical-patient-invite-icon"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></span>
                    <div>
                        <p class="clinical-patient-invite-title">Invita a tu paciente</p>
                        <p class="clinical-patient-invite-description">Comparte el acceso a esta teleconsulta.</p>
                    </div>
                </div>
                <button type="button" class="clinical-copy-patient-link" x-ref="copyButton"
                    data-patient-url="{{ $videoPatientUrl }}" :data-copied="String(copied)" :disabled="copying" :aria-expanded="String(linkVisible)" aria-controls="clinical-patient-link-details" @click="copyLink()">
                    <i class="fa-solid" :class="copying ? 'fa-spinner fa-spin' : (copied ? 'fa-check' : 'fa-link')" aria-hidden="true"></i>
                    <span aria-live="polite" x-text="copying ? 'Copiando...' : (linkVisible ? 'Ocultar enlace del paciente' : 'Copiar enlace para el paciente')">Copiar enlace para el paciente</span>
                    <i class="fa-regular fa-copy clinical-copy-patient-link-trailing" x-show="!copied && !copying" aria-hidden="true"></i>
                </button>
                <div id="clinical-patient-link-details" x-show="linkVisible" x-cloak class="clinical-patient-invite-manual">
                    <label for="clinical-patient-manual-link">Enlace para el paciente:</label>
                    <input id="clinical-patient-manual-link" x-ref="manualLink" type="text" readonly value="{{ $videoPatientUrl }}" @click="$el.select()">
                </div>
                @if(!$videoSession->patient_consent_at)
                    <p class="clinical-patient-invite-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>El paciente acepta el consentimiento al ingresar.</p>
                @endif
            </div>
            <div x-show="!videoLoaded" class="p-4 text-sm text-gray-600">
                <p>La llamada utiliza la sala de esta atención y sus comprobaciones de consentimiento.</p>
                <button type="button" @click="videoLoaded = true" class="mt-3 rounded-lg px-3 py-2 font-semibold text-white" style="background: {{ themeColor(600) }}">Abrir sala de videollamada</button>
            </div>
            <div data-vita-call-slot x-show="videoLoaded" class="p-4 text-sm text-gray-600">
                <p data-vita-call-status>La sala se abre en un cuadro flotante para continuar la llamada mientras navegas por Vita.</p>
                <button type="button" @click="window.VitaNavigation?.openRoom($el.closest('[data-vita-room-url]'))" class="mt-3 rounded-lg px-3 py-2 font-semibold text-white" style="background: {{ themeColor(600) }}">Mostrar sala flotante</button>
            </div>
        @elseif($currentEncounter->telemedicineSession?->status === 'ended')
            <p class="p-4 text-sm text-gray-600">Videollamada finalizada. Puedes continuar con el registro clínico.</p>
        @else
            <p class="p-4 text-sm text-gray-600">La sala requiere una sesión asociada y acceso del profesional responsable.</p>
        @endif
    </section>
@endif
