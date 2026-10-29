@extends(!$isPatient && request()->boolean('embedded') ? 'layouts.telemedicine-embedded' : 'layouts.menu-customer')

@section('header')
    <div class="flex items-center gap-3">
        <span class="grid h-10 w-10 place-items-center rounded-xl text-white" style="background: {{ themeColor(600) }}"><i class="fa-solid fa-video"></i></span>
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Teleconsulta</h2>
            <p class="text-sm text-gray-500">{{ $isPatient ? 'Atención por videollamada' : 'Sala vinculada a la cita médica' }}</p>
        </div>
    </div>
@endsection

@section('content')
<main class="mx-auto max-w-6xl space-y-5 px-3 pb-8 sm:px-6 lg:px-8">
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $appointment->specialty?->name ?? 'Teleconsulta' }}</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ $isPatient ? ($appointment->professional?->name ?? 'Profesional') : ($appointment->patient?->name ?? 'Paciente') }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    {{ \Carbon\Carbon::parse($appointment->start_at)->timezone(config('app.timezone'))->translatedFormat('l j \d\e F, H:i') }}
                </p>
            </div>
            @unless($isPatient)
                <a href="{{ $clinicalRecordUrl }}" target="_blank" rel="noopener" class="rounded-xl px-4 py-2 text-sm font-semibold text-white hover:brightness-90" style="background: {{ themeColor(600) }}">
                    <i class="fa-solid fa-file-medical mr-2"></i>Abrir ficha clínica
                </a>
            @endunless
            <span id="connectionStatus" class="rounded-full bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800">Sin conectar</span>
        </div>
        @if(!$signalingConfigured)
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Configura Laravel Reverb para habilitar la videollamada.
            </div>
        @endif
        <div id="telemedicineWindowNotice" class="{{ $telemedicineWindowOpen ? 'hidden' : '' }} mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            La sala estará habilitada desde 30 minutos antes de la cita y hasta 2 horas después de su término.
        </div>
        @if($isPatient)
            <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                <input id="telemedicineConsent" type="checkbox" class="mt-1 rounded border-gray-300" {{ $session->patient_consent_at ? 'checked' : '' }}>
                <span>Confirmo que recibí información sobre esta atención por telemedicina y acepto continuar. Puedo solicitar orientación sobre una atención presencial.</span>
            </label>
        @endif
    </section>

    <section class="{{ !$isPatient && request()->boolean('embedded') ? 'embedded-video-stage' : '' }} grid gap-4 lg:grid-cols-2">
        <div class="relative aspect-video overflow-hidden rounded-2xl bg-slate-950 shadow-lg">
            <video id="localVideo" class="h-full w-full object-cover" autoplay playsinline muted></video>
            <span class="absolute bottom-3 left-3 rounded-lg bg-black/50 px-3 py-1 text-sm text-white">Tú</span>
        </div>
        <div id="remoteTracks" class="grid aspect-video auto-rows-fr gap-2 overflow-y-auto rounded-2xl bg-slate-900 p-2 shadow-lg">
            <div id="remotePlaceholder" class="grid place-items-center text-sm text-slate-300">La otra persona aparecerá aquí al conectarse.</div>
        </div>
    </section>

    <section class="flex flex-wrap items-center justify-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
        <button id="joinCall" type="button" data-join-allowed-at="{{ $joinAllowedAt }}" data-join-expires-at="{{ $joinExpiresAt }}" class="rounded-xl px-5 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" style="background: {{ themeColor(600) }}" {{ $signalingConfigured && $telemedicineWindowOpen ? '' : 'disabled' }}>
            <i class="fa-solid fa-video mr-2"></i>Ingresar a la teleconsulta
        </button>
        <button id="toggleMic" type="button" class="hidden rounded-xl border border-gray-300 px-4 py-3 text-gray-700 hover:bg-gray-50"><i class="fa-solid fa-microphone mr-2"></i>Micrófono</button>
        <button id="toggleCamera" type="button" class="hidden rounded-xl border border-gray-300 px-4 py-3 text-gray-700 hover:bg-gray-50"><i class="fa-solid fa-camera mr-2"></i>Cámara</button>
        <button id="leaveCall" type="button" class="hidden rounded-xl bg-gray-700 px-5 py-3 font-semibold text-white hover:bg-gray-800"><i class="fa-solid fa-phone-slash mr-2"></i>Salir</button>
        @unless($isPatient)
            <button id="endCall" type="button" class="hidden rounded-xl bg-red-600 px-5 py-3 font-semibold text-white hover:bg-red-700"><i class="fa-solid fa-circle-stop mr-2"></i>Finalizar teleconsulta</button>
        @endunless
    </section>
    <div class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
        <span id="telemedicineTimer">Duraci&#243;n prevista: 30:00</span>
        <p id="telemedicineTimeWarning" hidden class="mt-1 font-semibold text-amber-700" role="status" aria-live="polite"></p>
    </div>
    <p id="telemedicineError" class="hidden rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"></p>
