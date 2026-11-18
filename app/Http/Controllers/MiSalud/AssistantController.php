<?php

namespace App\Http\Controllers\MiSalud;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Portal guidance only: no tenant, patient or clinical queries. */
class AssistantController extends Controller
{
    public function ask(Request $request): JsonResponse
    {
        abort_unless(auth('mi_salud')->check(), 401);
        $data = $request->validate(['message' => ['required', 'string', 'max:1200']]);
        $message = Str::ascii(mb_strtolower($data['message']));
        $reply = 'Soy Viti, tu asistente de Mi Salud. Puedo orientarte sobre tu perfil y tu cuenta, o ayudarte a encontrar clínicas en el directorio público de VITA.';
        if (preg_match('/clientes|superadmin|estadisticas.*vita/', $message)) {
            $reply = 'Estás en Mi Salud, tu espacio de paciente. Desde aquí no puedo consultar estadísticas de clientes de VITA ni datos administrativos de clínicas.';
        } elseif (preg_match('/ficha|documento|examen|receta|atenciones/', $message)) {
            $reply = 'En Mis atenciones puedes consultar las fichas y atenciones firmadas que cada clínica habilite para tu cuenta. Conocer un documento de identidad no concede acceso a una ficha. Yo puedo orientarte, pero no leo tu contenido clínico.';
        } elseif (preg_match('/cita|reserva|agend|telemed/', $message)) {
            $reply = 'En Agendar atención puedes buscar centros por especialidad y ubicación, y abrir la agenda de la clínica para elegir fecha y hora. La disponibilidad y la confirmación se gestionan en la agenda del centro.';
        } elseif (preg_match('/perfil|nombre|correo|identifica/', $message)) {
            $reply = 'En Mi perfil puedes consultar tus datos y las opciones de actualización de tu cuenta personal. Esto no modifica las fichas de ninguna clínica ni verifica automáticamente tu identidad.';
        } elseif (preg_match('/clave|contrasena|acceso/', $message)) {
            $reply = 'Si necesitas recuperar tu acceso, utiliza «¿Olvidaste tu contraseña?» en la pantalla de ingreso de Mi Salud. Nunca compartas tu contraseña ni enlaces de verificación en el chat.';
        }
        return response()->json(['context'=>'mi-salud','reply' => $reply, 'actions' => [
            ['label' => 'Mi perfil', 'url' => route('mi-salud.profile')],
            ['label' => 'Agendar atención', 'url' => route('mi-salud.booking.index')],
            ['label' => 'Mis atenciones', 'url' => route('mi-salud.attentions.index')],
        ]]);
    }
}
