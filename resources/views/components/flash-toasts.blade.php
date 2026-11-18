@php
    $flashMessages = [];
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        if (session()->has($type) && is_string(session($type))) {
            $flashMessages[] = ['icon' => $type, 'title' => session($type)];
        }
    }
@endphp
@if(count($flashMessages))
    <script>
        (() => {
            const messages = @js($flashMessages);
            const toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: element => {
                    element.addEventListener('mouseenter', Swal.stopTimer);
                    element.addEventListener('mouseleave', Swal.resumeTimer);
                },
            });
            (async () => {
                for (const message of messages) await toast.fire(message);
            })();
        })();
    </script>
@endif
