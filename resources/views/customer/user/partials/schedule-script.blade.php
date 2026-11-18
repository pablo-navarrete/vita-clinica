        function ProfessionalScheduleModal() {

            return {

                show: false,
                loading: false,
                professional: null,
                branchId: @json((int) ($activeBranchId ?? 0)),
                isHeadquarters: @json($isHeadquarters),

                schedules: [],
                scheduleGroups() {
                    const groups = new Map();
                    this.schedules.forEach(schedule => {
                        const branch = schedule.branch_name || 'Sede sin nombre';
                        if (!groups.has(branch)) groups.set(branch, []);
                        groups.get(branch).push(schedule);
                    });
                    return Array.from(groups, ([branch, items]) => ({ branch, items }));
                },

                days: {
                    0: 'Domingo',
                    1: 'Lunes',
                    2: 'Martes',
                    3: 'Miércoles',
                    4: 'Jueves',
                    5: 'Viernes',
                    6: 'Sábado'
                },
                form: {
                    day_of_week: '',
                    start_time: '',
                    end_time: ''
                },

                open(data) {
                    this.professional = data;
                    this.branchId = @json((int) ($activeBranchId ?? 0));
                    this.show = true;

                    this.loadSchedules();
                },

                close() {
                    this.show = false;
                    this.professional = null;

                    this.schedules = [];

                    this.form = {
                        day_of_week: '',
                        start_time: '',
                        end_time: ''
                    };
                },

                changeBranch() {
                    this.form = { day_of_week: '', start_time: '', end_time: '' };
                    this.loadSchedules();
                },

                canManageSchedule(schedule) {
                    return this.isHeadquarters || Number(schedule.clinic_branch_id) === Number(this.branchId);
                },

                save() {
                    fetch(`/professional/${this.professional.id}/schedule`, {
                            method: 'POST',

                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },

                            body: JSON.stringify({ ...this.form, clinic_branch_id: Number(this.branchId) })

                        })
                        .then(async res => {

                            const data = await res.json();

                            if (!res.ok) {

                                let message = 'Ocurrió un error al guardar el horario';


                                if (data.message) {

                                    message = data.message;

                                }


                                if (data.errors) {

                                    message = Object.values(data.errors)
                                        .flat()
                                        .join('<br>');

                                }


                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    html: message,
                                    confirmButtonText: 'Aceptar'
                                });

                                return;
                            }

                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Horario agregado',
                                showConfirmButton: false,
                                timer: 1500
                            });

                            // Limpiar formulario
                            this.form = {
                                day_of_week: '',
                                start_time: '',
                                end_time: ''
                            };
                            // Recargar horarios
                            this.loadSchedules();

                        });

                },

                loadSchedules() {

                    this.loading = true;

                    fetch(`/professional/${this.professional.id}/schedule?all_branches=1`)
                        .then(res => res.json())
                        .then(data => {
                            this.schedules = data;
                        })
                        .finally(() => {
                            this.loading = false;
                        });

                },


  showAffectedAppointments(schedule) {
    window.VitaNavigation.navigate(`/customer/schedules/${schedule.id}/affected-appointments`);
},

                remove(schedule) {

                    // Tiene citas futuras según lo informado por getSchedule()
   if (schedule.future_appointments_count > 0) {

    const count = schedule.future_appointments_count;

    Swal.fire({
        icon: 'warning',
        title: 'Horario con citas agendadas',
        html: `
            <p class="mb-3">
                Este horario tiene
                <strong>${count}</strong>
                ${count === 1 ? 'cita agendada' : 'citas agendadas'}.
            </p>

            <p class="text-sm text-gray-600 mb-4">
                Antes de eliminar este horario debes gestionar
                las citas afectadas.
            </p>
        `,

        showDenyButton: true,
        showCancelButton: true,
        showConfirmButton: false,

        denyButtonText: 'Gestionar citas afectadas',
        cancelButtonText: 'Cerrar',

        denyButtonColor: '{{ themeColor(600) }}',
        cancelButtonColor: '#374151',

    }).then(result => {

        if (result.isDenied) {
            this.showAffectedAppointments(schedule);
        }

    });

    return;
}

                    // No tiene citas futuras
                    Swal.fire({
                        icon: 'warning',
                        title: '¿Eliminar horario?',
                        text: 'Este horario no tiene citas agendadas. ¿Deseas eliminarlo?',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then(result => {

                        if (!result.isConfirmed) {
                            return;
                        }

                        fetch(`/professional/schedule/${schedule.id}?clinic_branch_id=${encodeURIComponent(schedule.clinic_branch_id)}`, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector(
                                        'meta[name="csrf-token"]'
                                    ).content
                                }
                            })
                            .then(async res => {

                                const data = await res.json();

                                // Backend detectó que aparecieron citas
                                if (!res.ok) {
                                    throw new Error(
                                        data.message || 'No se pudo eliminar el horario.'
                                    );
                                }

                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: 'success',
                                    title: 'Horario eliminado',
                                    showConfirmButton: false,
                                    timer: 1500
                                });

                                // Volver a cargar los horarios
                                this.loadSchedules();

                            })
                            .catch(error => {

                                Swal.fire({
                                    icon: 'warning',
                                    title: 'No se puede eliminar',
                                    text: error.message
                                });

                            });
                    });
                }
            }
        }

