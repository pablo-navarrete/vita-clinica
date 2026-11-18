@foreach(['roles' => 'Administrar roles', 'specialties' => 'Administrar especialidades'] as $kind => $title)
<div data-profile-assignments data-kind="{{ $kind }}" id="profile-{{ $kind }}-modal" hidden
    role="dialog" aria-modal="true" aria-labelledby="profile-{{ $kind }}-title"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    data-roles-url="{{ route('customers.users.roles.show', ['customer' => request()->route('customer'), 'id' => $user->id]) }}"
    data-specialties-url="{{ route('customers.users.specialties.show', ['customer' => request()->route('customer'), 'id' => $user->id]) }}">
    <div class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-6 py-4" style="background-color: {{ themeColor(100) }}">
            <h3 id="profile-{{ $kind }}-title" class="font-semibold text-gray-800">{{ $title }}</h3>
            <button type="button" data-assignment-close aria-label="Cerrar" class="p-2 text-gray-500"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="space-y-5 overflow-y-auto p-6">
            <label class="block text-sm font-medium">Sede
                <select data-assignment-branch class="mt-1 w-full rounded-lg border-gray-300">
                    @foreach($assignmentBranches as $group)
                        <option value="{{ $group['branch']->id }}" @selected($group['branch']->id == $activeBranch?->id)>{{ $group['branch']->name }}</option>
                    @endforeach
                </select>
            </label>
            <p data-assignment-message role="status" class="text-sm text-gray-600"></p>
            <fieldset data-assignment-fields disabled class="space-y-4">
                @if($kind === 'roles')
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($assignmentRoles as $role)
                            <label class="inline-flex items-center gap-2 rounded-lg border p-3 text-sm"><input type="checkbox" data-assignment-role value="{{ $role->id }}" class="rounded border-gray-300">{{ $role->name }}</label>
                        @endforeach
                    </div>
                @else
                    <p data-specialty-help class="text-sm text-gray-500"></p>
                    <label class="block text-sm font-medium">Buscar y seleccionar especialidad
                        <select data-specialty-select class="mt-2 w-full rounded-lg border-gray-300"><option value="">Selecciona una especialidad</option></select>
                    </label>
                    <div data-assignment-specialties class="flex flex-wrap gap-4"></div>
                @endif
            </fieldset>
        </div>
        <div class="flex justify-end gap-3 border-t px-6 py-4">
            <button type="button" data-assignment-close class="rounded-lg bg-gray-100 px-4 py-2 text-sm">Cancelar</button>
            <button type="button" data-save-assignment="{{ $kind }}" disabled class="rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" style="background-color: {{ themeColor(600) }}">{{ $kind === 'roles' ? 'Guardar roles' : 'Guardar especialidades' }}</button>
        </div>
    </div>
</div>
@endforeach
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
<style>[data-profile-assignments][hidden] { display: none !important; }</style>
<style>
#profile-specialties-modal .select2-container { width:100% !important; }
#profile-specialties-modal .select2-selection--single { height:42px; border:1px solid #d1d5db; border-radius:8px; display:flex; align-items:center; }
#profile-specialties-modal .select2-selection__arrow { height:40px; }
</style>
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="{{ asset('js/profile-assignments.js') }}?v={{ filemtime(public_path('js/profile-assignments.js')) }}"></script>
@endpush