</main>
@endsection

@push('scripts')
<script type="module">
const endpoint = @json($tokenUrl);
const endUrl = @json($endUrl);
const patient = @json($isPatient);
const join = document.getElementById('joinCall');
const status = document.getElementById('connectionStatus');
const errorBox = document.getElementById('telemedicineError');
const remote = document.getElementById('remoteTracks');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
let clientId, connection, echo, channel, stream, heartbeat, restarting, closing = false;
let makingOffer = false, ignoreOffer = false, settingAnswer = false, candidates = [], remoteClient;
let signalQueue = Promise.resolve(), receiveQueue = Promise.resolve();
let ended = false, joining = false;

const consultationDuration = 30 * 60 * 1000;
let consultationStartedAt = null, serverOffset = 0, fiveMinuteWarning = false;
function updateConsultationClock() {
    if (consultationStartedAt === null || ended) return;
    const remaining = Math.max(0, Math.ceil((consultationStartedAt + consultationDuration - (Date.now() + serverOffset)) / 1000));
    const display = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
    document.getElementById('telemedicineTimer').textContent = remaining ? `Tiempo restante: ${display}` : 'Tiempo previsto cumplido. Puedes continuar la consulta.';
    if (window.parent !== window) window.parent.postMessage({ type: 'vita:room-clock', display: remaining ? display : 'Tiempo cumplido' }, location.origin);
    if (remaining <= 300 && remaining > 0 && !fiveMinuteWarning) {
        fiveMinuteWarning = true;
        const message = 'Quedan 5 minutos de la consulta. La videollamada no se cortará automáticamente.';
        const notice = document.getElementById('telemedicineTimeWarning');
        notice.textContent = message; notice.hidden = false;
        if (window.parent !== window) window.parent.postMessage({ type:'vita:consultation-warning', message }, location.origin);
        else window.Swal?.fire({ toast:true, position:'top-end', icon:'info', title:message, showConfirmButton:false, timer:8000 });
    }
    if (remaining === 0) {
        const notice = document.getElementById('telemedicineTimeWarning');
        notice.textContent = 'Se cumplieron los 30 minutos. La llamada continuará hasta que decidas finalizarla.';
        notice.hidden = false;
    }
}
setInterval(updateConsultationClock, 1000);
function refreshMediaButtons() {
    for (const [id, kind, label, icon] of [['toggleMic', 'audio', 'Micrófono', 'microphone'], ['toggleCamera', 'video', 'Cámara', 'video']]) {
        const button = document.getElementById(id);
        const tracks = stream?.getTracks().filter(track => track.kind === kind && track.readyState !== 'ended') || [];
        const enabled = tracks.some(track => track.enabled);
        button.disabled = !tracks.length;
        button.setAttribute('aria-pressed', String(enabled));
        button.setAttribute('aria-label', `${enabled ? 'Desactivar' : 'Activar'} ${label.toLowerCase()}`);
        button.title = button.getAttribute('aria-label');
        const symbol = document.createElement('i');
        symbol.className = `fa-solid fa-${icon}${enabled ? '' : '-slash'} mr-2`;
        symbol.setAttribute('aria-hidden', 'true');
        button.replaceChildren(symbol, document.createTextNode(enabled ? label : `${label} apagado`));
        button.style.color = enabled ? '' : '#b91c1c';
        button.style.backgroundColor = enabled ? '' : '#fef2f2';
    }
}
function showError(error) {
    errorBox.textContent = error.message || String(error);
    errorBox.classList.remove('hidden');
}
function windowOpen() {
    const now = Date.now();
    return now >= Date.parse(join.dataset.joinAllowedAt) && now <= Date.parse(join.dataset.joinExpiresAt);
}
function publishRoomState(joined) {
    if (window.parent !== window) window.parent.postMessage({ type: 'vita:room-state', joined }, location.origin);
}
window.addEventListener('message', async event => {
    if (event.source !== window.parent || event.origin !== location.origin || event.data?.type !== 'vita:stop-call') return;
    await disconnect();
    window.parent.postMessage({ type: 'vita:room-stopped' }, location.origin);
});
function controls(active) {
    refreshMediaButtons();
    publishRoomState(active);
    join.classList.toggle('hidden', active);
    for (const id of ['leaveCall', 'toggleMic', 'toggleCamera', 'endCall']) {
        document.getElementById(id)?.classList.toggle('hidden', !active);
    }
    join.disabled = ended || joining || !windowOpen() || !@json($signalingConfigured);
}
async function request(action, extra = {}) {
    const response = await fetch(endpoint, {
        method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ action, client_id: clientId, ...(patient ? { consent: true } : {}), ...extra }),
    });
    const result = await response.json();
    if (response.ok && Number.isFinite(result.serverNow)) serverOffset = result.serverNow - Date.now();
    if (response.ok && Number.isFinite(result.consultationStartedAt)) {
        consultationStartedAt = result.consultationStartedAt;
        updateConsultationClock();
    }

    if (!response.ok) throw Object.assign(new Error(result.message || 'No se pudo conectar con la sala.'), { status: response.status });
    return result;
}
function send(signal) {
    signalQueue = signalQueue.catch(() => {}).then(() => request('signal', { signal }));
    return signalQueue;
}
function clearRemote() {
    remote.replaceChildren();
    const placeholder = document.createElement('p');
    placeholder.className = 'grid place-items-center text-sm text-slate-300';
    placeholder.textContent = 'Esperando a la otra persona...';
    remote.append(placeholder);
}
function closePeer() {
    clearTimeout(restarting);
    if (connection) {
        connection.onnegotiationneeded = connection.onicecandidate = connection.ontrack = connection.onconnectionstatechange = null;
        connection.close();
    }
    connection = null;
    candidates = [];
    makingOffer = ignoreOffer = settingAnswer = false;
    clearRemote();
}
function createPeer(iceServers) {
    closePeer();
    const peer = new RTCPeerConnection({ iceServers });
    connection = peer;
    peer.onicecandidate = ({ candidate }) => {
        if (candidate) send({ type: 'candidate', candidate: candidate.toJSON() }).catch(showError);
    };
    peer.ontrack = ({ streams, track }) => {
        let video = remote.querySelector('video');
        if (!video) {
            remote.replaceChildren();
            video = document.createElement('video');
            video.autoplay = true; video.playsInline = true;
            video.className = 'h-full w-full rounded-xl object-cover';
            video.srcObject = streams[0] || new MediaStream();
            remote.append(video);
        }
        if (!streams[0]) video.srcObject.addTrack(track);
        video.play().catch(() => { video.controls = true; });
    };
    peer.onconnectionstatechange = () => {
        if (connection !== peer) return;
        status.textContent = peer.connectionState === 'connected' ? 'Conectado'
            : peer.connectionState === 'failed' ? 'No se pudo conectar. Revisa la red y el servidor TURN.' : 'Conectando...';
        clearTimeout(restarting);
        if (peer.connectionState === 'connected') send({ type: 'connected' }).catch(showError);
        if (peer.connectionState === 'failed') peer.restartIce();
        if (peer.connectionState === 'disconnected') restarting = setTimeout(() => peer.restartIce(), 5000);
    };
    peer.onnegotiationneeded = async () => {
        try {
            makingOffer = true;
            await peer.setLocalDescription();
            if (connection === peer) await send({ type: 'description', description: peer.localDescription.toJSON() });
        } catch (error) { if (!closing) showError(error); }
        finally { makingOffer = false; }
    };
    return peer;
}
function addLocalTracks(peer) {
    for (const track of stream.getTracks()) peer.addTrack(track, stream);
}
async function receive(event, iceServers) {
    if (event.signal?.type === 'consultation' && Number.isFinite(event.signal.startedAt)) {
        consultationStartedAt = event.signal.startedAt;
        updateConsultationClock();
        return;
    }

    if (closing || !stream || event.sender === (patient ? 'patient' : 'staff')) return;
    const signal = event.signal;
    if (signal.type === 'ended') {
        ended = true; await disconnect(false); status.textContent = 'Teleconsulta finalizada'; return;
    }
    if (signal.type === 'leave') {
        closePeer(); remoteClient = null; status.textContent = 'Esperando a la otra persona...'; return;
    }
    if (signal.type === 'ready') {
        if (remoteClient !== event.clientId || !connection) {
            remoteClient = event.clientId;
            addLocalTracks(createPeer(iceServers));
        }
        return;
    }
    if (remoteClient && remoteClient !== event.clientId) return;
    remoteClient = event.clientId;
    const peer = connection || createPeer(iceServers);
    if (signal.type === 'description') {
        const description = signal.description;
        const readyForOffer = !makingOffer && (peer.signalingState === 'stable' || settingAnswer);
        ignoreOffer = !patient && description.type === 'offer' && !readyForOffer;
        if (ignoreOffer) return;
        settingAnswer = description.type === 'answer';
        try { await peer.setRemoteDescription(description); }
        finally { settingAnswer = false; }
        for (const candidate of candidates.splice(0)) await peer.addIceCandidate(candidate);
        if (description.type === 'offer') {
            if (!peer.getSenders().length) addLocalTracks(peer);
            await peer.setLocalDescription();
            if (connection === peer) await send({ type: 'description', description: peer.localDescription.toJSON() });
        }
    } else if (signal.type === 'candidate' && !ignoreOffer) {
        if (peer.remoteDescription) await peer.addIceCandidate(signal.candidate);
        else candidates.push(signal.candidate);
    }
}
async function disconnect(notify = true) {
    closing = true;
    clearInterval(heartbeat); heartbeat = null;
    closePeer();
    stream?.getTracks().forEach(track => track.stop()); stream = null;
    document.getElementById('localVideo').srcObject = null;
    if (notify && clientId) await request('leave').catch(() => {});
    echo?.disconnect(); echo = null; channel = null;
    remoteClient = null;
    status.textContent = 'Sin conectar';
    joining = false;
    controls(false);
}
join.addEventListener('click', async () => {
    if (joining || stream || ended) return;
    if (patient && !document.getElementById('telemedicineConsent').checked) {
        showError(new Error('Acepta el consentimiento antes de ingresar.')); return;
    }
    joining = true; closing = false; controls(false); errorBox.classList.add('hidden');
    try {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || !window.RTCPeerConnection) {
            throw new Error('Utiliza HTTPS y un navegador compatible para acceder a la cámara y micrófono.');
        }
        clientId = crypto.randomUUID();
        if (!window.Echo) throw new Error('No se cargó el servicio de conexión. Recarga la página.');
        stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: { width: { ideal: 640 }, height: { ideal: 480 }, frameRate: { ideal: 24, max: 30 } } });
        document.getElementById('localVideo').srcObject = stream;
        const payload = await request('join');
        echo = new window.Echo.constructor({ ...window.Echo.options, authEndpoint: endpoint,
            auth: { headers: { 'X-CSRF-TOKEN': csrf }, params: { action: 'auth', client_id: clientId, ...(patient ? { consent: true } : {}) } } });
        channel = echo.private(payload.channel);
        channel.listen('.telemedicine.signal', event => {
            receiveQueue = receiveQueue.catch(() => {}).then(() => receive(event, payload.iceServers)).catch(showError);
        });
        await new Promise((resolve, reject) => {
            const timeout = setTimeout(() => reject(new Error('No se pudo conectar con Reverb. Comprueba el servidor WebSocket.')), 12000);
            channel.subscribed(() => { clearTimeout(timeout); resolve(); });
            channel.error(() => { clearTimeout(timeout); reject(new Error('No se pudo autorizar la conexión a la sala.')); });
        });
        status.textContent = 'Esperando a la otra persona...';
        await send({ type: 'ready' });
        heartbeat = setInterval(() => send({ type: 'ready' }).catch(async error => {
            showError(error);
            if ([403, 409, 410].includes(error.status)) await disconnect(false);
        }), 10000);
        controls(true);
    } catch (error) { await disconnect(); showError(error); }
    finally { joining = false; if (!stream) controls(false); }
});
document.getElementById('leaveCall').addEventListener('click', () => disconnect());
for (const [id, kind] of [['toggleMic', 'audio'], ['toggleCamera', 'video']]) {
    document.getElementById(id).addEventListener('click', () => {
        const tracks = stream?.getTracks().filter(track => track.kind === kind && track.readyState !== 'ended') || [];
        const enabled = !tracks.some(track => track.enabled);
        for (const track of tracks) track.enabled = enabled;
        refreshMediaButtons();
    });
}
document.getElementById('endCall')?.addEventListener('click', async () => {
    if (!confirm('¿Finalizar la videollamada para ambos participantes?')) return;
    try {
        const response = await fetch(endUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
        if (!response.ok) throw new Error('No se pudo finalizar la videollamada.');
        ended = true; await disconnect(); status.textContent = 'Teleconsulta finalizada';
    } catch (error) { showError(error); }
});
window.addEventListener('pagehide', () => {
    stream?.getTracks().forEach(track => track.stop()); connection?.close(); echo?.disconnect();
    clearInterval(heartbeat);
    if (clientId && !closing) fetch(endpoint, { method: 'POST', keepalive: true,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ action: 'leave', client_id: clientId, ...(patient ? { consent: true } : {}) }) }).catch(() => {});
});
setInterval(() => {
    const open = windowOpen();
    document.getElementById('telemedicineWindowNotice').classList.toggle('hidden', open);
    if (!open && stream) { ended = true; disconnect(); }
    if (!stream) controls(false);
}, 15000);
</script>
@endpush
