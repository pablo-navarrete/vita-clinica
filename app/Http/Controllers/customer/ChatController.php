<?php

namespace App\Http\Controllers\customer;

use Closure;
use App\Events\ChatMessageSent;
use App\Events\ChatMessageReactionChanged;
use App\Events\ChatMessageDeleted;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ClinicBranch;
use App\Models\Patient;
use App\Models\User;
use App\Support\ClinicActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ChatController extends Controller
{
    public function presence(): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $cache = \Illuminate\Support\Facades\Cache::store();
        if ((int) $user->customer_id === $customerId) {
            $cache->put("chat-presence:{$customerId}:{$user->id}", true, 60);
        }
        $ids = User::query()->where('customer_id', $customerId)->where('status', 1)->pluck('id');
        $keys = $ids->map(fn ($id) => "chat-presence:{$customerId}:{$id}")->all();
        $states = $cache->many($keys);
        $online = $ids->filter(fn ($id) => (bool) ($states["chat-presence:{$customerId}:{$id}"] ?? false))->values();

        return response()->json(['online' => $online])->header('Cache-Control', 'private, no-store');
    }

    public function summary(): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $isSuperAdmin = $this->isTenantSuperAdmin($user);
        $conversations = ChatConversation::query()
            ->where('customer_id', $customerId)
            ->when(!$isSuperAdmin, fn ($query) => $query->whereHas('participants', fn ($participants) => $participants->where('users.id', $user->id)->whereNull('chat_participants.hidden_at')))
            ->with(['patient:id,name,rut', 'clinicBranch:id,name', 'participants:id,name', 'latestMessage.user:id,name'])
            ->orderByDesc('updated_at')->take(30)->get();

        $data = $conversations->map(function (ChatConversation $conversation) use ($user, $isSuperAdmin) {
            $lastReadAt = $conversation->participants->firstWhere('id', $user->id)?->pivot?->last_read_at;
            $unread = $isSuperAdmin ? 0 : $conversation->messages()->where('user_id', '!=', $user->id)
                ->when($lastReadAt, fn ($query) => $query->where('created_at', '>', $lastReadAt))->count();
            $otherNames = $conversation->participants->where('id', '!=', $user->id)->pluck('name')->take(3)->implode(', ');
            $latest = $this->latestVisibleMessage($conversation, $user->id);

            return [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'title' => $conversation->title ?: ($conversation->patient ? 'Paciente: ' . $conversation->patient->name : ($otherNames ?: 'Conversación')),
                'patient' => $conversation->patient?->name,
                'patient_rut' => $conversation->patient?->rut,
                'patient_url' => $conversation->patient ? route('patients.medical-record.show', [
                    'customer' => request()->route('customer'), 'id' => $conversation->patient_id,
                ]) : null,
                'branch' => $conversation->clinicBranch?->name,
                'latest_body' => $latest?->deleted_at ? 'Este mensaje fue eliminado.' : $latest?->body,
                'latest_user' => $latest?->user?->name,
                'latest_at' => $latest?->created_at?->timezone(config('app.timezone'))->format('d/m H:i'),
                'unread' => $unread,
                'can_send' => $isSuperAdmin || $conversation->participants->firstWhere('id', $user->id)?->pivot?->left_at === null,
                'can_delete' => true,
            ];
        });

        return response()->json(['data' => $data, 'unread' => $data->sum('unread')]);
    }

    public function searchConversations(Request $request, string $customer): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $isSuperAdmin = $this->isTenantSuperAdmin($user);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['q']);
        $branchScope = $request->query('branch_scope', 'all');
        abort_unless(in_array($branchScope, ['all', 'current', 'other'], true), 422);
        $activeBranchId = $this->activeBranchId($customerId);

        $conversationQuery = ChatConversation::query()
            ->where('customer_id', $customerId)
            ->when(!$isSuperAdmin, fn ($query) => $query->whereHas('participants', fn ($participants) => $participants->where('users.id', $user->id)->whereNull('chat_participants.hidden_at')));
        $this->scopeConversationsByBranch($conversationQuery, $branchScope, $activeBranchId, (int) $user->id);
        $conversations = $conversationQuery
            ->where(function ($query) use ($term) {
                $query->where('title', 'like', '%' . $term . '%')
                    ->orWhereHas('patient', fn ($patient) => $patient->where('name', 'like', '%' . $term . '%')->orWhere('rut', 'like', '%' . $term . '%'))
                    ->orWhereHas('participants', fn ($participants) => $participants->where('users.name', 'like', '%' . $term . '%')->orWhere('users.email', 'like', '%' . $term . '%'));
            })
            ->with(['patient:id,name', 'clinicBranch:id,name', 'participants:id,name,email', 'participants.branches:id,name', 'latestMessage.user:id,name'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $conversations->map(function (ChatConversation $conversation) use ($user, $activeBranchId) {
            $people = $conversation->participants->where('id', '!=', $user->id)->pluck('name')->take(3)->implode(', ');
            $latest = $this->latestVisibleMessage($conversation, $user->id);

            return [
                'id' => (int) $conversation->id,
                'branch' => $this->conversationBranchLabel($conversation, (int) $user->id, $activeBranchId),
                'title' => $conversation->title ?: ($conversation->patient ? 'Paciente: ' . $conversation->patient->name : ($people ?: 'Conversacion')),
                'latest_user' => $latest?->user?->name,
                'latest_body' => $latest?->deleted_at ? 'Este mensaje fue eliminado.' : $latest?->body,
                'latest_at' => $conversation->updated_at?->timezone(config('app.timezone'))->format('d/m H:i'),
            ];
        })->values()]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $term = trim((string) $request->query('q', ''));
        if ($term !== '' && mb_strlen($term) < 2) return response()->json(['data' => [], 'suggestions' => false]);

        $users = $this->eligibleChatRecipients($user, $customerId)
            ->when($term !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('users.name', 'like', '%' . $term . '%')
                ->orWhere('users.email', 'like', '%' . $term . '%')))
            ->with(['branches' => fn ($query) => $query->where('clinic_branches.status', true)->select('clinic_branches.id', 'clinic_branches.name')])
            ->orderBy('users.name')->limit($term === '' ? 5 : 20)->get(['users.id', 'users.name', 'users.email']);

        return response()->json(['data' => $users->map(fn (User $recipient) => $this->chatRecipientPayload($recipient)), 'suggestions' => $term === '']);
    }

    public function index(Request $request): View
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $isSuperAdmin = $this->isTenantSuperAdmin($user);
        $canSendMessages = $this->hasChatPermission($user, $customerId, 'chat.send');
        $branchScope = $request->query('branch_scope', 'all');
        abort_unless(in_array($branchScope, ['all', 'current', 'other'], true), 422);
        $activeBranchId = $this->activeBranchId($customerId);
        $conversationQuery = ChatConversation::query()
            ->where('customer_id', $customerId)
            ->when(!$isSuperAdmin, fn ($query) => $query->whereHas('participants', fn ($participants) => $participants->where('users.id', $user->id)->whereNull('chat_participants.hidden_at')));
        $this->scopeConversationsByBranch($conversationQuery, $branchScope, $activeBranchId, (int) $user->id);
        $conversations = $conversationQuery
            ->with(['patient:id,name,rut', 'clinicBranch:id,name', 'participants:id,name,email', 'participants.branches:id,name', 'latestMessage.user:id,name'])
            ->orderByDesc('updated_at')->paginate(30)->withQueryString();
        $conversations->getCollection()->each(fn (ChatConversation $conversation) => $conversation->setRelation('latestMessage', $this->latestVisibleMessage($conversation, $user->id)));

        $active = null;
        $messages = collect();
        if ($request->filled('conversation')) {
            $active = $this->conversationForUser((int) $request->query('conversation'), $customerId, $user->id);
        } else {
            $active = $conversations->first();
        }

        if ($active) {
            if (!$isSuperAdmin && $active->participants()->where('users.id', $user->id)->exists()) {
                $active->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
            }
            $initialBatch = $this->visibleMessages($active, $user->id)->with(['user:id,name', 'replyTo.user:id,name', 'forwardedFrom.user:id,name', 'reactions:id,message_id,user_id,emoji'])->latest('id')->take(51)->get();
            $hasMoreOlder = $initialBatch->count() > 50;
            $messages = $initialBatch->take(50)->reverse()->values();
        } else {
            $hasMoreOlder = false;
        }

        return view('customer.chat.index', [
            'conversations' => $conversations,
            'activeConversation' => $active,
            'messages' => $messages,
            'hasMoreOlder' => $hasMoreOlder,
            'isSuperAdmin' => $isSuperAdmin,
            'canSendMessages' => $canSendMessages,
            'activeBranch' => $this->activeBranch($customerId),
            'activeBranchId' => $activeBranchId,
            'branchScope' => $branchScope,
            'users' => $isSuperAdmin ? collect() : $this->eligibleChatRecipients($user, $customerId)
                ->with(['branches' => fn ($query) => $query->where('clinic_branches.status', true)->select('clinic_branches.id', 'clinic_branches.name')])
                ->orderBy('users.name')->get(['users.id', 'users.name', 'users.email']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $this->assertChatWriteAccess($user);
        $conversationType = $request->input('conversation_type');
        $isGroup = $conversationType === 'group';
        $data = $request->validate([
            'conversation_type' => ['required', 'in:direct,group'],
            'title' => ['nullable', 'string', 'max:120'],
            'participant_ids' => ['required', 'array', 'min:' . ($isGroup ? 2 : 1), 'max:' . ($isGroup ? 29 : 1)],
            'participant_ids.*' => ['required', 'integer', 'distinct'],
            'patient_id' => ['nullable', 'integer'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $participantIds = collect($data['participant_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $participants = User::query()->where('customer_id', $customerId)->where('status', 1)
            ->whereIn('id', $participantIds)->with('branches')->get();
        if ($participants->count() !== $participantIds->count() || $participantIds->contains($user->id)) {
            throw ValidationException::withMessages(['participant_ids' => 'Selecciona usuarios activos de esta clínica.']);
        }

        $patient = null;
        if (!empty($data['patient_id'])) {
            $patient = Patient::query()->where('customer_id', $customerId)->find($data['patient_id']);
            if (!$patient) throw ValidationException::withMessages(['patient_id' => 'El paciente no pertenece a esta clínica.']);
        }

        $conversation = DB::transaction(function () use ($data, $customerId, $user, $participantIds, $patient, $isGroup) {
            $conversation = ChatConversation::create([
                'customer_id' => $customerId,
                'clinic_branch_id' => $this->activeBranchId($customerId),
                'patient_id' => $patient?->id,
                'created_by' => $user->id,
                'type' => $isGroup ? 'group' : 'direct',
                'title' => $isGroup ? (trim((string) ($data['title'] ?? '')) ?: null) : null,
            ]);
            $conversation->participants()->attach($participantIds->push($user->id)->unique()->all(), ['created_at' => now(), 'updated_at' => now()]);
            $conversation->messages()->create(['user_id' => $user->id, 'body' => trim($data['message'])]);
            return $conversation;
        });

        ClinicActivityLogger::recordManual($customerId, 'Chat interno', 'created', 'conversación',
            $conversation->title ?: ($patient ? 'Paciente: ' . $patient->name : 'Conversación'),
            'Creó una conversación en el chat interno.', [], ChatConversation::class, $conversation->id);

        return response()->json(['redirect' => route('customers.chat.index', ['customer' => request()->route('customer'), 'conversation' => $conversation->id])], 201);
    }

    public function messages(Request $request, string $customer, int $conversation): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $chat = $this->conversationForUser($conversation, $customerId, $user->id);
        $after = max(0, (int) $request->query('after', 0));
        $before = max(0, (int) $request->query('before', 0));
        $around = max(0, (int) $request->query('around', 0));
        $hasMoreOlder = false;
        $hasMoreNewer = false;
        if ($after > 0) {
            $batch = $this->visibleMessages($chat, $user->id)->where('id', '>', $after)->with(['user:id,name', 'replyTo.user:id,name', 'forwardedFrom.user:id,name', 'reactions:id,message_id,user_id,emoji'])->orderBy('id')->take(101)->get();
            $hasMoreNewer = $batch->count() > 100;
            $messages = $batch->take(100);
        } elseif ($around > 0) {
            $older = $this->visibleMessages($chat, $user->id)->where('id', '<', $around)->with(['user:id,name', 'replyTo.user:id,name', 'forwardedFrom.user:id,name', 'reactions:id,message_id,user_id,emoji'])->orderByDesc('id')->take(25)->get()->reverse();
            $target = $this->visibleMessages($chat, $user->id)->whereKey($around)->with(['user:id,name', 'replyTo.user:id,name', 'forwardedFrom.user:id,name', 'reactions:id,message_id,user_id,emoji'])->first();
            abort_unless($target, 404);
            $newerBatch = $this->visibleMessages($chat, $user->id)->where('id', '>', $around)->with(['user:id,name', 'replyTo.user:id,name', 'forwardedFrom.user:id,name', 'reactions:id,message_id,user_id,emoji'])->orderBy('id')->take(26)->get();
            $hasMoreNewer = $newerBatch->count() > 25;
            $newer = $newerBatch->take(25);
            $messages = $older->push($target)->concat($newer)->values();
            $hasMoreOlder = $this->visibleMessages($chat, $user->id)->where('id', '<', $messages->first()?->id ?? $around)->exists();
        } elseif ($before > 0) {
            $batch = $this->visibleMessages($chat, $user->id)->where('id', '<', $before)->with(['user:id,name', 'replyTo.user:id,name', 'forwardedFrom.user:id,name', 'reactions:id,message_id,user_id,emoji'])->orderByDesc('id')->take(51)->get();
            $hasMoreOlder = $batch->count() > 50;
            $messages = $batch->take(50)->reverse()->values();
        } else {
            $batch = $this->visibleMessages($chat, $user->id)->with(['user:id,name', 'replyTo.user:id,name', 'forwardedFrom.user:id,name', 'reactions:id,message_id,user_id,emoji'])->latest('id')->take(51)->get();
            $hasMoreOlder = $batch->count() > 50;
            $messages = $batch->take(50)->reverse()->values();
        }
        if (!$this->isTenantSuperAdmin($user) && $chat->participants()->where('users.id', $user->id)->exists()) {
            $chat->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
        }

        $otherNames = $chat->participants->where('id', '!=', $user->id)->pluck('name')->take(3)->implode(', ');
        $administratorName = $chat->participants->firstWhere('id', $chat->created_by)?->name ?? $chat->creator?->name ?? 'Administrador';
        $conversationData = [
            'id' => $chat->id,
            'type' => $chat->type === 'group' || $chat->participants->count() > 2 ? 'group' : $chat->type,
            'title' => $chat->title ?: ($chat->patient ? 'Paciente: ' . $chat->patient->name : ($otherNames ?: 'Conversación')),
            'branch' => $this->conversationBranchLabel($chat, (int) $user->id, $this->activeBranchId($customerId)),
            'created_by' => (int) $chat->created_by,
            'administrator' => $administratorName,
            'patient_id' => $chat->patient_id,
            'patient_name' => $chat->patient?->name,
            'participants' => $chat->participants->map(fn ($participant) => [
                'id' => (int) $participant->id,
                'name' => $participant->name,
                'email' => $participant->email,
                'branch_names' => $participant->branches->pluck('name')->values(),
                'left_at' => $participant->pivot?->left_at,
                'is_admin' => (int) $participant->id === (int) $chat->created_by,
            ])->values(),
            'can_manage' => $this->isTenantSuperAdmin($user)
                || ($this->hasChatPermission($user, $customerId, 'chat.manage')
                    && (int) $chat->created_by === (int) $user->id
                    && $chat->participants->firstWhere('id', $user->id)?->pivot?->left_at === null),
            'can_send' => $this->isTenantSuperAdmin($user)
                || ($this->hasChatPermission($user, $customerId, 'chat.send')
                    && $chat->participants->firstWhere('id', $user->id)?->pivot?->left_at === null),
            'can_delete' => $this->isTenantSuperAdmin($user) || $chat->participants->contains('id', $user->id),
            'has_more_older' => $hasMoreOlder,
            'has_more_newer' => $hasMoreNewer,
            'add_url' => route('customers.chat.participants.add', ['customer' => $customer, 'conversation' => $chat->id]),
            'remove_url_template' => route('customers.chat.participants.remove', ['customer' => $customer, 'conversation' => $chat->id, 'participant' => '__PARTICIPANT__']),
            'patient_url' => $chat->patient ? route('patients.medical-record.show', ['customer' => $customer, 'id' => $chat->patient_id]) : null,
            'search_url' => route('customers.chat.search', ['customer' => $customer, 'conversation' => $chat->id]),
            'hide_url' => route('customers.chat.hide', ['customer' => $customer, 'conversation' => $chat->id]),
        ];

        return response()->json([
            'data' => $messages->map(fn ($message) => $this->messagePayload($message, $user->id)),
            'has_more_older' => $hasMoreOlder,
            'has_more_newer' => $hasMoreNewer,
            'conversation' => $conversationData,
        ]);
    }

    public function searchMessages(Request $request, string $customer, int $conversation): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $chat = $this->conversationForUser($conversation, $customerId, $user->id);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = mb_strtolower(trim($data['q']));
        $matches = [];
        $matchCount = 0;

        // Message bodies are encrypted at rest, so matching happens in bounded batches after decryption.
        $this->visibleMessages($chat, $user->id)->whereNull('deleted_at')->with('user:id,name')->orderBy('id')->chunkById(300, function ($batch) use (&$matches, &$matchCount, $term, $user) {
            foreach ($batch as $message) {
                if (mb_stripos(mb_strtolower((string) $message->body), $term) === false) continue;
                $payload = $this->messagePayload($message, $user->id);
                $payload['body'] = mb_strlen($payload['body']) > 280 ? mb_substr($payload['body'], 0, 280) . '…' : $payload['body'];
                $matches[] = $payload;
                $matchCount++;
                if (count($matches) > 100) array_shift($matches);
            }
        });

        return response()->json(['data' => array_reverse($matches), 'limited' => $matchCount > 100]);
    }

    public function send(Request $request, string $customer, int $conversation): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $this->assertChatWriteAccess($user);
        $chat = $this->conversationForUser($conversation, $customerId, $user->id);
        $this->assertActiveParticipant($chat, $user->id);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'reply_to_id' => ['nullable', 'integer'],
        ]);
        $body = trim($data['message']);
        if ($body === '') throw ValidationException::withMessages(['message' => 'Escribe un mensaje antes de enviarlo.']);
        $replyTo = !empty($data['reply_to_id']) ? $chat->messages()->find($data['reply_to_id']) : null;
        if (!empty($data['reply_to_id']) && !$replyTo) {
            throw ValidationException::withMessages(['reply_to_id' => 'El mensaje que intentas responder no pertenece a esta conversación.']);
        }

        $message = DB::transaction(function () use ($chat, $user, $body, $replyTo) {
            $message = $chat->messages()->create(['user_id' => $user->id, 'body' => $body, 'reply_to_id' => $replyTo?->id]);
            DB::table('chat_participants')->where('conversation_id', $chat->id)->whereNull('left_at')->update(['hidden_at' => null]);
            $chat->touch();
            return $message->load(['user:id,name', 'replyTo.user:id,name', 'forwardedFrom.user:id,name']);
        });

        try {
            event(new ChatMessageSent($message, $customerId));
        } catch (Throwable $exception) {
            // A stopped Reverb process must not make an already-saved message look unsent.
            Log::warning('Chat message was saved, but its Reverb broadcast failed.', [
                'customer_id' => $customerId,
                'conversation_id' => $chat->id,
                'message_id' => $message->id,
                'exception' => $exception::class,
            ]);
        }

        ClinicActivityLogger::recordManual($customerId, 'Chat interno', 'created', 'mensaje',
            $chat->title ?: ($chat->patient?->name ? 'Paciente: ' . $chat->patient->name : 'Conversación'),
            'Envió un mensaje en el chat interno.', [], ChatConversation::class, $chat->id);

        return response()->json(['data' => $this->messagePayload($message, $user->id)], 201);
    }

    public function forward(Request $request, string $customer): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $this->assertChatWriteAccess($user);
        $data = $request->validate([
            'message_id' => ['required', 'integer'],
            'recipient_ids' => ['nullable', 'array', 'max:30'],
            'recipient_ids.*' => ['integer', 'distinct'],
            'conversation_ids' => ['nullable', 'array', 'max:100'],
            'conversation_ids.*' => ['integer', 'distinct'],
        ]);
        $recipientIds = collect($data['recipient_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $conversationIds = collect($data['conversation_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        if ($recipientIds->isEmpty() && $conversationIds->isEmpty()) {
            throw ValidationException::withMessages(['recipients' => 'Selecciona al menos una persona o conversación.']);
        }
        if ($recipientIds->contains($user->id)) {
            throw ValidationException::withMessages(['recipient_ids' => 'No puedes reenviarte el mensaje a ti mismo.']);
        }

        $source = ChatMessage::query()->whereNull('deleted_at')->whereDoesntHave('userDeletions', fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('conversation', fn ($query) => $query
            ->where('customer_id', $customerId)
            ->whereHas('participants', fn ($participants) => $participants->where('users.id', $user->id)->whereNull('chat_participants.left_at')))
            ->with('user:id,name')->findOrFail($data['message_id']);

        $validRecipients = User::query()->where('customer_id', $customerId)->where('status', 1)
            ->whereIn('id', $recipientIds)->with('branches')->get();
        if ($validRecipients->count() !== $recipientIds->count()) {
            throw ValidationException::withMessages(['recipient_ids' => 'Selecciona usuarios activos de esta clínica.']);
        }

        $targetConversations = ChatConversation::query()->where('customer_id', $customerId)
            ->whereIn('id', $conversationIds)
            ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id)->whereNull('chat_participants.left_at'))
            ->get();
        if ($targetConversations->count() !== $conversationIds->count()) {
            throw ValidationException::withMessages(['conversation_ids' => 'Selecciona conversaciones a las que pertenezcas.']);
        }
        if ($conversationIds->contains((int) $source->conversation_id)) {
            throw ValidationException::withMessages(['conversation_ids' => 'Elige otra conversación para reenviar este mensaje.']);
        }

        $createdMessages = DB::transaction(function () use ($user, $customerId, $source, $recipientIds, $targetConversations) {
            $targets = $targetConversations->keyBy('id');
            foreach ($recipientIds as $recipientId) {
                $target = $this->findOrCreateDirectConversation($customerId, $user->id, $recipientId);
                $targets->put($target->id, $target);
            }
            return $targets->map(function (ChatConversation $target) use ($user, $source) {
                $message = $target->messages()->create([
                    'user_id' => $user->id,
                    'body' => $source->body,
                    'forwarded_from_message_id' => $source->id,
                ]);
                DB::table('chat_participants')->where('conversation_id', $target->id)->whereNull('left_at')->update(['hidden_at' => null]);
                $target->touch();
                return $message->load(['user:id,name', 'replyTo.user:id,name', 'forwardedFrom.user:id,name']);
            })->values();
        });

        foreach ($createdMessages as $message) {
            try { event(new ChatMessageSent($message, $customerId)); }
            catch (Throwable $exception) {
                Log::warning('Forwarded chat message was saved, but its Reverb broadcast failed.', [
                    'customer_id' => $customerId,
                    'conversation_id' => $message->conversation_id,
                    'message_id' => $message->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        ClinicActivityLogger::recordManual($customerId, 'Chat interno', 'created', 'mensaje reenviado',
            'Mensaje del chat interno', 'Reenvió un mensaje a ' . $createdMessages->count() . ' destino(s).', [],
            ChatConversation::class, $source->conversation_id);

        return response()->json(['message' => 'Mensaje reenviado.', 'data' => $createdMessages->map(fn ($message) => [
            'conversation_id' => (int) $message->conversation_id,
            'title' => $message->conversation?->title,
        ])]);
    }

    public function toggleReaction(Request $request, string $customer, int $conversation, int $message): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $this->assertChatWriteAccess($user);
        $chat = $this->conversationForUser($conversation, $customerId, $user->id);
        $this->assertActiveParticipant($chat, $user->id);
        $data = $request->validate([
            'emoji' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (!is_string($value) || preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $value) !== 1) {
                        $fail('Selecciona un emoji válido.');
                    }
                },
            ],
        ]);
        $chatMessage = $chat->messages()->findOrFail($message);
        abort_if($chatMessage->deleted_at, 422, 'No puedes reaccionar a un mensaje eliminado.');

        DB::transaction(function () use ($chatMessage, $user, $data) {
            $currentReaction = $chatMessage->reactions()->where('user_id', $user->id)->where('emoji', $data['emoji'])->exists();
            $chatMessage->reactions()->where('user_id', $user->id)->delete();
            if (!$currentReaction) {
                $chatMessage->reactions()->create(['user_id' => $user->id, 'emoji' => $data['emoji']]);
            }
        });

        $reactions = $chatMessage->reactions()->get(['id', 'message_id', 'user_id', 'emoji'])
            ->groupBy('emoji')->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'mine' => $group->contains(fn ($reaction) => (int) $reaction->user_id === (int) $user->id),
            ])->values()->all();

        try {
            event(new ChatMessageReactionChanged($customerId, (int) $chat->id, (int) $chatMessage->id, $reactions));
        } catch (Throwable $exception) {
            Log::warning('Chat reaction was saved, but its Reverb broadcast failed.', [
                'customer_id' => $customerId,
                'conversation_id' => $chat->id,
                'message_id' => $chatMessage->id,
                'exception' => $exception::class,
            ]);
        }

        return response()->json(['message_id' => (int) $chatMessage->id, 'reactions' => $reactions]);
    }

    public function hideConversation(Request $request, string $customer, int $conversation): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $this->assertChatWriteAccess($user);
        $chat = $this->conversationForUser($conversation, $customerId, $user->id);
        $data = $request->validate(['leave_group' => ['nullable', 'boolean'], 'hide_chat' => ['nullable', 'boolean']]);
        $membership = DB::table('chat_participants')->where('conversation_id', $chat->id)->where('user_id', $user->id)->first();
        abort_unless($membership, 404);

        $shouldLeave = $chat->type === 'group' && filter_var($data['leave_group'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $shouldHide = filter_var($data['hide_chat'] ?? true, FILTER_VALIDATE_BOOLEAN);
        DB::transaction(function () use ($chat, $user, $membership, $shouldLeave, $shouldHide) {
            $updates = ['hidden_at' => $shouldHide ? now() : null, 'updated_at' => now()];
            if ($shouldLeave && $membership->left_at === null) $updates['left_at'] = now();
            DB::table('chat_participants')->where('conversation_id', $chat->id)->where('user_id', $user->id)->update($updates);
            if ($shouldLeave && (int) $chat->created_by === (int) $user->id) {
                $nextOwnerId = DB::table('chat_participants')->where('conversation_id', $chat->id)->whereNull('left_at')->where('user_id', '!=', $user->id)->orderBy('user_id')->value('user_id');
                if ($nextOwnerId) $chat->update(['created_by' => $nextOwnerId]);
            }
        });

        return response()->json([
            'message' => $shouldLeave ? 'Saliste del grupo. Puedes consultar el historial, pero ya no interactuar.' : 'Chat eliminado de tu lista.',
            'redirect' => $shouldLeave && !$shouldHide
                ? route('customers.chat.index', ['customer' => $customer, 'conversation' => $chat->id])
                : route('customers.chat.index', ['customer' => $customer]),
        ]);
    }

    public function deleteMessage(Request $request, string $customer, int $conversation, int $message): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $this->assertChatWriteAccess($user);
        $chat = $this->conversationForUser($conversation, $customerId, $user->id);
        $this->assertActiveParticipant($chat, $user->id);
        $chatMessage = $this->visibleMessages($chat, $user->id)->findOrFail($message);
        $data = $request->validate(['scope' => ['required', 'in:me,everyone']]);

        if ($data['scope'] === 'everyone') {
            abort_unless($this->isTenantSuperAdmin($user) || (int) $chatMessage->user_id === (int) $user->id, 403, 'Solo puedes eliminar para todos tus propios mensajes.');
            if (!$chatMessage->deleted_at) {
                $chatMessage->forceFill(['deleted_at' => now(), 'deleted_by' => $user->id])->save();
                $chatMessage->reactions()->delete();
                try { event(new ChatMessageDeleted($customerId, (int) $chat->id, (int) $chatMessage->id)); }
                catch (Throwable $exception) {
                    Log::warning('Chat message deletion was saved, but its Reverb broadcast failed.', ['conversation_id' => $chat->id, 'message_id' => $chatMessage->id, 'exception' => $exception::class]);
                }
            }
        } else {
            $chatMessage->userDeletions()->firstOrCreate(['user_id' => $user->id]);
        }

        return response()->json(['message' => $data['scope'] === 'everyone' ? 'Mensaje eliminado para todos.' : 'Mensaje eliminado de tu vista.', 'scope' => $data['scope'], 'message_id' => (int) $chatMessage->id]);
    }

    private function findOrCreateDirectConversation(int $customerId, int $senderId, int $recipientId): ChatConversation
    {
        $existing = ChatConversation::query()->where('customer_id', $customerId)->where('type', 'direct')->whereNull('patient_id')
            ->whereHas('participants', fn ($query) => $query->where('users.id', $senderId))
            ->whereHas('participants', fn ($query) => $query->where('users.id', $recipientId))
            ->has('participants', '=', 2)->orderByDesc('updated_at')->first();

        if ($existing) return $existing;

        $conversation = ChatConversation::create([
            'customer_id' => $customerId,
            'clinic_branch_id' => $this->activeBranchId($customerId),
            'created_by' => $senderId,
            'type' => 'direct',
        ]);
        $conversation->participants()->attach([$senderId, $recipientId], ['created_at' => now(), 'updated_at' => now()]);
        return $conversation;
    }

    public function addParticipant(Request $request, string $customer, int $conversation): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $this->assertChatWriteAccess($user);
        $chat = $this->conversationForUser($conversation, $customerId, $user->id);
        $this->assertActiveParticipant($chat, $user->id);
        abort_unless($this->isTenantSuperAdmin($user) || (int) $chat->created_by === (int) $user->id, 403, 'Solo el administrador del grupo puede agregar integrantes.');
        $data = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $participantIds = collect($data['participant_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $participants = User::query()->where('customer_id', $customerId)->where('status', 1)->whereIn('id', $participantIds)->get();
        if ($participants->count() !== $participantIds->count()) {
            throw ValidationException::withMessages(['participant_ids' => 'Uno o más usuarios no están disponibles en esta clínica.']);
        }
        $participants->load('branches');

        $activeIds = $chat->participants()->wherePivotNull('left_at')->pluck('users.id')->map(fn ($id) => (int) $id);
        if ($participantIds->intersect($activeIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['participant_ids' => 'Uno o más usuarios ya pertenecen al grupo.']);
        }
        if ($activeIds->count() + $participantIds->count() > 30) {
            throw ValidationException::withMessages(['participant_ids' => 'El grupo alcanzaría el máximo de 30 integrantes.']);
        }

        DB::transaction(function () use ($chat, $participants) {
            foreach ($participants as $participant) {
                $membership = DB::table('chat_participants')->where('conversation_id', $chat->id)->where('user_id', $participant->id)->first();
                if ($membership) {
                    $chat->participants()->updateExistingPivot($participant->id, ['left_at' => null, 'last_read_at' => now()]);
                } else {
                    $chat->participants()->attach($participant->id, ['created_at' => now(), 'updated_at' => now()]);
                }
            }
            if ($chat->type !== 'group') $chat->update(['type' => 'group']);
            $chat->touch();
        });

        $names = $participants->pluck('name')->implode(', ');
        ClinicActivityLogger::recordManual($customerId, 'Chat interno', 'updated', 'integrantes', $names,
            'Agregó integrantes a un grupo.', [], ChatConversation::class, $chat->id);

        return response()->json(['message' => $participants->count() === 1 ? 'Integrante agregado al grupo.' : 'Integrantes agregados al grupo.']);
    }

    public function removeParticipant(Request $request, string $customer, int $conversation, int $participant): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $user = $this->clinicUser($customerId);
        $this->assertChatWriteAccess($user);
        $chat = $this->conversationForUser($conversation, $customerId, $user->id);
        $targetMembership = $chat->participants()->where('users.id', $participant)->first();
        abort_unless($targetMembership, 404);
        $isLeaving = $participant === (int) $user->id;
        if (!$isLeaving) {
            $this->assertActiveParticipant($chat, $user->id);
            abort_unless($this->isTenantSuperAdmin($user) || ($chat->type === 'group' && (int) $chat->created_by === (int) $user->id), 403, 'Solo el administrador del grupo puede quitar integrantes.');
        } else {
            abort_if($targetMembership->pivot?->left_at !== null, 422, 'Ya saliste de esta conversacion.');
        }

        DB::transaction(function () use ($chat, $participant, $isLeaving) {
            if ($isLeaving) {
                $chat->participants()->updateExistingPivot($participant, ['left_at' => now()]);
            } else {
                $chat->participants()->detach($participant);
            }
            if ($isLeaving && $chat->type === 'group' && (int) $chat->created_by === $participant) {
                $nextOwnerId = $chat->participants()->wherePivotNull('left_at')->where('users.id', '!=', $participant)->orderBy('users.id')->value('users.id');
                if ($nextOwnerId) $chat->update(['created_by' => $nextOwnerId]);
            }
            $chat->touch();
        });
        $removedUser = User::query()->find($participant);
        ClinicActivityLogger::recordManual($customerId, 'Chat interno', 'updated', 'integrante', $removedUser?->name ?? 'Integrante',
            $isLeaving ? 'Salió de un grupo.' : 'Quitó un integrante de un grupo.', [], ChatConversation::class, $chat->id);

        return response()->json([
            'message' => $isLeaving ? 'Saliste del grupo. Puedes consultar el historial, pero ya no interactuar.' : 'Integrante eliminado del grupo.',
            'redirect' => $isLeaving ? route('customers.chat.index', ['customer' => $customer, 'conversation' => $chat->id]) : null,
        ]);
    }

    public function searchPatients(Request $request): JsonResponse
    {
        $customerId = (int) app('customer')->id;
        $this->clinicUser($customerId);
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) return response()->json(['data' => []]);

        $patients = Patient::query()->where('customer_id', $customerId)
            ->where(fn ($query) => $query->where('name', 'like', '%' . $term . '%')->orWhere('rut', 'like', '%' . $term . '%'))
            ->orderBy('name')->limit(10)->get(['id', 'name', 'rut']);

        return response()->json(['data' => $patients]);
    }

    private function clinicUser(int $customerId): User
    {
        $user = request()->user();
        abort_unless($user && (
            $this->isTenantSuperAdmin($user)
            || ((int) $user->status === 1 && (int) $user->customer_id === $customerId)
        ), 403);
        return $user;
    }

    private function hasChatPermission(User $user, int $customerId, string $permission): bool
    {
        if ($this->isTenantSuperAdmin($user)) return true;

        $roles = $user->roles()->where('roles.customer_id', $customerId)->get(['roles.id', 'roles.permissions_configured']);
        if ($roles->isEmpty() || $roles->every(fn ($role) => !(bool) $role->permissions_configured)) return true;

        return $user->roles()->where('roles.customer_id', $customerId)
            ->whereHas('permissions', fn ($query) => $query->where('permissions.slug', $permission))
            ->exists();
    }

    private function activeBranchId(int $customerId): ?int
    {
        if ((int) session('active_branch_customer_id') !== $customerId || !session()->has('active_branch_id')) return null;

        $branchId = (int) session('active_branch_id');
        return ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)->whereKey($branchId)->value('id');
    }

    private function activeBranch(int $customerId): ?ClinicBranch
    {
        $branchId = $this->activeBranchId($customerId);
        return $branchId ? ClinicBranch::query()->find($branchId) : null;
    }

    private function scopeConversationsByBranch($query, string $scope, ?int $branchId, int $userId): void
    {
        if ($scope === 'all') return;
        if (!$branchId) {
            $query->when($scope === 'current', fn ($current) => $current->whereRaw('1 = 0'))
                ->when($scope === 'other', fn ($other) => $other->whereNotNull('clinic_branch_id'));
            return;
        }

        $otherParticipant = fn ($participants) => $participants
            ->where('users.id', '!=', $userId)
            ->whereNull('chat_participants.left_at');

        if ($scope === 'current') {
            $query->whereHas('participants', fn ($participants) => $otherParticipant($participants)
                ->whereHas('branches', fn ($branches) => $branches->where('clinic_branches.id', $branchId)))
                ->whereDoesntHave('participants', fn ($participants) => $otherParticipant($participants)
                    ->whereDoesntHave('branches', fn ($branches) => $branches->where('clinic_branches.id', $branchId)));
            return;
        }

        $query->whereHas('participants', fn ($participants) => $otherParticipant($participants)
            ->whereDoesntHave('branches', fn ($branches) => $branches->where('clinic_branches.id', $branchId)));
    }

    private function eligibleChatRecipients(User $sender, int $customerId)
    {
        return User::query()->where('users.customer_id', $customerId)->where('users.status', 1)
            ->where('users.id', '!=', $sender->id);
    }

    private function chatRecipientPayload(User $recipient): array
    {
        return [
            'id' => (int) $recipient->id,
            'name' => $recipient->name,
            'email' => $recipient->email,
            'branch_names' => $recipient->branches->pluck('name')->values(),
        ];
    }

    private function conversationBranchLabel(ChatConversation $conversation, int $userId, ?int $activeBranchId): string
    {
        $isGroup = $conversation->type === 'group' || $conversation->participants->count() > 2 || filled($conversation->title);
        $participants = $conversation->participants->where('id', '!=', $userId);
        $branchIds = $participants->flatMap(fn (User $person) => $person->branches->pluck('id'))
            ->map(fn ($id) => (int) $id)->unique()->values();

        if ($isGroup) {
            if ($branchIds->count() > 1) return 'Todas las sedes';
            if ($activeBranchId && $branchIds->contains($activeBranchId)) return 'Mi sede';
        }

        $branchNames = $participants->flatMap(fn (User $person) => $person->branches->pluck('name'))->unique()->values();

        return $branchNames->isNotEmpty()
            ? $branchNames->implode(', ')
            : ($conversation->clinicBranch?->name ?? 'Sede sin identificar');
    }

    private function isTenantSuperAdmin(User $user): bool
    {
        return $user->customer_id === null
            && $user->roles()->where('roles.name', 'Super Administrador')->exists();
    }

    private function assertChatWriteAccess(User $user): void
    {
        // Superadministrador global: permite operaciones administrativas del chat.
    }

    private function conversationForUser(int $id, int $customerId, int $userId): ChatConversation
    {
        $user = User::query()->findOrFail($userId);
        return ChatConversation::query()->where('customer_id', $customerId)
            ->when(!$this->isTenantSuperAdmin($user), fn ($query) => $query->whereHas('participants', fn ($participants) => $participants->where('users.id', $userId)->whereNull('chat_participants.hidden_at')))
            ->with(['patient:id,name,rut', 'clinicBranch:id,name', 'participants:id,name,email', 'participants.branches:id,name'])->findOrFail($id);
    }

    private function visibleMessages(ChatConversation $chat, int $userId)
    {
        return $chat->messages()->whereDoesntHave('userDeletions', fn ($query) => $query->where('user_id', $userId));
    }

    private function latestVisibleMessage(ChatConversation $chat, int $userId): ?ChatMessage
    {
        return $this->visibleMessages($chat, $userId)->with('user:id,name')->latest('id')->first();
    }

    private function assertActiveParticipant(ChatConversation $chat, int $userId): void
    {
        abort_unless(
            $chat->participants()->where('users.id', $userId)->wherePivotNull('left_at')->exists(),
            403,
            'Saliste de esta conversacion. Puedes revisar el historial, pero no enviar mensajes.'
        );
    }

    private function messagePayload($message, int $userId): array
    {
        $message->loadMissing(['replyTo.user:id,name', 'forwardedFrom.user:id,name', 'reactions:id,message_id,user_id,emoji']);
        return [
            'id' => $message->id,
            'body' => $message->deleted_at ? 'Este mensaje fue eliminado.' : $message->body,
            'is_deleted' => (bool) $message->deleted_at,
            'user' => $message->user?->name ?? 'Usuario',
            'mine' => (int) $message->user_id === $userId,
            'time' => $message->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'reply_to' => $message->replyTo ? [
                'id' => (int) $message->replyTo->id,
                'user' => $message->replyTo->user?->name ?? 'Usuario',
                'body' => $message->replyTo->deleted_at ? 'Este mensaje fue eliminado.' : $message->replyTo->body,
            ] : null,
            'forwarded_from' => $message->forwardedFrom ? [
                'user' => $message->forwardedFrom->user?->name ?? 'Usuario',
            ] : null,
            'reactions' => $message->reactions->groupBy('emoji')->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'mine' => $group->contains(fn ($reaction) => (int) $reaction->user_id === $userId),
            ])->values()->all(),
        ];
    }
}
