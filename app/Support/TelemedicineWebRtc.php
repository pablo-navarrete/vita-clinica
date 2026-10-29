<?php

namespace App\Support;

use App\Events\TelemedicineSignal;
use App\Models\TelemedicineSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class TelemedicineWebRtc
{
    public function handle(Request $request, TelemedicineSession $session, bool $patient)
    {
        abort_unless(config('broadcasting.default') === 'reverb' && config('broadcasting.connections.reverb.key')
            && config('broadcasting.connections.reverb.secret'), 503, 'Configura Laravel Reverb para conectar la videollamada.');
        $data = $request->validate([
            'action' => ['sometimes', Rule::in(['join', 'auth', 'signal', 'leave'])],
            'client_id' => ['required', 'uuid'],
        ]);
        $role = $patient ? 'patient' : 'staff';
        $key = 'telemedicine:' . $session->customer_id . ':' . $session->id . ':' . $role;
        $client = $data['client_id'];
        $action = $data['action'] ?? 'join';
        abort_if(RateLimiter::tooManyAttempts($key . ':rate', 360), 429, 'Demasiados mensajes de conexión.');
        RateLimiter::hit($key . ':rate', 60);
        return Cache::lock($key . ':lock', 10)->block(3, function () use ($key, $client, $action, $session, $role, $request) {
            $occupant = Cache::get($key);
            abort_if($occupant && $occupant !== $client, 409, 'Ya tienes esta sala abierta en otro dispositivo. Cierra la otra conexión y espera 30 segundos.');
            if ($action !== 'join') abort_unless($occupant === $client, 409, 'Vuelve a ingresar a la sala.');
            if ($action === 'auth') {
                $auth = $request->validate(['socket_id' => ['required', 'regex:/^\d+\.\d+$/'], 'channel_name' => ['required', 'string']]);
                abort_unless($auth['channel_name'] === 'private-telemedicine.' . $session->room_name, 403);
                $signature = hash_hmac('sha256', $auth['socket_id'] . ':' . $auth['channel_name'], config('broadcasting.connections.reverb.secret'));
                return response()->json(['auth' => config('broadcasting.connections.reverb.key') . ':' . $signature]);
            }
            if ($action === 'signal' || $action === 'leave') {
                $signal = $action === 'leave' ? ['type' => 'leave'] : $request->validate([
                    'signal' => ['required', 'array:type,description,candidate'],
                    'signal.type' => ['required', Rule::in(['ready', 'description', 'candidate', 'connected'])],
                    'signal.description' => ['required_if:signal.type,description', 'array:type,sdp'],
                    'signal.description.type' => ['required_with:signal.description', Rule::in(['offer', 'answer'])],
                    'signal.description.sdp' => ['required_with:signal.description', 'string', 'max:60000'],
                    'signal.candidate' => ['required_if:signal.type,candidate', 'array:candidate,sdpMid,sdpMLineIndex,usernameFragment'],
                    'signal.candidate.candidate' => ['required_with:signal.candidate', 'string', 'max:4096'],
                    'signal.candidate.sdpMid' => ['nullable', 'string', 'max:256'],
                    'signal.candidate.sdpMLineIndex' => ['nullable', 'integer', 'min:0', 'max:64'],
                    'signal.candidate.usernameFragment' => ['nullable', 'string', 'max:256'],
                ])['signal'];
                if ($signal['type'] === 'connected') {
                    TelemedicineSession::whereKey($session->id)->whereNull('started_at')->update(['started_at' => now()]);
                    $session->refresh();
                    $signal = ['type' => 'consultation', 'startedAt' => $session->started_at->getTimestampMs()];
                }
                broadcast(new TelemedicineSignal($session->room_name, $role, $client, $signal));
            }
            if ($action === 'leave') Cache::forget($key);
            else Cache::put($key, $client, 30);
            if ($session->status === 'scheduled' && $action === 'join') {
                $session->forceFill(['status' => 'in_progress'])->save();
            }
            return response()->json(['channel' => 'telemedicine.' . $session->room_name, 'iceServers' => $this->iceServers($client),
                'consultationStartedAt' => $session->started_at?->getTimestampMs(), 'serverNow' => now()->getTimestampMs()]);
        });
    }

    private function iceServers(string $client): array
    {
        $servers = [];
        $stun = config('services.webrtc.stun_urls');
        if ($stun) $servers[] = ['urls' => $stun];
        if (config('services.webrtc.turn_urls') && config('services.webrtc.turn_secret')) {
            $username = now()->addHours(6)->timestamp . ':' . $client;
            $servers[] = ['urls' => config('services.webrtc.turn_urls'), 'username' => $username,
                'credential' => base64_encode(hash_hmac('sha1', $username, config('services.webrtc.turn_secret'), true))];
        }
        return $servers;
    }
}
