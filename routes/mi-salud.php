<?php

use App\Http\Controllers\MiSalud\AccountController;
use Illuminate\Support\Facades\Route;

Route::domain(config('app.domain'))->prefix('mi-salud')->name('mi-salud.')->group(function() {
    Route::get('/reserva/{intent}',[\App\Http\Controllers\MiSalud\BookingAuthorizationController::class,'show'])->whereUuid('intent')->middleware('throttle:20,1')->name('booking.show');
    Route::post('/reserva/{intent}',[\App\Http\Controllers\MiSalud\BookingAuthorizationController::class,'approve'])->whereUuid('intent')->middleware(\App\Http\Middleware\PortalAccess::class)->middleware('throttle:10,1')->name('booking.approve');
    Route::get('/',[AccountController::class,'home'])->name('home');
    Route::get('/estado-sesion',[AccountController::class,'sessionStatus'])->middleware('throttle:60,1')->name('session-status');
    Route::get('/registro',[AccountController::class,'registerForm'])->name('register');
    Route::post('/registro',[AccountController::class,'register'])->middleware('throttle:5,1,mi-salud-register:')->name('register.store');
    Route::get('/ingresar',[AccountController::class,'loginForm'])->name('login');
    Route::post('/ingresar',[AccountController::class,'login'])->middleware('throttle:12,1,mi-salud-login:')->name('login.store');
    Route::get('/recuperar-acceso',[AccountController::class,'forgotForm'])->name('forgot');
    Route::post('/recuperar-acceso',[AccountController::class,'forgot'])->middleware('throttle:3,1,mi-salud-forgot:')->name('forgot.store');
    Route::get('/enlace/{purpose}',[AccountController::class,'tokenForm'])->whereIn('purpose',['verify','reset'])->name('token');
    Route::post('/enlace/{purpose}',[AccountController::class,'token'])->whereIn('purpose',['verify','reset'])->middleware('throttle:6,1,mi-salud-token:')->name('token.consume');
    Route::middleware(\App\Http\Middleware\PortalAccess::class)->group(function() {
        Route::get('/verificar-correo',[AccountController::class,'verifyNotice'])->name('verify.notice');
        Route::post('/vista-local',[AccountController::class,'localPreview'])->middleware('throttle:3,1,mi-salud-preview:')->name('local-preview');
        Route::post('/verificar-correo',[AccountController::class,'resend'])->middleware('throttle:3,1,mi-salud-resend:')->name('verify.resend');
        Route::post('/salir',[AccountController::class,'logout'])->name('logout');
    });
    Route::middleware(\App\Http\Middleware\PortalAccess::class.':verified')->group(function() {
        Route::get('/agendar',[\App\Http\Controllers\MiSalud\BookingDirectoryController::class,'index'])->name('booking.index');
        Route::post('/agendar/ubicacion',[\App\Http\Controllers\MiSalud\BookingDirectoryController::class,'location'])->middleware('throttle:12,1')->name('booking.location');
        Route::get('/mis-atenciones',[\App\Http\Controllers\MiSalud\ClinicalHistoryController::class,'index'])->name('attentions.index');
        Route::get('/mis-atenciones/{grant}',[\App\Http\Controllers\MiSalud\ClinicalHistoryController::class,'record'])->whereUuid('grant')->name('attentions.record');
        Route::get('/mis-atenciones/{grant}/atenciones/{encounter}',[\App\Http\Controllers\MiSalud\ClinicalHistoryController::class,'encounter'])->whereUuid('grant')->whereNumber('encounter')->name('attentions.encounter');
        Route::get('/mis-atenciones/{grant}/atenciones/{encounter}/documentos/{document}',[\App\Http\Controllers\MiSalud\ClinicalHistoryController::class,'download'])->whereUuid('grant')->whereNumber(['encounter','document'])->middleware('throttle:30,1')->name('attentions.document');
        Route::get('/resenas/{slug}', [\App\Http\Controllers\MiSalud\ReviewController::class, 'show'])->name('reviews.show');
        Route::post('/resenas/{slug}', [\App\Http\Controllers\MiSalud\ReviewController::class, 'store'])->middleware('throttle:5,1')->name('reviews.store');
        Route::delete('/resenas/{slug}', [\App\Http\Controllers\MiSalud\ReviewController::class, 'withdraw'])->middleware('throttle:5,1')->name('reviews.withdraw');
        Route::post('/asistente', [\App\Http\Controllers\MiSalud\AssistantController::class, 'ask'])->middleware('throttle:30,1')->name('assistant.ask');
        Route::get('/panel',[AccountController::class,'panel'])->name('panel');
        Route::get('/perfil',[AccountController::class,'profile'])->name('profile');
        Route::put('/perfil',[AccountController::class,'updateProfile'])->middleware('throttle:6,1,mi-salud-profile:')->name('profile.update');
    });
});
