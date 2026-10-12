<?php

use App\Http\Controllers\customer\AppointmentController;
use App\Http\Controllers\customer\AllergieController;
use App\Http\Controllers\customer\ChronicDiseasesController;
use App\Http\Controllers\customer\ColorController;
use App\Http\Controllers\customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\customer\EncounterController;
use App\Http\Controllers\customer\InsuranceController;
use App\Http\Controllers\customer\LogoController;
use App\Http\Controllers\customer\MedicalRecordController;
use App\Http\Controllers\customer\PatientController;
use App\Http\Controllers\customer\PreviousSurgerieController;
use App\Http\Controllers\customer\ProfessionalController;
use App\Http\Controllers\customer\ProfessionalScheduleController;
use App\Http\Controllers\customer\RegularMedicationController;
use App\Http\Controllers\customer\SpecialtyController;
use App\Http\Controllers\customer\WebController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\superadmin\CustomerController;
use App\Http\Controllers\superadmin\DashboardController;
use App\Models\PreviousSurgerie;
use App\Models\Specialty;
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
    Route::put('/professional/{id}', [ProfessionalController::class, 'update'])->name('professional.update');
    Route::patch('/professional/{id}/toggle-status', [ProfessionalController::class, 'toggleStatus']);
    Route::delete('/professional/{id}', [ProfessionalController::class, 'destroy']);
   


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

    //ficha clinica 
    Route::get('/patients/{id}/medical-record', [MedicalRecordController::class, 'show'])->name('patients.medical-record.show');

    //alergias paciente
    Route::post('/allergies', [AllergieController::class, 'store'])->name('allergies.store');

    //insurances / previsiones
    Route::get('/insurances', [InsuranceController::class, 'index'])->name('customers.insurances.index');
    Route::get('/insurances-data', [InsuranceController::class, 'getInsurances']);
    Route::post('/insurances', [InsuranceController::class, 'store']);
    Route::put('/insurances/{id}', [InsuranceController::class, 'update']);
    Route::patch('/insurances/{id}/toggle-status', [InsuranceController::class, 'toggleStatus']);
    Route::delete('/insurances/{id}', [InsuranceController::class, 'destroy']);

    //enfermedades cronicas
    Route::post('/chronic-diseases', [ChronicDiseasesController::class, 'store'])->name('chronic_diseases.store');
    //MEDICAMENTOS HABITUALES
    Route::post('/regular-medications', [RegularMedicationController::class, 'store'])->name('regular_medications.store');
    //Cirugías Previas
    Route::post('/previous-surgeries', [PreviousSurgerieController::class, 'store'])->name('previous_surgeries.store');

    //atencion medica
    Route::post('/encounter', [EncounterController::class, 'store'])->name('encounter.store');


    //especialidades
    Route::get('/specialties', [SpecialtyController::class, 'index'])->name('customers.specialties.index');
    Route::get('/specialties-data', [SpecialtyController::class, 'getSpecialties']);
    Route::post('/specialties', [SpecialtyController::class, 'store']);
    Route::put('/specialties/{id}', [SpecialtyController::class, 'update']);
    Route::patch('/specialties/{id}/toggle-status', [SpecialtyController::class, 'toggleStatus']);
    Route::delete('/specialties/{id}', [SpecialtyController::class, 'destroy']);

    //horarios
    Route::post('/professional/{professional}/schedule', [ProfessionalScheduleController::class, 'store']);
    Route::get('/professional/{professional}/schedule', [ProfessionalScheduleController::class, 'getSchedule']);
    Route::delete('/professional/schedule/{id}', [ProfessionalScheduleController::class, 'destroy']);
    Route::get('/customer/schedules/{schedule}/affected-appointments', [ProfessionalScheduleController::class, 'affectedAppointments'])->name('customer.schedules.affected-appointments');
Route::get('/customer/schedules/{schedule}/affected-appointments/data',[ProfessionalScheduleController::class, 'affectedAppointmentsData'])->name('customer.schedules.affected-appointments.data');

    //agenda citas medicas
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('customers.appointments.index');
    Route::get('/appointments/events', [AppointmentController::class, 'events']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/availability', [AppointmentController::class,'availability']);
    Route::get('/professionals/by-specialty', [AppointmentController::class, 'professionalsBySpecialty']);
    Route::post('/customer/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])->name('customer.appointments.cancel');

    //dashboard
    Route::get('/dashboard/statistics', [CustomerDashboardController::class,'statistics'])->name('dashboard.statistics');

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
