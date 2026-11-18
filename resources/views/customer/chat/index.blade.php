@extends('layouts.menu-customer')

@section('header')
    <div class="flex items-center gap-3">
        <span class="grid h-10 w-10 place-items-center rounded-xl text-white" style="background: {{ themeColor(600) }}"><i class="fa-solid fa-comments"></i></span>
        <div><h2 class="text-xl font-semibold text-gray-800">Chat interno</h2><p class="text-sm text-gray-500">Conversaciones privadas del equipo de esta clínica</p></div>
    </div>
@endsection

@push('styles')
    <style>
        .chat-participant-search { height: 42px; padding-left: 2.4rem; border-color: #d1d5db; border-radius: .625rem; }
        .chat-participant-search:focus { border-color: {{ themeColor(500) }}; box-shadow: 0 0 0 3px {{ themeColor(100) }}; }
        .chat-participant-option { display: flex; min-width: 0; align-items: center; gap: .65rem; }
        .chat-participant-option__avatar { display: grid; width: 2.25rem; height: 2.25rem; flex: 0 0 auto; place-items: center; border-radius: 9999px; background: {{ themeColor(100) }}; color: {{ themeColor(700) }}; font-size: .75rem; font-weight: 700; }
    </style>
@endpush

@section('content')
<div class="mx-auto max-w-7xl space-y-4 px-3 pb-8 sm:px-6 lg:px-8">
    <section class="rounded-xl border {{ $isSuperAdmin ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-blue-100 bg-blue-50 text-blue-900' }} p-4 text-sm">
        <i class="fa-solid {{ $isSuperAdmin ? 'fa-eye' : 'fa-shield-halved' }} mr-2"></i>
        @if($isSuperAdmin)
            Estás consultando los chats de esta clínica en modo de solo lectura. Las conversaciones de otras clínicas no aparecen aquí.
        @else
            Los mensajes son privados para los participantes y se almacenan cifrados. Los usuarios con permiso para enviar mensajes pueden comunicarse con toda la clínica, incluidas otras sedes. @if($activeBranch)<strong>Sede activa:</strong> {{ $activeBranch->name }}.@endif Si conversan sobre un paciente, usen el chat para coordinar; registren las decisiones clínicas en su atención.
        @endif
    </section>

    <details class="rounded-xl bg-white p-4 shadow-sm {{ !$canSendMessages ? 'hidden' : '' }}" id="new-chat-details">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-gray-800">
            <span><i class="fa-solid fa-square-pen mr-2" style="color: {{ themeColor(600) }}"></i>Nueva conversación</span>
            <i class="fa-solid fa-chevron-down text-sm text-gray-400"></i>
        </summary>
        <form id="new-chat-form" class="mt-4 grid gap-4 border-t pt-4 md:grid-cols-2">
            @csrf
            <input type="hidden" id="chat-conversation-type" name="conversation_type" value="direct">
            <div class="md:col-span-2">
                <div class="flex flex-wrap gap-2" role="group" aria-label="Tipo de conversación">
                    <button type="button" id="chat-mode-direct" aria-pressed="true" class="rounded-lg border px-4 py-2 text-sm font-semibold transition"><i class="fa-solid fa-user mr-2"></i>Nuevo chat</button>
                    <button type="button" id="chat-mode-group" aria-pressed="false" class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50"><i class="fa-solid fa-user-group mr-2"></i>Nuevo grupo</button>
                </div>
                <div class="mt-4">
                    <p id="chat-participants-label" class="text-sm font-medium text-gray-700">Selecciona una persona</p>
                    <p id="participants-help" class="mt-1 text-xs text-gray-500">Elige con quién quieres conversar.</p>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200">
                            <div class="border-b bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">Usuarios disponibles</div>
                            <label class="relative block p-2">
                                <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input id="chat-user-search" type="search" autocomplete="off" class="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Buscar por nombre" aria-label="Buscar usuario">
                            </label>
                            <div id="chat-user-picker" class="max-h-64 space-y-2 overflow-y-auto p-2">
                                @foreach ($users as $user)
                                    @php
                                        $initials = collect(explode(' ', trim($user->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                                    @endphp
                                    <button type="button" class="chat-user-option flex w-full min-w-0 items-center gap-3 rounded-xl border border-gray-200 p-2.5 text-left transition hover:border-gray-300 hover:bg-gray-50" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" aria-pressed="false">
                                        <span data-presence-user="{{ $user->id }}" class="chat-user-avatar grid h-10 w-10 shrink-0 place-items-center rounded-full text-sm font-bold" style="background: {{ themeColor(100) }}; color: {{ themeColor(700) }}">{{ $initials }}</span>
                                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-gray-700">{{ $user->name }}</span><span class="block truncate text-xs text-gray-500">{{ $user->branches->pluck('name')->implode(', ') ?: 'Sin sede asignada' }}</span></span>
                                        <i class="fa-solid fa-plus shrink-0 text-xs" style="color: {{ themeColor(600) }}"></i>
                                    </button>
                                @endforeach
                            </div>
                            <p id="chat-user-search-empty" class="hidden px-3 pb-3 text-sm text-gray-500">No se encontraron usuarios con ese nombre.</p>
                        </section>
                        <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200">
                            <div class="border-b bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">Integrantes seleccionados</div>
                            <div id="chat-selected-picker" class="flex max-h-64 min-h-[140px] flex-wrap content-start gap-2 overflow-y-auto p-3">
                                <p id="chat-selected-empty" class="self-center text-sm text-gray-400">Selecciona usuarios para agregarlos aquí.</p>
                            </div>
                        </section>
                    </div>
                    <div id="chat-selected-participants"></div>
                    <p id="chat-selection-count" class="mt-2 text-xs font-medium text-gray-500"></p>
                </div>
            </div>
            <div class="space-y-4">
                <label id="chat-group-title-wrap" class="hidden text-sm font-medium text-gray-700">Nombre del grupo <span class="font-normal text-gray-400">(opcional)</span>
                    <input name="title" maxlength="120" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="Ej.: Coordinación de recepción">
                </label>
                <div class="relative">
                    <label for="chat-patient-search" class="block text-sm font-medium text-gray-700">Vincular paciente <span class="font-normal text-gray-400">(opcional)</span></label>
                    <input id="chat-patient-search" autocomplete="off" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="Buscar por nombre o RUT">
                    <input type="hidden" name="patient_id" id="chat-patient-id">
                    <div id="chat-patient-results" class="absolute z-20 mt-1 hidden max-h-52 w-full overflow-y-auto rounded-lg border bg-white shadow-lg"></div>
                    <p id="chat-patient-selected" class="mt-1 hidden text-xs text-green-700"></p>
                </div>
            </div>
            <label class="text-sm font-medium text-gray-700 md:col-span-2">Mensaje inicial
                <textarea name="message" rows="3" maxlength="5000" required class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="Escribe el primer mensaje..."></textarea>
            </label>
            <div class="md:col-span-2">
                <button class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:brightness-95" style="background: {{ themeColor(600) }}"><i class="fa-solid fa-paper-plane"></i>Crear conversación</button>
            </div>
        </form>
    </details>

    <section id="chat-workspace" data-messages-url-template="{{ route('customers.chat.messages', ['customer' => request()->route('customer'), 'conversation' => '__ID__']) }}" data-delete-url-template="{{ route('customers.chat.messages.delete', ['customer' => request()->route('customer'), 'conversation' => '__ID__', 'message' => '__MESSAGE__']) }}" data-hide-url-template="{{ route('customers.chat.hide', ['customer' => request()->route('customer'), 'conversation' => '__ID__']) }}" data-read-only="{{ !$canSendMessages ? '1' : '0' }}" data-conversation-search-url="{{ route('customers.chat.conversations.search', ['customer' => request()->route('customer')]) }}" data-chat-index-url="{{ route('customers.chat.index', ['customer' => request()->route('customer'), 'branch_scope' => $branchScope]) }}" data-branch-scope="{{ $branchScope }}" class="grid min-h-[620px] overflow-hidden rounded-xl bg-white shadow-sm lg:h-[min(78vh,850px)] lg:min-h-[560px] lg:grid-cols-[320px_minmax(0,1fr)]">
        <aside class="flex max-h-[280px] min-h-0 flex-col border-b lg:max-h-none lg:border-b-0 lg:border-r">
            <div class="border-b px-4 py-4"><h3 class="font-semibold text-gray-800">Conversaciones</h3><p class="text-xs text-gray-500">{{ $conversations->total() }} {{ $isSuperAdmin ? 'de esta clínica' : 'en las que participas' }}</p></div>
            <nav class="flex gap-1 border-b p-2" aria-label="Filtrar conversaciones por sede">
                @foreach(['all' => 'Todos', 'current' => 'Mi sede', 'other' => 'Otras sedes'] as $scope => $label)
                    <a data-chat-branch-filter href="{{ route('customers.chat.index', ['customer' => request()->route('customer'), 'branch_scope' => $scope]) }}" class="flex-1 rounded-lg px-2 py-2 text-center text-xs font-semibold transition {{ $branchScope === $scope ? 'bg-blue-50 text-blue-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800' }}" @if($branchScope === $scope) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <label class="relative block border-b p-3">
                <i class="fa-solid fa-magnifying-glass absolute left-6 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input id="chat-conversation-search" type="search" autocomplete="off" class="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm" placeholder="Buscar por persona, paciente o grupo" aria-label="Buscar conversaciones">
            </label>
            <div id="chat-conversation-list" class="min-h-0 flex-1 overflow-y-auto">
                @forelse ($conversations as $conversation)
                    @php
                        $otherNames = $conversation->participants->where('id', '!=', auth()->id())->pluck('name')->take(3)->implode(', ');
                        $label = $conversation->title ?: ($conversation->patient ? 'Paciente: ' . $conversation->patient->name : ($otherNames ?: 'Conversación'));
                        $isGroup = $conversation->type === 'group' || $conversation->participants->count() > 2 || filled($conversation->title);
                        $otherParticipants = $conversation->participants->where('id', '!=', auth()->id());
                        $participantBranchIds = $otherParticipants->flatMap(fn ($person) => $person->branches->pluck('id'))->map(fn ($id) => (int) $id)->unique();
                        $recipient = $conversation->participants->firstWhere('id', '!=', auth()->id());
                        $branchLabel = $isGroup
                            ? ($participantBranchIds->count() > 1 ? 'Todas las sedes' : ($activeBranchId && $participantBranchIds->contains($activeBranchId) ? 'Mi sede' : ($conversation->clinicBranch?->name ?? 'Sede sin identificar')))
                            : ($recipient?->branches->pluck('name')->implode(', ') ?: ($conversation->clinicBranch?->name ?? 'Sede sin identificar'));
                    @endphp
                    <a data-chat-conversation-link data-messages-url="{{ route('customers.chat.messages', ['customer' => request()->route('customer'), 'conversation' => $conversation->id]) }}" href="{{ route('customers.chat.index', ['customer' => request()->route('customer'), 'conversation' => $conversation->id, 'branch_scope' => $branchScope]) }}"
                       class="block border-b px-4 py-3 transition hover:bg-gray-50 {{ $activeConversation?->id === $conversation->id ? 'bg-blue-50' : '' }}">
                        <div class="flex items-start justify-between gap-2"><span class="truncate text-sm font-semibold text-gray-800">{{ $label }}</span><span class="shrink-0 text-[11px] text-gray-400">{{ $conversation->updated_at->timezone(config('app.timezone'))->format('d/m H:i') }}</span></div>
                        <div class="mt-1 text-xs text-amber-700"><i class="fa-solid fa-location-dot mr-1"></i>{{ $isGroup ? 'Grupo' : 'Directo' }} · {{ $branchLabel }}</div>
                        @if($conversation->patient)<div class="mt-1 text-xs text-indigo-700"><i class="fa-solid fa-user-injured mr-1"></i>Vinculada a paciente</div>@endif
                        <p class="mt-1 truncate text-xs text-gray-500">{{ $conversation->latestMessage?->user?->name ? $conversation->latestMessage->user->name . ': ' : '' }}{{ $conversation->latestMessage?->body ?? 'Sin mensajes' }}</p>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-gray-500"><i class="fa-regular fa-comments mb-3 block text-3xl text-gray-300"></i>{{ $branchScope === 'current' ? 'No tienes conversaciones de tu sede todavía.' : ($branchScope === 'other' ? 'No tienes conversaciones de otras sedes todavía.' : 'Aún no tienes conversaciones.') }}</div>
                @endforelse
            </div>
            <div id="chat-conversation-search-results" class="hidden min-h-0 flex-1 overflow-y-auto"></div>
            @if($conversations->hasPages())<div id="chat-conversation-pagination" class="border-t px-3 py-2">{{ $conversations->links() }}</div>@endif
        </aside>

        <div id="chat-main" class="flex min-h-0 min-w-0 flex-col">
            @if($activeConversation)
                @php
                    $activeOtherNames = $activeConversation->participants->where('id', '!=', auth()->id())->pluck('name')->implode(', ');
                    $activeTitle = $activeConversation->title ?: ($activeConversation->patient ? 'Paciente: ' . $activeConversation->patient->name : ($activeOtherNames ?: 'Conversación'));
                @endphp
                @php
                    $activeParticipants = $activeConversation->participants;
                    $isGroupConversation = $activeConversation->type === 'group' || $activeParticipants->count() > 2 || filled($activeConversation->title);
                    $activeOtherParticipants = $activeParticipants->where('id', '!=', auth()->id());
                    $activeParticipantBranchIds = $activeOtherParticipants->flatMap(fn ($person) => $person->branches->pluck('id'))->map(fn ($id) => (int) $id)->unique();
                    $activeRecipient = $activeParticipants->firstWhere('id', '!=', auth()->id());
                    $activeBranchLabel = $isGroupConversation
                        ? ($activeParticipantBranchIds->count() > 1 ? 'Todas las sedes' : ($activeBranchId && $activeParticipantBranchIds->contains($activeBranchId) ? 'Mi sede' : ($activeConversation->clinicBranch?->name ?? 'Sede sin identificar')))
                        : ($activeRecipient?->branches->pluck('name')->implode(', ') ?: ($activeConversation->clinicBranch?->name ?? 'Sede sin identificar'));
                    $canSendToActiveConversation = $canSendMessages && $activeParticipants->firstWhere('id', auth()->id())?->pivot?->left_at === null;
                    $isChatAdmin = (int) $activeConversation->created_by === (int) auth()->id() && $canSendToActiveConversation;
                    $participantPreview = $activeParticipants->take(2)->pluck('name');
                    $participantRemainder = max(0, $activeParticipants->count() - $participantPreview->count());
                @endphp
                <header class="shrink-0 border-b px-5 py-4">
                    <div class="flex items-center gap-2">
                    <button id="chat-conversation-details-toggle" type="button" aria-expanded="false" aria-controls="chat-conversation-details" class="flex min-w-0 flex-1 items-center justify-between gap-3 text-left">
                        <span class="min-w-0">
                            <span class="block truncate font-semibold text-gray-900">{{ $activeTitle }}</span>
                            <span class="mt-1 block truncate text-xs text-amber-700">{{ $isGroupConversation ? 'Grupo' : 'Directo' }} · {{ $activeBranchLabel }}</span>
                        
                            <span class="mt-1 block truncate text-xs text-gray-500">{{ $participantPreview->implode(', ') }}{{ $participantRemainder ? ' y ' . $participantRemainder . ' personas más' : '' }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2 text-xs text-gray-500"><span>{{ $activeParticipants->count() }} integrantes</span><i id="chat-conversation-details-icon" class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <button type="button" id="chat-message-search-toggle" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-800" aria-label="Buscar mensajes" title="Buscar mensajes"><i class="fa-solid fa-magnifying-glass"></i></button>
                    @if($canSendMessages)
                    <button type="button" id="chat-hide-conversation" data-group="{{ $isGroupConversation ? '1' : '0' }}" data-left="{{ $canSendToActiveConversation ? '0' : '1' }}" data-url="{{ route('customers.chat.hide', ['customer' => request()->route('customer'), 'conversation' => $activeConversation->id]) }}" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-gray-500 transition hover:bg-red-50 hover:text-red-600" aria-label="Eliminar chat de mi lista" title="Eliminar chat de mi lista"><i class="fa-regular fa-trash-can"></i></button>
                    @endif
                    </div>
                    <div id="chat-message-search-wrap" class="relative mt-3 hidden">
                        <label class="sr-only" for="chat-message-search">Buscar en esta conversación</label>
                        <div class="flex items-center gap-1.5"><div class="relative min-w-0 flex-1"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i><input id="chat-message-search" data-search-url="{{ route('customers.chat.search', ['customer' => request()->route('customer'), 'conversation' => $activeConversation->id]) }}" type="search" autocomplete="off" class="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm" placeholder="Buscar en la conversación"></div><span id="chat-search-position" class="min-w-10 text-center text-xs text-gray-500"></span><button type="button" data-chat-search-step="-1" disabled class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Resultado anterior" title="Resultado anterior"><i class="fa-solid fa-chevron-up"></i></button><button type="button" data-chat-search-step="1" disabled class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Resultado siguiente" title="Resultado siguiente"><i class="fa-solid fa-chevron-down"></i></button></div>
                        <div id="chat-search-results" class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"></div>
                    </div>
                    <div id="chat-conversation-details" class="mt-3 hidden rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Integrantes de la conversación</h4>
                        <ul class="max-h-56 space-y-2 overflow-y-auto">
                            @foreach($activeParticipants as $participant)
                                @php $participantInitials = collect(explode(' ', trim($participant->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''); @endphp
                                <li class="flex min-w-0 items-center gap-3 rounded-lg bg-white p-2">
                                    <span data-presence-user="{{ $participant->id }}" class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-bold" style="background: {{ themeColor(100) }}; color: {{ themeColor(700) }}">{{ $participantInitials }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-gray-800">{{ $participant->name }}{{ (int) $participant->id === (int) auth()->id() ? ' (tú)' : '' }}</span>
                                        @if($isGroupConversation && (int) $participant->id === (int) $activeConversation->created_by)
                                            <span class="mt-0.5 block text-xs font-semibold text-indigo-600">Administrador</span>
                                        @endif
                                        @if($participant->pivot?->left_at)
                                            <span class="block text-xs text-gray-500">Saliste· solo lectura</span>
                                        @endif
                                        <span class="block truncate text-xs text-gray-500">{{ $participant->email ?: 'Sin correo registrado' }}</span>
                                        <span class="block truncate text-xs text-amber-700">{{ $participant->branches->pluck('name')->implode(', ') ?: 'Sin sede asignada' }}</span>
                                    </span>
                                    @if(((int) $participant->id === (int) auth()->id() && !$participant->pivot?->left_at) || ($isChatAdmin && (int) $participant->id !== (int) auth()->id() && !$participant->pivot?->left_at))
                                        <button type="button" class="chat-remove-participant ml-auto shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50" data-url="{{ route('customers.chat.participants.remove', ['customer' => request()->route('customer'), 'conversation' => $activeConversation->id, 'participant' => $participant->id]) }}" data-participant-name="{{ $participant->name }}" data-is-self="{{ (int) $participant->id === (int) auth()->id() ? '1' : '0' }}">
                                            {{ (int) $participant->id === (int) auth()->id() ? ($isGroupConversation ? 'Salir del grupo' : 'Salir del chat') : 'Quitar' }}
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if($isChatAdmin && $activeParticipants->isNotEmpty())
                            <button type="button" class="chat-open-add-members mt-3 inline-flex items-center gap-2 border-t pt-3 text-sm font-semibold hover:underline" style="color: {{ themeColor(700) }}" data-url="{{ route('customers.chat.participants.add', ['customer' => request()->route('customer'), 'conversation' => $activeConversation->id]) }}" data-member-ids="{{ $activeParticipants->filter(fn ($member) => !$member->pivot?->left_at)->pluck('id')->implode(',') }}"><i class="fa-solid fa-user-plus"></i>Agregar integrantes</button>
                        @endif
                    </div>
                    @if($activeConversation->patient)
                        <a class="mt-2 inline-flex text-xs font-medium text-indigo-700 hover:underline" href="{{ route('patients.medical-record.show', ['customer' => request()->route('customer'), 'id' => $activeConversation->patient_id]) }}"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Abrir ficha clínica de {{ $activeConversation->patient->name }}</a>
                    @endif
                </header>
                <div id="chat-messages" data-url="{{ route('customers.chat.messages', ['customer' => request()->route('customer'), 'conversation' => $activeConversation->id]) }}" data-channel="chat.{{ app('customer')->id }}.{{ $activeConversation->id }}" data-user-id="{{ auth()->id() }}" data-can-send="{{ $canSendToActiveConversation ? '1' : '0' }}" data-has-more-older="{{ $hasMoreOlder ? '1' : '0' }}" data-has-more-newer="0" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-gray-50 p-4 sm:p-6">
                    @foreach($messages as $message)
                        <article data-message-id="{{ $message->id }}" class="flex {{ (int)$message->user_id === (int)auth()->id() ? 'justify-end' : 'justify-start' }}">
                            <div class="relative max-w-[85%]">
                                <div class="rounded-2xl px-4 py-2.5 shadow-sm {{ (int)$message->user_id === (int)auth()->id() ? 'rounded-br-md text-white' : 'rounded-bl-md bg-white text-gray-800' }}" @if((int)$message->user_id === (int)auth()->id()) style="background: {{ themeColor(600) }}" @endif>
                                    @if(!$message->deleted_at && $message->forwardedFrom)<p class="mb-1 text-[11px] italic opacity-75"><i class="fa-solid fa-share mr-1"></i>Reenviado de {{ $message->forwardedFrom->user?->name ?? 'Usuario' }}</p>@endif
                                    <p class="mb-1 text-[11px] font-semibold {{ (int)$message->user_id === (int)auth()->id() ? 'text-white/80' : 'text-gray-500' }}">{{ $message->user?->name ?? 'Usuario' }}</p>
                                    @if(!$message->deleted_at && $message->replyTo)<blockquote class="mb-2 rounded-lg border-l-2 border-current/40 bg-black/5 px-2 py-1 text-xs"><span class="block font-semibold">{{ $message->replyTo->user?->name ?? 'Usuario' }}</span><span class="line-clamp-2 whitespace-pre-wrap">{{ $message->replyTo->deleted_at ? 'Este mensaje fue eliminado.' : $message->replyTo->body }}</span></blockquote>@endif
                                    <p class="whitespace-pre-wrap break-words text-sm {{ $message->deleted_at ? 'italic opacity-70' : '' }}">{{ $message->deleted_at ? 'Este mensaje fue eliminado.' : $message->body }}</p>
                                    <time class="mt-1 block text-right text-[10px] {{ (int)$message->user_id === (int)auth()->id() ? 'text-white/70' : 'text-gray-400' }}">{{ $message->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time>
                                </div>
                                @if($canSendMessages && !$message->deleted_at && $canSendToActiveConversation)
                                <div class="mt-1 flex justify-end gap-3 text-[11px] text-gray-500">
                                    <button type="button" class="chat-reply-message hover:text-gray-900" data-message-id="{{ $message->id }}" data-message-user="{{ $message->user?->name ?? 'Usuario' }}" data-message-body="{{ $message->body }}"><i class="fa-solid fa-reply mr-1"></i>Responder</button>
                                    <button type="button" class="chat-forward-message hover:text-gray-900" data-message-id="{{ $message->id }}"><i class="fa-solid fa-share mr-1"></i>Reenviar</button>
                                    <button type="button" class="chat-reaction-toggle hover:text-gray-900" aria-label="Reaccionar" title="Reaccionar"><i class="fa-regular fa-face-smile"></i></button>
                                </div>
                                <div class="chat-reaction-summary mt-1 flex flex-wrap justify-end gap-1">
                                    @foreach($message->reactions->groupBy('emoji') as $reactionEmoji => $reactionGroup)
                                        @php $mineReaction = $reactionGroup->contains(fn ($reaction) => (int) $reaction->user_id === (int) auth()->id()); @endphp
                                        <button type="button" class="chat-reaction-chip inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs {{ $mineReaction ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-white' }}" data-emoji="{{ $reactionEmoji }}"><span>{{ $reactionEmoji }}</span><span>{{ $reactionGroup->count() }}</span>@if($mineReaction)<span class="ml-0.5 font-bold text-gray-500" aria-label="Quitar reacci&#243;n">&times;</span>@endif</button>
                                    @endforeach
                                </div>
                                <div class="chat-reaction-picker absolute bottom-full right-0 z-20 hidden max-h-52 w-72 max-w-[85vw] grid grid-cols-6 gap-1 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-xl">
                                    @foreach(['&#128512;', '&#128514;', '&#128522;', '&#128525;', '&#129392;', '&#128077;', '&#128079;', '&#128591;', '&#10084;&#65039;', '&#127881;', '&#9989;', '&#129300;', '&#128546;', '&#128567;', '&#128138;', '&#129658;', '&#128075;'] as $reactionEntity)
                                        <button type="button" class="chat-react-option rounded p-1 text-lg hover:bg-gray-100" data-emoji="{{ html_entity_decode($reactionEntity, ENT_QUOTES, 'UTF-8') }}">{{ html_entity_decode($reactionEntity, ENT_QUOTES, 'UTF-8') }}</button>
                                    @endforeach
                                </div>
                                @elseif(!$message->deleted_at && $message->reactions->isNotEmpty())
                                <div class="chat-reaction-summary mt-1 flex flex-wrap justify-end gap-1" aria-label="Reacciones">
                                    @foreach($message->reactions->groupBy('emoji') as $reactionEmoji => $reactionGroup)
                                        <span class="chat-reaction-chip inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2 py-0.5 text-xs"><span>{{ $reactionEmoji }}</span><span>{{ $reactionGroup->count() }}</span></span>
                                    @endforeach
                                </div>
                                @endif
                                @if($canSendMessages && $canSendToActiveConversation && !$message->deleted_at)<div class="mt-1 flex justify-end text-[11px] text-gray-500"><button type="button" class="chat-delete-message hover:text-red-600" data-message-id="{{ $message->id }}" data-message-mine="{{ (int)$message->user_id === (int)auth()->id() ? '1' : '0' }}"><i class="fa-regular fa-trash-can mr-1"></i>Eliminar</button></div>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
                @if($canSendToActiveConversation)
                <form id="chat-send-form" data-url="{{ route('customers.chat.send', ['customer' => request()->route('customer'), 'conversation' => $activeConversation->id]) }}" class="border-t bg-white p-3 sm:p-4">
                    @csrf
                    <input type="hidden" name="reply_to_id" id="chat-reply-to-id">
                    <div id="chat-reply-preview" class="mb-2 hidden items-center justify-between rounded-lg border-l-4 bg-gray-50 px-3 py-2" style="border-color: {{ themeColor(600) }}">
                        <div class="min-w-0"><span class="block text-xs font-semibold text-gray-700">Respondiendo a <span id="chat-reply-user"></span></span><span id="chat-reply-body" class="block truncate text-xs text-gray-500"></span></div>
                        <button type="button" id="chat-reply-cancel" class="ml-3 text-gray-500 hover:text-gray-900" aria-label="Cancelar respuesta"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div id="chat-emoji-picker" class="mb-2 hidden grid max-h-52 grid-cols-8 gap-1 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-lg sm:grid-cols-10" aria-label="Emojis">
                        <button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128512;">&#128512;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128514;">&#128514;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128522;">&#128522;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128525;">&#128525;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#129392;">&#129392;</button>
                        <button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128077;">&#128077;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128079;">&#128079;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128591;">&#128591;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#10084;&#65039;">&#10084;&#65039;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#127881;">&#127881;</button>
                        <button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#9989;">&#9989;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#129300;">&#129300;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128546;">&#128546;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128567;">&#128567;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128138;">&#128138;</button>
                        <button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#129658;">&#129658;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128197;">&#128197;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128075;">&#128075;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#128153;">&#128153;</button><button type="button" class="chat-emoji-option rounded p-1 text-xl hover:bg-gray-100" data-emoji="&#10024;">&#10024;</button>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="button" id="chat-emoji-toggle" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border text-xl text-gray-600 hover:bg-gray-50" aria-label="Insertar emoji" title="Insertar emoji">&#128522;</button>
                        <textarea name="message" rows="2" maxlength="5000" required class="min-h-[48px] flex-1 resize-y rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Escribe un mensaje..." aria-label="Mensaje"></textarea>
                        <button type="submit" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-white shadow-sm hover:brightness-95" style="background: {{ themeColor(600) }}" aria-label="Enviar mensaje"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                    <p id="chat-error" class="mt-1 hidden text-xs text-red-600"></p>
                </form>
                @else
                    <div class="border-t bg-gray-50 px-4 py-3 text-center text-sm text-gray-600">Solo lectura: puedes revisar el historial, pero no enviar mensajes.</div>
                @endif
            @else
                <div class="grid flex-1 place-items-center p-8 text-center text-gray-500"><div><i class="fa-regular fa-comments mb-4 text-5xl text-gray-300"></i><h3 class="font-semibold text-gray-700">Tu chat de equipo</h3><p class="mt-1 max-w-sm text-sm">Selecciona una conversación o crea una nueva para hablar con administración y el equipo.</p></div></div>
            @endif
        </div>
    </section>
</div>

<div id="chat-add-members-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-3 sm:p-5" role="dialog" aria-modal="true" aria-labelledby="chat-add-members-title">
    <section class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <header class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6">
            <div><h2 id="chat-add-members-title" class="text-lg font-semibold text-gray-900">Agregar integrantes</h2><p class="mt-1 text-sm text-gray-500">Busca personas y selecciónalas para agregarlas al grupo.</p></div>
            <button type="button" data-close-add-members class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <form id="chat-add-members-form" class="flex min-h-0 flex-1 flex-col" data-url="">
            @csrf
            <div class="grid min-h-0 gap-4 overflow-y-auto p-4 sm:grid-cols-2 sm:p-6">
                <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200">
                    <div class="border-b bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700">Personas disponibles</div>
                    <label class="relative block p-2"><i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i><input id="chat-add-members-search" type="search" autocomplete="off" class="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm" placeholder="Buscar por nombre o correo"></label>
                    <div id="chat-add-members-available" class="max-h-64 space-y-2 overflow-y-auto p-2"></div>
                </section>
                <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200">
                    <div class="flex items-center justify-between border-b bg-gray-50 px-3 py-2"><span class="text-sm font-semibold text-gray-700">Integrantes seleccionados</span><span id="chat-add-members-count" class="text-xs text-gray-500">0 seleccionados</span></div>
                    <div id="chat-add-members-selected" class="flex max-h-64 min-h-[140px] flex-wrap content-start gap-2 overflow-y-auto p-3"><p class="self-center text-sm text-gray-400">Selecciona personas para agregarlas aquí.</p></div>
                </section>
            </div>
            <div id="chat-add-members-hidden"></div>
            <footer class="flex flex-wrap justify-end gap-2 border-t bg-gray-50 px-4 py-3 sm:px-6">
                <button type="button" data-close-add-members class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button id="chat-add-members-submit" type="submit" disabled class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" style="background: {{ themeColor(600) }}"><i class="fa-solid fa-user-plus"></i>Agregar integrantes</button>
            </footer>
        </form>
    </section>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const renderChatEmojis = element => {
        if (!element || !window.twemoji) return element;
        return window.twemoji.parse(element, { base: 'https://cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.3/assets/', folder: 'svg', ext: '.svg', className: 'chat-twemoji' });
    };
    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    const csrf = csrfToken();
    // Registrar este buscador antes de inicializar los controles de creación de chat:
    // esa inicialización no debe impedir la búsqueda de solo lectura del superadmin.
    const conversationSearchInput = document.getElementById('chat-conversation-search');
    const conversationSearchWorkspace = document.getElementById('chat-workspace');
    let conversationSearchTimer;
    conversationSearchInput?.addEventListener('input', event => {
        const input = event.currentTarget;
        clearTimeout(conversationSearchTimer);
        const list = document.getElementById('chat-conversation-list');
        const pagination = document.getElementById('chat-conversation-pagination');
        const results = document.getElementById('chat-conversation-search-results');
        const query = input.value.trim();
        results.replaceChildren();
        if (query.length < 2) {
            results.classList.add('hidden');
            list.classList.remove('hidden');
            pagination?.classList.remove('hidden');
            if (query.length === 1) {
                results.textContent = 'Escribe al menos 2 caracteres para buscar.';
                results.classList.remove('hidden');
                list.classList.add('hidden');
                pagination?.classList.add('hidden');
            }
            return;
        }

        list.classList.add('hidden');
        pagination?.classList.add('hidden');
        results.classList.remove('hidden');
        const loading = document.createElement('p');
        loading.className = 'px-4 py-3 text-sm text-gray-500';
        loading.textContent = 'Buscando conversaciones...';
        results.appendChild(loading);

        conversationSearchTimer = setTimeout(async () => {
            try {
                const response = await fetch(`${conversationSearchWorkspace.dataset.conversationSearchUrl}?q=${encodeURIComponent(query)}&branch_scope=${encodeURIComponent(conversationSearchWorkspace.dataset.branchScope || 'all')}`, { headers: { 'Accept': 'application/json' } });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'No se pudieron buscar las conversaciones.');
                if (conversationSearchInput.value.trim() !== query) return;
                results.replaceChildren();
                (result.data || []).forEach(conversation => {
                    const link = document.createElement('a');
                    const url = new URL(conversationSearchWorkspace.dataset.chatIndexUrl, window.location.origin);
                    url.searchParams.set('conversation', conversation.id);
                    link.href = url.toString();
                    link.className = 'block border-b px-4 py-3 transition hover:bg-gray-50';
                    const title = document.createElement('span');
                    title.className = 'min-w-0 truncate text-sm font-semibold text-gray-800';
                    title.textContent = conversation.title;
                    const time = document.createElement('span');
                    time.className = 'ml-2 shrink-0 text-[11px] text-gray-400';
                    time.textContent = conversation.latest_at || '';
                    const heading = document.createElement('div');
                    heading.className = 'flex items-start justify-between gap-2';
                    heading.append(title, time);
                    link.append(heading);
                    if (conversation.branch) {
                        const branch = document.createElement('span');
                        branch.className = 'mt-1 block truncate text-xs text-amber-700';
                        branch.textContent = `Sede: ${conversation.branch}`;
                        link.append(branch);
                    }
                    const preview = document.createElement('p');
                    preview.className = 'mt-1 truncate text-xs text-gray-500';
                    preview.textContent = `${conversation.latest_user ? `${conversation.latest_user}: ` : ''}${conversation.latest_body || 'Sin mensajes'}`;
                    link.append(preview);
                    results.appendChild(link);
                });
                if (!result.data?.length) {
                    const empty = document.createElement('p');
                    empty.className = 'px-4 py-6 text-center text-sm text-gray-500';
                    empty.textContent = 'No se encontraron conversaciones.';
                    results.appendChild(empty);
                }
            } catch (error) {
                results.replaceChildren();
                const message = document.createElement('p');
                message.className = 'px-4 py-3 text-sm text-red-600';
                message.textContent = error.message;
                results.appendChild(message);
            }
        }, 250);
    });
    const chatUsers = @json($users->map(fn ($user) => ['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email])->values());
    const addMembersModal = document.getElementById('chat-add-members-modal');
    const addMembersForm = document.getElementById('chat-add-members-form');
    const addMembersSearch = document.getElementById('chat-add-members-search');
    const addMembersAvailable = document.getElementById('chat-add-members-available');
    const addMembersSelected = document.getElementById('chat-add-members-selected');
    const addMembersHidden = document.getElementById('chat-add-members-hidden');
    const addMembersCount = document.getElementById('chat-add-members-count');
    const addMembersSubmit = document.getElementById('chat-add-members-submit');
    let addMembersExcluded = new Set();
    const addMembersSelection = new Map();
    const addMemberInitials = name => name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map(part => part.charAt(0).toLocaleUpperCase()).join('') || '?';
    const renderAddMembers = () => {
        const term = normalizeName(addMembersSearch.value);
        const available = chatUsers.filter(person => !addMembersExcluded.has(Number(person.id)) && !addMembersSelection.has(Number(person.id)) && (!term || normalizeName(`${person.name} ${person.email || ''}`).includes(term)));
        addMembersAvailable.replaceChildren();
        if (!available.length) {
            const empty = document.createElement('p'); empty.className = 'px-3 py-4 text-sm text-gray-500'; empty.textContent = 'No hay personas disponibles para agregar.'; addMembersAvailable.appendChild(empty);
        }
        available.forEach(person => {
            const option = document.createElement('button'); option.type = 'button'; option.className = 'chat-participant-option w-full rounded-xl border border-gray-200 p-2.5 text-left transition hover:border-gray-300 hover:bg-gray-50';
            const avatar = document.createElement('span'); avatar.className = 'chat-participant-option__avatar'; avatar.textContent = addMemberInitials(person.name); avatar.dataset.presenceUser = person.id;
            const details = document.createElement('span'); details.className = 'min-w-0 flex-1';
            const name = document.createElement('span'); name.className = 'block truncate text-sm font-medium text-gray-800'; name.textContent = person.name;
            const email = document.createElement('span'); email.className = 'block truncate text-xs text-gray-500'; email.textContent = person.email || 'Sin correo registrado';
            const branch = document.createElement('span'); branch.className = 'block truncate text-xs text-amber-700'; branch.textContent = (person.branch_names || []).join(', ') || 'Sin sede asignada';
            const plus = document.createElement('i'); plus.className = 'fa-solid fa-plus ml-auto text-xs'; plus.style.color = @json(themeColor(600));
            details.append(name, email, branch); option.append(avatar, details, plus);
            option.addEventListener('click', () => { addMembersSelection.set(Number(person.id), person); renderAddMembers(); });
            addMembersAvailable.appendChild(option);
        });
        addMembersSelected.replaceChildren();
        if (!addMembersSelection.size) {
            const empty = document.createElement('p'); empty.className = 'self-center text-sm text-gray-400'; empty.textContent = 'Selecciona personas para agregarlas aquí.'; addMembersSelected.appendChild(empty);
        }
        addMembersSelection.forEach((person, id) => {
            const chip = document.createElement('button'); chip.type = 'button'; chip.className = 'flex max-w-full items-center gap-2 rounded-full border border-gray-200 bg-white py-1.5 pl-1.5 pr-3 text-left text-sm transition hover:border-red-200 hover:bg-red-50'; chip.title = 'Quitar de la selección';
            const avatar = document.createElement('span'); avatar.className = 'chat-participant-option__avatar'; avatar.textContent = addMemberInitials(person.name); avatar.dataset.presenceUser = person.id;
            const name = document.createElement('span'); name.className = 'max-w-40 truncate font-medium text-gray-700'; name.textContent = person.name;
            const remove = document.createElement('i'); remove.className = 'fa-solid fa-xmark text-xs text-gray-400';
            chip.append(avatar, name, remove); chip.addEventListener('click', () => { addMembersSelection.delete(id); renderAddMembers(); }); addMembersSelected.appendChild(chip);
        });
        const count = addMembersSelection.size;
        addMembersCount.textContent = `${count} seleccionado${count === 1 ? '' : 's'}`;
        addMembersSubmit.disabled = count === 0;
        addMembersHidden.replaceChildren(...Array.from(addMembersSelection.keys(), id => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'participant_ids[]'; input.value = id; return input; }));
    };
    const openAddMembers = button => {
        addMembersForm.dataset.url = button.dataset.url;
        addMembersExcluded = new Set((button.dataset.memberIds || '').split(',').filter(Boolean).map(Number));
        addMembersSelection.clear(); addMembersSearch.value = ''; renderAddMembers();
        addMembersModal.classList.remove('hidden'); addMembersModal.classList.add('flex'); document.body.classList.add('overflow-hidden'); addMembersSearch.focus();
    };
    const closeAddMembers = () => { addMembersModal.classList.add('hidden'); addMembersModal.classList.remove('flex'); document.body.classList.remove('overflow-hidden'); };
    addMembersSearch.addEventListener('input', renderAddMembers);
    addMembersModal.addEventListener('click', event => { if (event.target === addMembersModal || event.target.closest('[data-close-add-members]')) closeAddMembers(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !addMembersModal.classList.contains('hidden')) closeAddMembers(); });
    const conversationType = document.getElementById('chat-conversation-type');
    const directButton = document.getElementById('chat-mode-direct');
    const groupButton = document.getElementById('chat-mode-group');
    const userPicker = document.getElementById('chat-user-picker');
    const userSearch = document.getElementById('chat-user-search');
    const userSearchEmpty = document.getElementById('chat-user-search-empty');
    const selectedPicker = document.getElementById('chat-selected-picker');
    const selectedEmpty = document.getElementById('chat-selected-empty');
    const selectedInputs = document.getElementById('chat-selected-participants');
    const selectionCount = document.getElementById('chat-selection-count');
    const participantsHelp = document.getElementById('participants-help');
    const participantsLabel = document.getElementById('chat-participants-label');
    const groupTitle = document.getElementById('chat-group-title-wrap');
    let selectedUsers = new Set();
    const normalizeName = value => value.trim().toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const setMode = mode => {
        conversationType.value = mode;
        if (mode === 'direct' && selectedUsers.size > 1) selectedUsers = new Set([selectedUsers.values().next().value]);
        const isGroup = mode === 'group';
        directButton.setAttribute('aria-pressed', String(!isGroup));
        groupButton.setAttribute('aria-pressed', String(isGroup));
        directButton.classList.toggle('text-white', !isGroup);
        directButton.classList.toggle('text-gray-600', isGroup);
        directButton.style.background = !isGroup ? @json(themeColor(600)) : 'white';
        groupButton.classList.toggle('text-white', isGroup);
        groupButton.classList.toggle('text-gray-600', !isGroup);
        groupButton.style.background = isGroup ? @json(themeColor(600)) : 'white';
        participantsLabel.textContent = isGroup ? 'Selecciona a las personas del grupo' : 'Selecciona una persona';
        participantsHelp.textContent = isGroup ? 'Marca al menos dos personas para crear el grupo.' : 'Elige con quién quieres conversar.';
        groupTitle.classList.toggle('hidden', !isGroup);
        renderSelectedUsers();
    };
    const renderSelectedUsers = () => {
        selectedInputs.replaceChildren();
        selectedPicker.replaceChildren();
        const users = Array.from(userPicker.querySelectorAll('.chat-user-option'));
        users.forEach(button => {
            const id = button.dataset.userId;
            const selected = selectedUsers.has(id);
            const matchesSearch = normalizeName(button.dataset.userName).includes(normalizeName(userSearch.value));
            button.classList.toggle('hidden', selected || !matchesSearch);
            button.setAttribute('aria-pressed', String(selected));
            if (selected) {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'participant_ids[]'; input.value = id;
                selectedInputs.appendChild(input);

                const member = document.createElement('button');
                member.type = 'button'; member.dataset.userId = id;
                member.className = 'flex w-20 flex-col items-center gap-1 rounded-lg p-2 text-center transition hover:bg-gray-50';
                member.title = `Quitar a ${button.dataset.userName}`;
                const avatar = document.createElement('span');
                avatar.dataset.presenceUser = button.dataset.userId;
                avatar.className = 'grid h-12 w-12 place-items-center rounded-full text-sm font-bold text-white shadow-sm';
                avatar.style.background = @json(themeColor(600));
                avatar.textContent = button.querySelector('.chat-user-avatar').textContent.trim();
                const name = document.createElement('span');
                name.className = 'w-full truncate text-xs font-medium text-gray-700';
                name.textContent = button.dataset.userName;
                member.append(avatar, name);
                selectedPicker.appendChild(member);
            }
        });
        const count = selectedUsers.size;
        if (!count) selectedPicker.appendChild(selectedEmpty);
        const visible = users.filter(button => !selectedUsers.has(button.dataset.userId) && normalizeName(button.dataset.userName).includes(normalizeName(userSearch.value))).length;
        userSearchEmpty.classList.toggle('hidden', visible > 0);
        selectionCount.textContent = count ? `${count} ${count === 1 ? 'persona seleccionada' : 'personas seleccionadas'}` : 'Nadie seleccionado';
    };
    directButton?.addEventListener('click', () => setMode('direct'));
    groupButton?.addEventListener('click', () => setMode('group'));
    userPicker?.addEventListener('click', event => {
        const button = event.target.closest('.chat-user-option');
        if (!button) return;
        const id = button.dataset.userId;
        if (selectedUsers.has(id)) selectedUsers.delete(id);
        else if (conversationType.value === 'direct') selectedUsers = new Set([id]);
        else selectedUsers.add(id);
        renderSelectedUsers();
    });
    selectedPicker?.addEventListener('click', event => {
        const member = event.target.closest('button[data-user-id]');
        if (!member) return;
        selectedUsers.delete(member.dataset.userId);
        renderSelectedUsers();
    });
    setMode('direct');
    userSearch?.addEventListener('input', () => {
        renderSelectedUsers();
    });
    const patientSearch = document.getElementById('chat-patient-search');
    const patientResults = document.getElementById('chat-patient-results');
    const patientId = document.getElementById('chat-patient-id');
    const patientSelected = document.getElementById('chat-patient-selected');
    let patientTimer;

    patientSearch?.addEventListener('input', () => {
        clearTimeout(patientTimer);
        patientId.value = '';
        patientSelected.classList.add('hidden');
        const q = patientSearch.value.trim();
        if (q.length < 2) { patientResults.classList.add('hidden'); return; }
        patientTimer = setTimeout(async () => {
            try {
                const response = await fetch(`{{ route('customers.chat.patients.search', ['customer' => request()->route('customer')]) }}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } });
                const result = await response.json();
                patientResults.replaceChildren();
                result.data.forEach(patient => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'block w-full border-b px-3 py-2 text-left text-sm hover:bg-gray-50';
                    option.textContent = `${patient.name} · ${patient.identification_label}`;
                    option.addEventListener('click', () => {
                        patientId.value = patient.id;
                        patientSearch.value = patient.name;
                        patientSelected.textContent = `Conversación vinculada a ${patient.name} (${patient.identification_label}). Solo los participantes de este chat podrán verla.`;
                        patientSelected.classList.remove('hidden');
                        patientResults.classList.add('hidden');
                    });
                    patientResults.appendChild(option);
                });
                if (!result.data.length) {
                    const empty = document.createElement('p'); empty.className = 'px-3 py-2 text-sm text-gray-500'; empty.textContent = 'No se encontraron pacientes.'; patientResults.appendChild(empty);
                }
                patientResults.classList.remove('hidden');
            } catch (_) { patientResults.classList.add('hidden'); }
        }, 250);
    });

    document.getElementById('new-chat-form')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('button[type="submit"]') || form.querySelector('button');
        button.disabled = true;
        try {
            const response = await fetch(`{{ route('customers.chat.store', ['customer' => request()->route('customer')]) }}`, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: new FormData(form)
            });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'No se pudo crear la conversación.');
            const target = new URL(data.redirect, window.location.origin);
            const conversationId = target.searchParams.get('conversation');
            const messagesUrl = document.getElementById('chat-workspace').dataset.messagesUrlTemplate.replace('__ID__', encodeURIComponent(conversationId));
            await activateConversation(data.redirect, messagesUrl, true);
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'No se pudo crear el chat', text: error.message });
            button.disabled = false;
        }
    });

    const workspace = document.getElementById('chat-workspace');
    const chatEmojiList = ['\u{1F600}','\u{1F603}','\u{1F604}','\u{1F601}','\u{1F606}','\u{1F605}','\u{1F602}','\u{1F642}','\u{1F643}','\u{1F609}','\u{1F60A}','\u{1F970}','\u{1F60D}','\u{1F929}','\u{1F618}','\u{1F617}','\u{1F61A}','\u{1F619}','\u{1F60B}','\u{1F61B}','\u{1F61C}','\u{1F92A}','\u{1F61D}','\u{1F911}','\u{1F917}','\u{1F92D}','\u{1FAE2}','\u{1FAE3}','\u{1F92B}','\u{1F914}','\u{1FAE1}','\u{1F910}','\u{1F928}','\u{1F610}','\u{1F611}','\u{1F636}','\u{1FAE5}','\u{1F60F}','\u{1F612}','\u{1F644}','\u{1F62C}','\u{1F925}','\u{1F60C}','\u{1F614}','\u{1F62A}','\u{1F924}','\u{1F634}','\u{1F637}','\u{1F912}','\u{1F915}','\u{1F922}','\u{1F92E}','\u{1F927}','\u{1F975}','\u{1F976}','\u{1F974}','\u{1F635}','\u{1F92F}','\u{1FAE0}','\u{1F60E}','\u{1F978}','\u{1F913}','\u{1F9D0}','\u{1F615}','\u{1FAE4}','\u{1F61F}','\u{1F641}','\u{2639}\u{FE0F}','\u{1F62E}','\u{1F62F}','\u{1F632}','\u{1F633}','\u{1F97A}','\u{1F979}','\u{1F626}','\u{1F627}','\u{1F628}','\u{1F630}','\u{1F625}','\u{1F622}','\u{1F62D}','\u{1F631}','\u{1F616}','\u{1F623}','\u{1F61E}','\u{1F613}','\u{1F629}','\u{1F62B}','\u{1F971}','\u{1F624}','\u{1F621}','\u{1F620}','\u{1F92C}','\u{1F44F}','\u{1F64C}','\u{1F450}','\u{1F932}','\u{1F64F}','\u{1F44D}','\u{1F44E}','\u{1F44A}','\u{270A}','\u{1F91B}','\u{1F91C}','\u{1F91D}','\u{1F44B}','\u{1F91A}','\u{1F590}\u{FE0F}','\u{270B}','\u{1F596}','\u{1F90C}','\u{1F90F}','\u{1F90F}','\u{270C}\u{FE0F}','\u{1F91E}','\u{1FAF0}','\u{1F91F}','\u{1F918}','\u{1F919}','\u{1F4AA}','\u{1F9BE}','\u{270D}\u{FE0F}','\u{1F485}','\u{1FAF6}','\u{2764}\u{FE0F}','\u{1F9E1}','\u{1F49B}','\u{1F49A}','\u{1F499}','\u{1F49C}','\u{1F5A4}','\u{1F90D}','\u{1F90E}','\u{1F494}','\u{1F495}','\u{1F49E}','\u{1F493}','\u{1F497}','\u{1F496}','\u{1F498}','\u{1F49D}','\u{1F49F}','\u{1F48C}','\u{1F4AC}','\u{1F5E8}\u{FE0F}','\u{1F5EF}\u{FE0F}','\u{1F4AD}','\u{1F4A4}','\u{1F440}','\u{1F441}\u{FE0F}','\u{1F9E0}','\u{1F9B7}','\u{1F9B4}','\u{1F440}','\u{1F443}','\u{1FAC0}','\u{1FAC1}','\u{1FA78}','\u{1FA79}','\u{1F9B6}','\u{1F9B5}','\u{1F9B8}','\u{1F9B9}','\u{1F9BA}','\u{1F9BB}','\u{1F9BC}','\u{1F9BD}','\u{1F48A}','\u{1F489}','\u{1F9EC}','\u{1F9A0}','\u{1F9EB}','\u{1F9EA}','\u{1F321}\u{FE0F}','\u{2600}\u{FE0F}','\u{1F324}\u{FE0F}','\u{1F308}','\u{2B50}','\u{1F31F}','\u{2728}','\u{26A1}','\u{1F525}','\u{1F4A7}','\u{1F30A}','\u{1F389}','\u{1F38A}','\u{1F382}','\u{1F381}','\u{1F3C6}','\u{1F947}','\u{2705}','\u{2611}\u{FE0F}','\u{274C}','\u{2753}','\u{2757}','\u{1F4AF}','\u{1F4CC}','\u{1F4CD}','\u{1F4C5}','\u{1F552}','\u{23F0}','\u{1F4DE}','\u{2709}\u{FE0F}','\u{1F3E5}','\u{1FA7B}','\u{1FA7C}','\u{1F9D1}\u{200D}\u{2695}\u{FE0F}','\u{1F436}','\u{1F431}','\u{1F43B}','\u{1F337}','\u{1F339}','\u{1F33B}','\u{1F34E}','\u{1F355}','\u{2615}','\u{1F957}','\u{1F370}','\u{1F697}','\u{2708}\u{FE0F}','\u{1F3E0}','\u{1F6CF}\u{FE0F}','\u{1F4DA}','\u{1F4BB}','\u{1F4F1}','\u{1F512}','\u{1F514}'];
    const chatReactionList = [...new Set(chatEmojiList)];
    const recentEmojiStorageKey = `vita-chat-emojis-${Number(@json(app('customer')->id))}-${Number(@json(auth()->id()))}`;
    const popularEmojis = ['😀','😂','😊','😍','🥰','👍','👏','🙏','❤️','🎉','✅','🤔'];
    const readRecentEmojis = () => {
        try { return JSON.parse(localStorage.getItem(recentEmojiStorageKey) || '[]').filter(emoji => chatReactionList.includes(emoji)).slice(0, 12); }
        catch (_) { return []; }
    };
    const renderEmojiSections = (picker, buttonClass = 'chat-emoji-option') => {
        if (!picker) return;
        const recent = readRecentEmojis();
        const featured = recent.length ? recent : popularEmojis;
        const emojiGridColumns = buttonClass === 'chat-react-option' ? 'grid-cols-6' : 'grid-cols-8 sm:grid-cols-10';
        const makeButtons = emojis => emojis.map(emoji => `<button type="button" class="${buttonClass} grid h-9 w-9 place-items-center rounded-lg text-xl transition hover:bg-gray-100" data-emoji="${emoji}" aria-label="${emoji}">${emoji}</button>`).join('');
        picker.innerHTML = `<div class="col-span-full border-b border-gray-100 pb-1"><div class="mb-1 px-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">${recent.length ? 'Usados recientemente' : 'Más usados'}</div><div class="grid ${emojiGridColumns} gap-1">${makeButtons(featured)}</div></div><div class="col-span-full"><div class="mb-1 px-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Todos los emojis</div><div class="grid ${emojiGridColumns} gap-1">${makeButtons(chatReactionList)}</div></div>`;
        renderChatEmojis(picker);
    };
    const rememberEmoji = emoji => {
        if (!chatReactionList.includes(emoji)) return;
        const recent = [emoji, ...readRecentEmojis().filter(item => item !== emoji)].slice(0, 12);
        try { localStorage.setItem(recentEmojiStorageKey, JSON.stringify(recent)); } catch (_) {}
        document.querySelectorAll('#chat-emoji-picker, .chat-reaction-picker').forEach(picker => renderEmojiSections(picker, picker.classList.contains('chat-reaction-picker') ? 'chat-react-option' : 'chat-emoji-option'));
    };
    const initialEmojiPicker = document.getElementById('chat-emoji-picker');
    renderEmojiSections(initialEmojiPicker);
    workspace.querySelectorAll('.chat-reaction-picker').forEach(picker => {
        picker.classList.add('max-h-52', 'w-72', 'max-w-[85vw]', 'grid', 'grid-cols-6', 'gap-1', 'overflow-y-auto');
        renderEmojiSections(picker, 'chat-react-option');
    });
    renderChatEmojis(initialEmojiPicker);
    renderChatEmojis(workspace);
    let activeEchoChannel = null;
    let loadingConversation = false;
    let canSendToCurrentConversation = document.getElementById('chat-messages')?.dataset.canSend !== '0';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const currentMessageList = () => document.getElementById('chat-messages');
    const messageMarkup = message => {
        const deleted = Boolean(message.is_deleted);
        const quote = message.reply_to ? `<blockquote class="mb-2 rounded-lg border-l-2 border-current/40 bg-black/5 px-2 py-1 text-xs"><span class="block font-semibold">${escapeHtml(message.reply_to.user)}</span><span class="line-clamp-2 whitespace-pre-wrap">${escapeHtml(message.reply_to.body)}</span></blockquote>` : '';
        const forwarded = !deleted && message.forwarded_from ? `<p class="mb-1 text-[11px] italic opacity-75"><i class="fa-solid fa-share mr-1"></i>Reenviado de ${escapeHtml(message.forwarded_from.user)}</p>` : '';
        const reactions = (message.reactions || []).map(reaction => {
            const tag = canSendToCurrentConversation ? 'button' : 'span';
            const buttonAttributes = canSendToCurrentConversation ? ' type="button" data-emoji="' + escapeHtml(reaction.emoji) + '"' : '';
            return `<${tag}${buttonAttributes} class="chat-reaction-chip inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs ${reaction.mine ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-white'}"><span>${escapeHtml(reaction.emoji)}</span><span>${Number(reaction.count)}</span>${reaction.mine && canSendToCurrentConversation ? '<span class="ml-0.5 font-bold text-gray-500" aria-label="Quitar reacci&#243;n">&times;</span>' : ''}</${tag}>`;
        }).join('');
        const picker = '';
        const deleteButton = workspace.dataset.readOnly === '1' || !canSendToCurrentConversation || deleted ? '' : `<div class="mt-1 flex justify-end text-[11px] text-gray-500"><button type="button" class="chat-delete-message hover:text-red-600" data-message-id="${Number(message.id)}" data-message-mine="${message.mine ? '1' : '0'}"><i class="fa-regular fa-trash-can mr-1"></i>Eliminar</button></div>`;
        const actions = !deleted && canSendToCurrentConversation ? `<div class="mt-1 flex justify-end gap-3 text-[11px] text-gray-500"><button type="button" class="chat-reply-message hover:text-gray-900" data-message-id="${Number(message.id)}" data-message-user="${escapeHtml(message.user)}" data-message-body="${escapeHtml(message.body)}"><i class="fa-solid fa-reply mr-1"></i>Responder</button><button type="button" class="chat-forward-message hover:text-gray-900" data-message-id="${Number(message.id)}"><i class="fa-solid fa-share mr-1"></i>Reenviar</button><button type="button" class="chat-reaction-toggle hover:text-gray-900" aria-label="Reaccionar" title="Reaccionar"><i class="fa-regular fa-face-smile"></i></button></div>` : '';
        return `<article data-message-id="${Number(message.id)}" class="flex ${message.mine ? 'justify-end' : 'justify-start'}"><div class="relative max-w-[85%]"><div class="rounded-2xl px-4 py-2.5 shadow-sm ${message.mine ? 'rounded-br-md text-white' : 'rounded-bl-md bg-white text-gray-800'}" ${message.mine ? `style="background:${@json(themeColor(600))}"` : ''}><p class="mb-1 text-[11px] font-semibold ${message.mine ? 'text-white/80' : 'text-gray-500'}">${escapeHtml(message.user)}</p>${!deleted ? forwarded + quote : ''}<p class="whitespace-pre-wrap break-words text-sm ${deleted ? 'italic opacity-70' : ''}">${escapeHtml(message.body)}</p><time class="mt-1 block text-right text-[10px] ${message.mine ? 'text-white/70' : 'text-gray-400'}">${escapeHtml(message.time)}</time></div>${actions}${deleteButton}<div class="chat-reaction-summary mt-1 flex flex-wrap justify-end gap-1">${deleted ? '' : reactions}</div>${!deleted && canSendToCurrentConversation ? `<div class="chat-reaction-picker absolute bottom-full right-0 z-20 hidden max-h-52 w-72 max-w-[85vw] grid grid-cols-6 gap-1 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-xl">${picker}</div>` : ''}</div></article>`;
    };
    const appendMessage = (message, target = currentMessageList(), prepend = false) => {
        if (!target || target.querySelector(`[data-message-id="${message.id}"]`)) return;
        const template = document.createElement('template'); template.innerHTML = messageMarkup(message).trim();
        const article = template.content.firstElementChild;
        article?.querySelectorAll('.chat-reaction-picker').forEach(picker => renderEmojiSections(picker, 'chat-react-option'));
        renderChatEmojis(article);
        if (prepend) target.insertBefore(article, target.firstChild);
        else target.appendChild(article);
    };
    const updateReactionSummary = (messageId, reactions) => {
        const article = workspace.querySelector(`article[data-message-id="${Number(messageId)}"]`);
        const summary = article?.querySelector('.chat-reaction-summary');
        if (!summary) return;
        summary.innerHTML = (reactions || []).map(reaction => {
            const tag = canSendToCurrentConversation ? 'button' : 'span';
            const buttonAttributes = canSendToCurrentConversation ? ' type="button" data-emoji="' + escapeHtml(reaction.emoji) + '"' : '';
            return `<${tag}${buttonAttributes} class="chat-reaction-chip inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs ${reaction.mine ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-white'}"><span>${escapeHtml(reaction.emoji)}</span><span>${Number(reaction.count)}</span>${reaction.mine && canSendToCurrentConversation ? '<span class="ml-0.5 font-bold text-gray-500" aria-label="Quitar reacci&#243;n">&times;</span>' : ''}</${tag}>`;
        }).join('');
        renderChatEmojis(summary);
    };
    const renderDeletedMessage = messageId => {
        const article = workspace.querySelector(`article[data-message-id="${Number(messageId)}"]`);
        if (!article) return;
        const body = article.querySelector('.whitespace-pre-wrap');
        if (body) { body.textContent = 'Este mensaje fue eliminado.'; body.classList.add('italic', 'opacity-70'); }
        article.querySelector('.chat-reply-message')?.remove();
        article.querySelector('.chat-forward-message')?.remove();
        article.querySelector('.chat-reaction-toggle')?.remove();
        article.querySelector('.chat-delete-message')?.remove();
        article.querySelector('.chat-reaction-summary')?.remove();
        article.querySelector('.chat-reaction-picker')?.remove();
        article.dataset.deleted = '1';
    };
    window.addEventListener('vita-chat-message-deleted', event => {
        const list = currentMessageList();
        if (!list || Number(list.dataset.url.match(/\/chat\/(\d+)\/messages/)?.[1]) !== Number(event.detail?.conversationId)) return;
        if (event.detail.scope === 'me') list.querySelector(`article[data-message-id="${Number(event.detail.messageId)}"]`)?.remove();
        else renderDeletedMessage(event.detail.messageId);
    });
    const deleteMessage = async button => {
        if (!canSendToCurrentConversation) return;
        const messageId = button.dataset.messageId;
        const url = workspace.dataset.deleteUrlTemplate.replace('__ID__', encodeURIComponent(currentMessageList()?.dataset.url.match(/\/chat\/(\d+)\/messages/)?.[1] || '')).replace('__MESSAGE__', encodeURIComponent(messageId));
        const mine = button.dataset.messageMine === '1';
        const choice = await Swal.fire({
            icon: 'warning', title: 'Eliminar mensaje', text: mine ? 'Puedes quitarlo de tu vista o eliminarlo para todos.' : 'Este mensaje solo se quitará de tu vista.',
            showCancelButton: true, showDenyButton: mine, confirmButtonText: 'Eliminar para mí', denyButtonText: 'Eliminar para todos', cancelButtonText: 'Cancelar',
            confirmButtonColor: @json(themeColor(600)), denyButtonColor: '#dc2626',
        });
        if (choice.isDismissed) return;
        const scope = choice.isDenied ? 'everyone' : 'me';
        try {
            const response = await fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ scope }) });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'No se pudo eliminar el mensaje.');
            if (scope === 'me') button.closest('article')?.remove(); else renderDeletedMessage(messageId);
            window.dispatchEvent(new CustomEvent('vita-chat-message-deleted', { detail: { conversationId: Number(currentMessageList()?.dataset.url.match(/\/chat\/(\d+)\/messages/)?.[1]), messageId: Number(messageId), scope } }));
        } catch (error) { Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: error.message }); }
    };
    const hideConversation = async button => {
        const isLeft = button.dataset.left === '1';
        const isGroup = button.dataset.group === '1' && !isLeft;
        const choice = await Swal.fire({ icon: 'warning', title: isGroup ? 'Opciones del grupo' : 'Eliminar chat', text: isLeft ? 'Se quitará esta conversación de tu lista. El grupo y su historial seguirán disponibles para los demás integrantes.' : (isGroup ? 'Elige si quieres ocultar este grupo, salir y ocultarlo, o salir conservando el historial.' : 'El chat se quitará solo de tu lista.'), showCancelButton: true, showDenyButton: isGroup, confirmButtonText: 'Eliminar', denyButtonText: 'Eliminar y salir', cancelButtonText: isGroup ? 'Salir' : 'Cancelar', confirmButtonColor: @json(themeColor(600)), denyButtonColor: '#dc2626', cancelButtonColor: '#6b7280' });
        if (choice.isDismissed && !(isGroup && choice.dismiss === Swal.DismissReason.cancel)) return;
        const shouldLeave = isGroup && (choice.isDenied || choice.dismiss === Swal.DismissReason.cancel);
        const shouldHide = !isGroup || choice.isConfirmed || choice.isDenied;
        try {
            const response = await fetch(button.dataset.url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ leave_group: shouldLeave, hide_chat: shouldHide }) });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'No se pudo eliminar el chat.');
            window.VitaNavigation.navigate(result.redirect);
        } catch (error) { Swal.fire({ icon: 'error', title: 'No se pudo eliminar el chat', text: error.message }); }
    };
    const toggleMessageReaction = async (messageId, emoji) => {
        const list = currentMessageList();
        if (!list || !messageId || !emoji) return;
        const messagesUrl = list.dataset.url.replace(/\/messages(?:\?.*)?$/, '');
        try {
            const response = await fetch(`${messagesUrl}/messages/${encodeURIComponent(messageId)}/reactions`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ emoji }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'No se pudo guardar la reacción.');
            updateReactionSummary(result.message_id, result.reactions);
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'No se pudo reaccionar', text: error.message });
        }
    };
    const buildConversationHtml = (conversation, messages) => {
        const participants = conversation.participants || [];
        const preview = participants.slice(0, 2).map(item => escapeHtml(item.name)).join(', ');
        const extra = Math.max(0, participants.length - 2);
        const removeTemplate = conversation.remove_url_template;
        const memberRows = participants.map(participant => {
            const initials = participant.name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part.charAt(0).toLocaleUpperCase()).join('');
            const isSelf = Number(participant.id) === Number(@json(auth()->id()));
            const isLeft = Boolean(participant.left_at);
            const canRemove = (conversation.can_manage && !isSelf) || (conversation.can_send && isSelf && !isLeft);
            const removeButton = canRemove ? `<button type="button" class="chat-remove-participant ml-auto shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50" data-url="${escapeHtml(removeTemplate.replace('__PARTICIPANT__', encodeURIComponent(participant.id)))}" data-participant-name="${escapeHtml(participant.name)}" data-is-self="${isSelf ? '1' : '0'}">${isSelf ? (conversation.type === 'group' ? 'Salir del grupo' : 'Salir del chat') : 'Quitar'}</button>` : '';
            const adminLabel = conversation.type === 'group' && participant.is_admin ? '<span class="mt-0.5 block text-xs font-semibold text-indigo-600">Administrador</span>' : '';
            const leftLabel = isLeft ? '<span class="block text-xs text-gray-500">Salió · solo lectura</span>' : '';
            const email = escapeHtml(participant.email || 'Sin correo registrado');
            const branches = (participant.branch_names || []).map(escapeHtml).join(', ') || 'Sin sede asignada';
            return `<li class="flex min-w-0 items-center gap-3 rounded-lg bg-white p-2"><span data-presence-user="${Number(participant.id)}" class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-bold" style="background:${@json(themeColor(100))};color:${@json(themeColor(700))}">${escapeHtml(initials)}</span><span class="min-w-0"><span class="block truncate text-sm font-medium text-gray-800">${escapeHtml(participant.name)}${isSelf ? ' (tú)' : ''}</span>${adminLabel}${leftLabel}<span class="block truncate text-xs text-gray-500">${email}</span><span class="block truncate text-xs text-amber-700">${branches}</span></span>${removeButton}</li>`;
        }).join('');
        const memberIdsData = participants.filter(item => !item.left_at).map(item => Number(item.id)).join(',');
        const addForm = conversation.can_manage ? `<button type="button" class="chat-open-add-members mt-3 inline-flex items-center gap-2 border-t pt-3 text-sm font-semibold hover:underline" style="color:${@json(themeColor(700))}" data-url="${escapeHtml(conversation.add_url)}" data-member-ids="${memberIdsData}"><i class="fa-solid fa-user-plus"></i>Agregar integrantes</button>` : '';
        const patientLink = conversation.patient_url ? `<a class="mt-2 inline-flex text-xs font-medium text-indigo-700 hover:underline" href="${escapeHtml(conversation.patient_url)}"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Abrir ficha clínica de ${escapeHtml(conversation.patient_name)}</a>` : '';
        const hideControl = !conversation.can_delete ? '' : `<button type="button" id="chat-hide-conversation" data-group="${conversation.type === 'group' ? '1' : '0'}" data-left="${conversation.can_send ? '0' : '1'}" data-url="${escapeHtml(conversation.hide_url)}" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-gray-500 transition hover:bg-red-50 hover:text-red-600" aria-label="Eliminar chat de mi lista" title="Eliminar chat de mi lista"><i class="fa-regular fa-trash-can"></i></button>`;
        const searchControl = `<div id="chat-message-search-wrap" class="relative mt-3 hidden"><label class="sr-only" for="chat-message-search">Buscar en esta conversación</label><div class="flex items-center gap-1.5"><div class="relative min-w-0 flex-1"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i><input id="chat-message-search" data-search-url="${escapeHtml(conversation.search_url)}" type="search" autocomplete="off" class="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm" placeholder="Buscar en la conversación"></div><span id="chat-search-position" class="min-w-10 text-center text-xs text-gray-500"></span><button type="button" data-chat-search-step="-1" disabled class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Resultado anterior" title="Resultado anterior"><i class="fa-solid fa-chevron-up"></i></button><button type="button" data-chat-search-step="1" disabled class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Resultado siguiente" title="Resultado siguiente"><i class="fa-solid fa-chevron-down"></i></button></div><div id="chat-search-results" class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"></div></div>`;
        const branchLabel = conversation.branch ? `<span class="mt-1 block truncate text-xs text-amber-700">Sede: ${escapeHtml(conversation.branch)}</span>` : '';
        const header = `<header class="shrink-0 border-b px-5 py-4"><div class="flex items-center gap-2"><button id="chat-conversation-details-toggle" type="button" aria-expanded="false" aria-controls="chat-conversation-details" class="flex min-w-0 flex-1 items-center justify-between gap-3 text-left"><span class="min-w-0"><span class="block truncate font-semibold text-gray-900">${escapeHtml(conversation.title)}</span>${branchLabel}<span class="mt-1 block truncate text-xs text-gray-500">${preview}${extra ? ` y ${extra} personas más` : ''}</span></span><span class="flex shrink-0 items-center gap-2 text-xs text-gray-500"><span>${participants.length} integrantes</span><i id="chat-conversation-details-icon" class="fa-solid fa-chevron-down"></i></span></button><button type="button" id="chat-message-search-toggle" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-800" aria-label="Buscar mensajes" title="Buscar mensajes"><i class="fa-solid fa-magnifying-glass"></i></button>${hideControl}</div>${searchControl}<div id="chat-conversation-details" class="mt-3 hidden rounded-xl border border-gray-200 bg-gray-50 p-3"><h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Administrar integrantes</h4><ul class="max-h-56 space-y-2 overflow-y-auto">${memberRows}</ul>${addForm}</div>${patientLink}</header>`;
        const renderedMessages = messages.map(messageMarkup).join('');
        const messageUrl = workspace.dataset.messagesUrlTemplate.replace('__ID__', encodeURIComponent(conversation.id));
        const sendUrl = @json(route('customers.chat.send', ['customer' => request()->route('customer'), 'conversation' => '__ID__'])).replace('__ID__', encodeURIComponent(conversation.id));
        const emojiButtons = '';
        return `${header}<div id="chat-messages" data-url="${escapeHtml(messageUrl)}" data-channel="chat.${Number(@json(app('customer')->id))}.${Number(conversation.id)}" data-user-id="${Number(@json(auth()->id()))}" data-can-send="${conversation.can_send ? '1' : '0'}" data-has-more-older="${conversation.has_more_older ? 1 : 0}" data-has-more-newer="${conversation.has_more_newer ? 1 : 0}" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-gray-50 p-4 sm:p-6">${renderedMessages}</div>${conversation.can_send ? `<form id="chat-send-form" data-url="${escapeHtml(sendUrl)}" class="border-t bg-white p-3 sm:p-4"><input type="hidden" name="_token" value="${escapeHtml(csrf)}"><input type="hidden" name="reply_to_id" id="chat-reply-to-id"><div id="chat-reply-preview" class="mb-2 hidden items-center justify-between rounded-lg border-l-4 bg-gray-50 px-3 py-2" style="border-color:${@json(themeColor(600))}"><div class="min-w-0"><span class="block text-xs font-semibold text-gray-700">Respondiendo a <span id="chat-reply-user"></span></span><span id="chat-reply-body" class="block truncate text-xs text-gray-500"></span></div><button type="button" id="chat-reply-cancel" class="ml-3 text-gray-500" aria-label="Cancelar respuesta"><i class="fa-solid fa-xmark"></i></button></div><div id="chat-emoji-picker" class="mb-2 hidden grid max-h-52 grid-cols-8 gap-1 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-lg sm:grid-cols-10">${emojiButtons}</div><div class="flex items-end gap-2"><button type="button" id="chat-emoji-toggle" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border text-xl hover:bg-gray-50" aria-label="Insertar emoji">&#128522;</button><textarea name="message" rows="2" maxlength="5000" required class="min-h-[48px] flex-1 resize-y rounded-xl border-gray-300 text-sm" placeholder="Escribe un mensaje..." aria-label="Mensaje"></textarea><button type="submit" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-white" style="background:${@json(themeColor(600))}" aria-label="Enviar mensaje"><i class="fa-solid fa-paper-plane"></i></button></div><p id="chat-error" class="mt-1 hidden text-xs text-red-600"></p></form>` : `<div class="border-t bg-gray-50 px-4 py-3 text-center text-sm text-gray-600">Solo lectura: puedes revisar el historial, pero no enviar mensajes.</div>`}`;
    };
    const subscribeConversation = channel => {
        if (!window.Echo || !channel || channel === activeEchoChannel) return;
        if (activeEchoChannel) window.Echo.leave(activeEchoChannel);
        activeEchoChannel = channel;
        window.Echo.private(channel).listen('.chat.message.sent', payload => {
            const list = currentMessageList();
            if (!list || list.dataset.channel !== channel) return;
            appendMessage({ id: payload.id, body: payload.body, user: payload.user, mine: Number(payload.user_id) === Number(list.dataset.userId), time: payload.time, reply_to: payload.reply_to, forwarded_from: payload.forwarded_from }, list);
            list.scrollTop = list.scrollHeight;
            window.dispatchEvent(new CustomEvent('vita-chat-message-received', { detail: payload }));
        }).listen('.chat.message.reaction', payload => {
            const list = currentMessageList();
            if (!list || list.dataset.channel !== channel) return;
            updateReactionSummary(payload.message_id, payload.reactions);
        }).listen('.chat.message.deleted', payload => {
            const list = currentMessageList();
            if (!list || list.dataset.channel !== channel) return;
            renderDeletedMessage(payload.message_id);
            window.dispatchEvent(new CustomEvent('vita-chat-message-deleted', { detail: { conversationId: Number(payload.conversation_id), messageId: Number(payload.message_id), scope: 'everyone' } }));
        });
    };
    async function activateConversation(url, messagesUrl, updateHistory = false, highlightId = null, preserveSearch = false) {
        if (loadingConversation) return;
        const searchInputBeforeNavigation = preserveSearch ? document.getElementById('chat-message-search') : null;
        const keepSearchOpen = Boolean(searchInputBeforeNavigation && !document.getElementById('chat-message-search-wrap')?.classList.contains('hidden'));
        const searchQueryBeforeNavigation = searchInputBeforeNavigation?.value || '';
        loadingConversation = true;
        workspace.classList.add('opacity-60', 'pointer-events-none');
        try {
            const response = await fetch(messagesUrl, { headers: { 'Accept': 'application/json' } });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'No se pudo abrir la conversación.');
            const main = document.getElementById('chat-main');
            canSendToCurrentConversation = Boolean(result.conversation.can_send);
            main.innerHTML = buildConversationHtml(result.conversation, result.data || []);
            renderChatEmojis(main);
            main.querySelectorAll('.chat-reaction-picker').forEach(picker => renderEmojiSections(picker, 'chat-react-option'));
            renderEmojiSections(main.querySelector('#chat-emoji-picker'));
            if (keepSearchOpen) {
                const searchWrap = document.getElementById('chat-message-search-wrap');
                const searchInput = document.getElementById('chat-message-search');
                searchWrap?.classList.remove('hidden');
                if (searchInput) searchInput.value = searchQueryBeforeNavigation;
                document.getElementById('chat-message-search-toggle')?.setAttribute('aria-expanded', 'true');
            }
            if (!highlightId) { searchMatches = []; searchIndex = -1; updateSearchPosition(); }
            const list = currentMessageList();
            list.dataset.hasMoreOlder = result.has_more_older ? '1' : '0';
            if (highlightId) {
                const match = list.querySelector(`[data-message-id="${highlightId}"]`);
                match?.scrollIntoView({ block: 'center' });
                match?.classList.add('ring-2', 'ring-yellow-400');
                if (match) setTimeout(() => match.classList.remove('ring-2', 'ring-yellow-400'), 2500);
            } else list.scrollTop = list.scrollHeight;
            subscribeConversation(list.dataset.channel);
            const sidebarList = workspace.querySelector('aside .min-h-0');
            const existingLink = Array.from(workspace.querySelectorAll('[data-chat-conversation-link]')).find(item => new URL(item.href).searchParams.get('conversation') === String(result.conversation.id));
            if (!existingLink && sidebarList) {
                const link = document.createElement('a');
                link.dataset.chatConversationLink = '';
                link.dataset.messagesUrl = list.dataset.url;
                link.href = url;
                link.className = 'block border-b bg-blue-50 px-4 py-3 transition hover:bg-gray-50';
                const loadedMessages = result.data || [];
                const lastMessage = loadedMessages[loadedMessages.length - 1];
                const preview = lastMessage ? `${lastMessage.user}: ${lastMessage.body}` : 'Sin mensajes';
                link.innerHTML = `<div class="flex items-start justify-between gap-2"><span class="truncate text-sm font-semibold text-gray-800">${escapeHtml(result.conversation.title)}</span></div><p class="mt-1 truncate text-xs text-gray-500">${escapeHtml(preview)}</p>`;
                sidebarList.prepend(link);
            }
            workspace.querySelectorAll('[data-chat-conversation-link]').forEach(link => {
                const selected = link.href === new URL(url, window.location.href).href;
                link.classList.toggle('bg-blue-50', selected);
                link.setAttribute('aria-current', selected ? 'page' : 'false');
            });
            if (updateHistory) window.history.pushState({ chat: true }, '', url);
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'No se pudo abrir el chat', text: error.message });
        } finally {
            loadingConversation = false;
            workspace.classList.remove('opacity-60', 'pointer-events-none');
        }
    }
    let branchFilterRequest = 0;
    const applyBranchFilter = async link => {
        const requestId = ++branchFilterRequest;
        const targetUrl = new URL(link.href, window.location.origin);
        const currentConversation = new URL(window.location.href).searchParams.get('conversation');
        if (currentConversation) targetUrl.searchParams.set('conversation', currentConversation);
        link.closest('nav')?.classList.add('pointer-events-none', 'opacity-60');
        try {
            const response = await fetch(targetUrl, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('No se pudo cambiar el filtro de conversaciones.');
            const html = await response.text();
            if (requestId !== branchFilterRequest) return;
            const nextPage = new DOMParser().parseFromString(html, 'text/html');
            const nextWorkspace = nextPage.querySelector('#chat-workspace');
            const nextNav = nextWorkspace?.querySelector('nav[aria-label="Filtrar conversaciones por sede"]');
            const nextList = nextPage.querySelector('#chat-conversation-list');
            const nextPagination = nextPage.querySelector('#chat-conversation-pagination');
            const currentNav = workspace.querySelector('nav[aria-label="Filtrar conversaciones por sede"]');
            const currentList = document.getElementById('chat-conversation-list');
            if (!nextNav || !nextList || !currentNav || !currentList) throw new Error('No se encontró la lista de conversaciones.');

            currentNav.replaceWith(nextNav);
            currentList.innerHTML = nextList.innerHTML;
            const currentPagination = document.getElementById('chat-conversation-pagination');
            if (currentPagination) currentPagination.remove();
            if (nextPagination) currentList.insertAdjacentElement('afterend', nextPagination);
            const nextCount = nextWorkspace.querySelector('aside > div:first-child p');
            const currentCount = workspace.querySelector('aside > div:first-child p');
            if (nextCount && currentCount) currentCount.textContent = nextCount.textContent;

            const scope = targetUrl.searchParams.get('branch_scope') || 'all';
            workspace.dataset.branchScope = scope;
            const indexUrl = new URL(workspace.dataset.chatIndexUrl, window.location.origin);
            indexUrl.searchParams.set('branch_scope', scope);
            workspace.dataset.chatIndexUrl = indexUrl.pathname + indexUrl.search;
            window.history.pushState({}, '', targetUrl.pathname + targetUrl.search);
            const searchResults = document.getElementById('chat-conversation-search-results');
            searchResults?.replaceChildren();
            searchResults?.classList.add('hidden');
            document.getElementById('chat-conversation-list')?.classList.remove('hidden');
            document.getElementById('chat-conversation-pagination')?.classList.remove('hidden');
            const searchInput = document.getElementById('chat-conversation-search');
            if (searchInput) searchInput.value = '';
        } catch (error) {
            if (requestId === branchFilterRequest) Swal.fire({ icon: 'error', title: 'No se pudo filtrar', text: error.message });
        } finally {
            if (requestId === branchFilterRequest) workspace.querySelector('nav[aria-label="Filtrar conversaciones por sede"]')?.classList.remove('pointer-events-none', 'opacity-60');
        }
    };
    workspace.addEventListener('click', event => {
        const branchFilter = event.target.closest('[data-chat-branch-filter]');
        if (branchFilter && !event.ctrlKey && !event.metaKey && !event.shiftKey && event.button === 0) {
            event.preventDefault();
            applyBranchFilter(branchFilter);
            return;
        }
        const deleteButton = event.target.closest('.chat-delete-message');
        if (deleteButton) { deleteMessage(deleteButton); return; }
        const hideButton = event.target.closest('#chat-hide-conversation');
        if (hideButton) { hideConversation(hideButton); return; }
        const reactionToggle = event.target.closest('.chat-reaction-toggle');
        if (reactionToggle) {
            if (!canSendToCurrentConversation) return;
            const article = reactionToggle.closest('article');
            const picker = article?.querySelector('.chat-reaction-picker');
            if (picker) {
                const opening = picker.classList.contains('hidden');
                workspace.querySelectorAll('.chat-reaction-picker:not(.hidden)').forEach(openPicker => openPicker.classList.add('hidden'));
                workspace.querySelectorAll('.chat-reaction-toggle').forEach(toggle => toggle.setAttribute('aria-expanded', 'false'));
                if (opening) {
                    picker.classList.remove('hidden', 'top-full', 'mt-2');
                    picker.classList.add('bottom-full', 'mb-2');
                    const list = currentMessageList();
                    const listRect = list.getBoundingClientRect();
                    const articleRect = article.getBoundingClientRect();
                    const pickerHeight = picker.offsetHeight;
                    const roomAbove = articleRect.top - listRect.top;
                    const roomBelow = listRect.bottom - articleRect.bottom;
                    if (roomAbove < pickerHeight + 8 && roomBelow > roomAbove) {
                        picker.classList.remove('bottom-full', 'mb-2');
                        picker.classList.add('top-full', 'mt-2');
                    }
                    reactionToggle.setAttribute('aria-expanded', 'true');
                } else {
                    reactionToggle.setAttribute('aria-expanded', 'false');
                }
            }
            return;
        }
        const reactionButton = event.target.closest('.chat-react-option, .chat-reaction-chip');
        if (reactionButton) {
            if (!canSendToCurrentConversation) return;
            const article = reactionButton.closest('article');
            article?.querySelector('.chat-reaction-picker')?.classList.add('hidden');
            rememberEmoji(reactionButton.dataset.emoji);
            toggleMessageReaction(article?.dataset.messageId, reactionButton.dataset.emoji);
            return;
        }
        const replyButton = event.target.closest('.chat-reply-message');
        if (replyButton) {
            if (!canSendToCurrentConversation) return;
            const form = document.getElementById('chat-send-form');
            form.querySelector('[name="reply_to_id"]').value = replyButton.dataset.messageId;
            document.getElementById('chat-reply-user').textContent = replyButton.dataset.messageUser;
            document.getElementById('chat-reply-body').textContent = replyButton.dataset.messageBody;
            document.getElementById('chat-reply-preview').classList.remove('hidden');
            form.querySelector('textarea[name="message"]').focus();
            return;
        }
        const forwardButton = event.target.closest('.chat-forward-message');
        if (forwardButton) {
            if (!canSendToCurrentConversation) return;
            const currentId = Number(currentMessageList()?.dataset.url.match(/\/chat\/(\d+)\/messages/)?.[1]);
            window.dispatchEvent(new CustomEvent('vita-chat-forward-open', { detail: { messageId: forwardButton.dataset.messageId, conversationId: currentId } }));
            return;
        }
        if (event.target.closest('#chat-emoji-toggle')) {
            document.getElementById('chat-emoji-picker').classList.toggle('hidden');
            return;
        }
        const emojiButton = event.target.closest('.chat-emoji-option');
        if (emojiButton) {
            rememberEmoji(emojiButton.dataset.emoji);
            const textarea = document.querySelector('#chat-send-form textarea[name="message"]');
            const start = textarea.selectionStart; const end = textarea.selectionEnd;
            textarea.setRangeText(emojiButton.dataset.emoji, start, end, 'end');
            textarea.focus();
            return;
        }
        if (event.target.closest('#chat-reply-cancel')) {
            document.querySelector('#chat-send-form [name="reply_to_id"]').value = '';
            document.getElementById('chat-reply-preview').classList.add('hidden');
            return;
        }
        const link = event.target.closest('[data-chat-conversation-link]');
        if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && event.button === 0) {
            event.preventDefault();
            activateConversation(link.href, link.dataset.messagesUrl, true);
            return;
        }
        if (event.target.closest('#chat-conversation-details-toggle')) {
            const details = document.getElementById('chat-conversation-details');
            const toggle = document.getElementById('chat-conversation-details-toggle');
            const icon = document.getElementById('chat-conversation-details-icon');
            const isOpen = details.classList.toggle('hidden') === false;
            toggle.setAttribute('aria-expanded', String(isOpen));
            icon?.classList.toggle('fa-chevron-down', !isOpen); icon?.classList.toggle('fa-chevron-up', isOpen);
            return;
        }
        const removeButton = event.target.closest('.chat-remove-participant');
        if (removeButton) removeGroupParticipant(removeButton);
    });
    document.addEventListener('click', event => {
        const picker = document.getElementById('chat-emoji-picker');
        const toggle = document.getElementById('chat-emoji-toggle');
        if (!picker || picker.classList.contains('hidden')) return;
        if (picker.contains(event.target) || toggle?.contains(event.target)) return;
        picker.classList.add('hidden');
    });
    document.addEventListener('click', event => {
        const clickedToggle = event.target.closest('.chat-reaction-toggle');
        const clickedPicker = event.target.closest('.chat-reaction-picker');
        workspace.querySelectorAll('.chat-reaction-picker:not(.hidden)').forEach(picker => {
            if (picker === clickedPicker || (clickedToggle && picker === clickedToggle.closest('article')?.querySelector('.chat-reaction-picker'))) return;
            picker.classList.add('hidden');
        });
    });
    workspace.addEventListener('keydown', event => {
        const messageInput = event.target.closest('#chat-send-form textarea[name="message"]');
        if (!messageInput || event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
        event.preventDefault();
        if (messageInput.value.trim()) messageInput.form?.requestSubmit();
    });
    let chatSearchTimer;
    let searchMatches = [];
    let searchIndex = -1;
    const updateSearchPosition = () => {
        const position = document.getElementById('chat-search-position');
        const buttons = workspace.querySelectorAll('[data-chat-search-step]');
        if (position) position.textContent = searchIndex >= 0 ? `${searchIndex + 1}/${searchMatches.length}` : (searchMatches.length ? `0/${searchMatches.length}` : '');
        buttons.forEach(button => { button.disabled = searchMatches.length === 0; });
    };
    const goToSearchIndex = async index => {
        if (!searchMatches.length) return;
        searchIndex = (index + searchMatches.length) % searchMatches.length;
        updateSearchPosition();
        const match = searchMatches[searchIndex];
        const list = currentMessageList();
        const currentUrl = window.location.pathname + window.location.search;
        document.getElementById('chat-search-results')?.classList.add('hidden');
        await activateConversation(currentUrl, `${list.dataset.url}?around=${encodeURIComponent(match.id)}`, false, match.id, true);
        updateSearchPosition();
    };
    workspace.addEventListener('input', event => {
        const input = event.target.closest('#chat-message-search');
        if (!input) return;
        clearTimeout(chatSearchTimer);
        const results = document.getElementById('chat-search-results');
        const query = input.value.trim();
        searchMatches = []; searchIndex = -1; updateSearchPosition();
        results.replaceChildren();
        if (query.length < 2) { results.classList.add('hidden'); return; }
        const loading = document.createElement('p'); loading.className = 'px-3 py-2 text-xs text-gray-500'; loading.textContent = 'Buscando mensajes...';
        results.appendChild(loading); results.classList.remove('hidden');
        chatSearchTimer = setTimeout(async () => {
            try {
                const response = await fetch(`${input.dataset.searchUrl}?q=${encodeURIComponent(query)}`, { headers: { 'Accept': 'application/json' } });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'No se pudo buscar en el chat.');
                if (document.getElementById('chat-message-search')?.value.trim() !== query) return;
                searchMatches = result.data || []; searchIndex = -1; updateSearchPosition();
                results.replaceChildren();
                searchMatches.forEach((match, index) => {
                    const option = document.createElement('button'); option.type = 'button'; option.dataset.chatSearchResult = match.id;
                    option.dataset.chatSearchIndex = index;
                    option.className = 'block w-full border-b px-3 py-2 text-left transition hover:bg-gray-50';
                    const heading = document.createElement('span'); heading.className = 'block text-xs font-semibold text-gray-700'; heading.textContent = `${match.user} · ${match.time}`;
                    const body = document.createElement('span'); body.className = 'mt-1 block whitespace-pre-wrap break-words text-sm text-gray-600'; body.textContent = match.body;
                    option.append(heading, body); results.appendChild(option);
                });
                if (result.limited) { const note = document.createElement('p'); note.className = 'px-3 py-2 text-xs text-gray-500'; note.textContent = 'Se muestran los 100 resultados más recientes.'; results.appendChild(note); }
                if (!result.data.length) { const empty = document.createElement('p'); empty.className = 'px-3 py-2 text-sm text-gray-500'; empty.textContent = 'No hay mensajes que coincidan.'; results.appendChild(empty); }
            } catch (error) {
                results.replaceChildren(); const message = document.createElement('p'); message.className = 'px-3 py-2 text-sm text-red-600'; message.textContent = error.message; results.appendChild(message);
            }
        }, 300);
    });
    workspace.addEventListener('scroll', async event => {
        const list = event.target;
        if (list.id !== 'chat-messages') return;
        if (list.scrollTop + list.clientHeight >= list.scrollHeight - 24 && list.dataset.hasMoreNewer === '1' && list.dataset.loadingNewer !== '1') {
            const newest = list.querySelector('[data-message-id]:last-of-type');
            if (newest) {
                list.dataset.loadingNewer = '1';
                try {
                    const response = await fetch(`${list.dataset.url}?after=${encodeURIComponent(newest.dataset.messageId)}`, { headers: { 'Accept': 'application/json' } });
                    const result = await response.json();
                    if (!response.ok) throw new Error('No se pudieron cargar mensajes posteriores.');
                    result.data.forEach(message => appendMessage(message, list));
                    list.dataset.hasMoreNewer = result.has_more_newer ? '1' : '0';
                    list.scrollTop = list.scrollHeight;
                } catch (_) { list.dataset.hasMoreNewer = '0'; }
                finally { list.dataset.loadingNewer = '0'; }
            }
        }
        if (list.scrollTop > 24 || list.dataset.hasMoreOlder !== '1' || list.dataset.loadingOlder === '1') return;
        const oldest = list.querySelector('[data-message-id]');
        if (!oldest) return;
        list.dataset.loadingOlder = '1';
        const previousHeight = list.scrollHeight;
        try {
            const response = await fetch(`${list.dataset.url}?before=${encodeURIComponent(oldest.dataset.messageId)}`, { headers: { 'Accept': 'application/json' } });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'No se pudieron cargar mensajes anteriores.');
            [...result.data].reverse().forEach(message => appendMessage(message, list, true));
            list.dataset.hasMoreOlder = result.has_more_older ? '1' : '0';
            list.scrollTop += list.scrollHeight - previousHeight;
        } catch (_) {
            list.dataset.hasMoreOlder = '0';
        } finally {
            list.dataset.loadingOlder = '0';
        }
    }, true);
    workspace.addEventListener('click', event => {
        const addMembersButton = event.target.closest('.chat-open-add-members');
        if (addMembersButton) { openAddMembers(addMembersButton); return; }
        if (event.target.closest('#chat-message-search-toggle')) {
            const searchWrap = document.getElementById('chat-message-search-wrap');
            const searchInput = document.getElementById('chat-message-search');
            const toggle = document.getElementById('chat-message-search-toggle');
            const isOpen = searchWrap.classList.toggle('hidden') === false;
            toggle.setAttribute('aria-expanded', String(isOpen));
            if (isOpen) {
                searchInput.focus();
            } else {
                searchInput.value = '';
                document.getElementById('chat-search-results')?.classList.add('hidden');
                searchMatches = [];
                searchIndex = -1;
                updateSearchPosition();
            }
            return;
        }
        const stepButton = event.target.closest('[data-chat-search-step]');
        if (stepButton && searchMatches.length) {
            event.preventDefault();
            const step = Number(stepButton.dataset.chatSearchStep);
            goToSearchIndex(searchIndex < 0 ? (step > 0 ? 0 : searchMatches.length - 1) : searchIndex + step);
            return;
        }
        const resultButton = event.target.closest('[data-chat-search-result]');
        if (resultButton) goToSearchIndex(Number(resultButton.dataset.chatSearchIndex));
    });
    workspace.addEventListener('submit', async event => {
        const form = event.target;
        if (form.id === 'chat-send-form') {
            event.preventDefault();
            const textarea = form.querySelector('textarea'); const button = form.querySelector('button[type="submit"]'); const error = form.querySelector('#chat-error');
            if (!textarea.value.trim()) return;
            button.disabled = true; error.classList.add('hidden');
            try {
                const response = await fetch(form.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: new FormData(form) });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'No se pudo enviar el mensaje.');
                appendMessage(data.data); textarea.value = ''; form.querySelector('[name="reply_to_id"]').value = ''; document.getElementById('chat-reply-preview')?.classList.add('hidden'); currentMessageList().scrollTop = currentMessageList().scrollHeight;
            } catch (exception) { error.textContent = exception.message; error.classList.remove('hidden'); }
            finally { button.disabled = false; textarea.focus(); }
            return;
        }
    });
    addMembersForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!addMembersSelection.size) return;
        addMembersSubmit.disabled = true;
        try {
            const response = await fetch(addMembersForm.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: new FormData(addMembersForm) });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'No se pudieron agregar los integrantes.');
            closeAddMembers();
            Swal.fire({ icon: 'success', title: 'Integrantes agregados', timer: 1200, showConfirmButton: false }).then(() => window.VitaNavigation.refresh());
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'No se pudieron agregar', text: error.message });
            addMembersSubmit.disabled = false;
        }
    });
    async function removeGroupParticipant(button) {
        const leaving = button.dataset.isSelf === '1';
        const confirmation = await Swal.fire({ icon: 'warning', title: leaving ? '¿Salir del grupo?' : '¿Quitar integrante?', text: leaving ? 'Conservarás el grupo y su historial, pero ya no podrás enviar mensajes ni reaccionar.' : 'Se quitará a ' + button.dataset.participantName + ' del grupo.', showCancelButton: true, confirmButtonText: leaving ? 'Salir del grupo' : 'Quitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626' });
        if (!confirmation.isConfirmed) return;
        try {
            const response = await fetch(button.dataset.url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            const data = await response.json(); if (!response.ok) throw new Error(data.message || 'No se pudo actualizar el grupo.');
            if (leaving) { window.VitaNavigation.navigate(data.redirect || @json(route('customers.chat.index', ['customer' => request()->route('customer')]))); return; }
            window.VitaNavigation.refresh();
        } catch (error) { Swal.fire({ icon: 'error', title: 'No se pudo actualizar el grupo', text: error.message }); }
    }
    window.addEventListener('popstate', () => {
        const conversationId = new URLSearchParams(window.location.search).get('conversation');
        if (!conversationId) {
            const firstLink = workspace.querySelector('[data-chat-conversation-link]');
            if (firstLink) activateConversation(firstLink.href, firstLink.dataset.messagesUrl, false);
            return;
        }
        const link = Array.from(workspace.querySelectorAll('[data-chat-conversation-link]')).find(item => new URL(item.href).searchParams.get('conversation') === conversationId);
        if (link) activateConversation(link.href, link.dataset.messagesUrl, false);
    });
    if (currentMessageList()) {
        currentMessageList().scrollTop = currentMessageList().scrollHeight;
        subscribeConversation(currentMessageList().dataset.channel);
    }
    let realtimeSyncTimer;
    window.addEventListener('vita:chat-sync', () => {
        clearTimeout(realtimeSyncTimer);
        realtimeSyncTimer = setTimeout(async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(location.href, {headers:{'X-Requested-With':'XMLHttpRequest'}});
                if (response.ok) {
                    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const nextList = page.getElementById('chat-conversation-list');
                    const currentList = document.getElementById('chat-conversation-list');
                    if (nextList && currentList) currentList.innerHTML = nextList.innerHTML;
                }
                const list = currentMessageList();
                if (!list || loadingConversation) return;
                const responseMessages = await fetch(`${list.dataset.url}?after=0`, {headers:{Accept:'application/json'}});
                if (!responseMessages.ok || list !== currentMessageList()) return;
                for (const message of (await responseMessages.json()).data || []) {
                    const existing = list.querySelector(`[data-message-id="${message.id}"]`);
                    if (!existing) appendMessage(message, list);
                    else {
                        const template = document.createElement('template'); template.innerHTML = messageMarkup(message).trim();
                        const replacement = template.content.firstElementChild;
                        replacement?.querySelectorAll('.chat-reaction-picker').forEach(picker => renderEmojiSections(picker, 'chat-react-option'));
                        renderChatEmojis(replacement);
                        if (replacement) existing.replaceWith(replacement);
                    }
                }
            } catch (_) {}
        }, 250);
    });
    setInterval(async () => {
        const list = currentMessageList();
        if (!list || document.hidden || window.parent.VitaUserRealtime?.ready) return;
        const latestId = Math.max(0, ...Array.from(list.querySelectorAll('[data-message-id]')).map(node => Number(node.dataset.messageId) || 0));
        try {
            const response = await fetch(`${list.dataset.url}?after=${latestId}`, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) return;
            const data = await response.json(); data.data.forEach(message => appendMessage(message, list));
            if (data.data.length) list.scrollTop = list.scrollHeight;
        } catch (_) {}
    }, 5000);
});
</script>
@endpush





