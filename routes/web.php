<?php

use App\Http\Controllers\customer\AssistantController;
use App\Http\Controllers\customer\ColorController;
use App\Http\Controllers\customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\customer\LogoController;
use App\Http\Controllers\customer\PatientController;
use App\Http\Controllers\customer\ProfessionalController;
use App\Http\Controllers\customer\WebController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\superadmin\CustomerController;
use App\Http\Controllers\superadmin\DashboardController;
use Illuminate\Support\Facades\Route;






/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| SUPERADMIN (DOMINIO PRINCIPAL)
|--------------------------------------------------------------------------
| vita-clinica.cl
*/

Route::domain(config('app.domain'))->middleware(['auth'])->group(function () {

    // dashboard superadmin
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // clientes
    Route::get('/customer-data', [CustomerController::class, 'getCustomer']);

    Route::get('/superadmin/customers', [CustomerController::class, 'index'])->name('superadmin.customer');

    Route::post('/customers', [CustomerController::class, 'store']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::patch('/customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus']);
    Route::patch('/customers/{customer}/toggle-payment-status', [CustomerController::class, 'togglePaymentStatus']);
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
    Route::get('/customer-trash', [CustomerController::class, 'getTrash']);
    Route::patch('/customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
});

/*
|--------------------------------------------------------------------------
| TENANTS (SUBDOMINIOS)
|--------------------------------------------------------------------------
| clinica.vita-clinica.cl
*/

Route::domain('{customer}.' . config('app.domain'))->middleware(['auth', 'tenant'])->group(function () {

    Route::get('/dashboard', [CustomerDashboardController::class, 'index'])->name('customers.dashboard.index');
    //color theme
    Route::post('/theme', [ColorController::class, 'update'])->name('theme.update');

    //professional
    Route::get('/professional', [ProfessionalController::class, 'index'])->name('customers.professional.index');
    Route::get('/professional-data', [ProfessionalController::class, 'getProfessional']);
    Route::post('/professional', [ProfessionalController::class, 'store']);
    Route::put('/professional/{id}', [ProfessionalController::class, 'update']);
    Route::patch('/professional/{id}/toggle-status', [ProfessionalController::class, 'toggleStatus']);
    Route::delete('/professional/{id}', [ProfessionalController::class, 'destroy']);

    //assistant
    Route::get('/assistant', [AssistantController::class, 'index'])->name('customers.assistant.index');
    Route::get('/assistant-data', [AssistantController::class, 'getAssistant']);
    Route::post('/assistant', [AssistantController::class, 'store']);
    Route::put('/assistant/{id}', [AssistantController::class, 'update']);
    Route::patch('/assistant/{id}/toggle-status', [AssistantController::class, 'toggleStatus']);
    Route::delete('/assistant/{id}', [AssistantController::class, 'destroy']);

    //patience
    Route::get('/patient', [PatientController::class, 'index'])->name('customers.patient.index');
    Route::get('/patient-data', [PatientController::class, 'getPatient']);
    Route::post('/patient', [PatientController::class, 'store']);
    Route::put('/patient/{id}', [PatientController::class, 'update']);
    Route::patch('/patient/{id}/toggle-status', [PatientController::class, 'toggleStatus']);
    Route::delete('/patient/{id}', [PatientController::class, 'destroy']);

    //logo
    Route::get('/logo', [LogoController::class, 'index']);
    Route::get('/logo-data', [LogoController::class, 'getLogo']);
    Route::post('/logo', [LogoController::class, 'store']);
    Route::post('/logo/{id}', [LogoController::class, 'update']);
    Route::patch('/logo/{id}/toggle-status', [LogoController::class, 'toggleStatus']);
    Route::delete('/logo/{id}', [LogoController::class, 'destroy']);
});


Route::domain('{customer}.' . config('app.domain'))
    ->middleware(['tenant'])
    ->group(function () {

        Route::get('/', [WebController::class, 'index']);
    });
/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
