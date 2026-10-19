<?php

use App\Http\Controllers\customer\AppointmentController;
use App\Http\Controllers\customer\AppointmentNotificationController;
use App\Http\Controllers\customer\AllergieController;
use App\Http\Controllers\customer\ChronicDiseasesController;
use App\Http\Controllers\customer\ColorController;
use App\Http\Controllers\customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\customer\EncounterController;
use App\Http\Controllers\customer\InsuranceController;
use App\Http\Controllers\customer\LogoController;
use App\Http\Controllers\customer\MenuController;
use App\Http\Controllers\customer\WebsitePageController;
use App\Http\Controllers\customer\WebsiteFooterController;
use App\Http\Controllers\customer\WebsiteSocialController;
use App\Http\Controllers\customer\WebsiteBannerController;
use App\Http\Controllers\customer\WebsiteTopbarController;
use App\Http\Controllers\customer\WebsiteMaintenanceController;
use App\Http\Controllers\customer\ClinicActivityController;
use App\Http\Controllers\customer\MedicalRecordController;
use App\Http\Controllers\customer\PatientController;
use App\Http\Controllers\customer\PreviousSurgerieController;
use App\Http\Controllers\customer\ProfessionalController;
use App\Http\Controllers\customer\ProfessionalScheduleController;
use App\Http\Controllers\customer\RegularMedicationController;
use App\Http\Controllers\customer\SpecialtyController;
use App\Http\Controllers\customer\WebController;
use App\Http\Controllers\customer\ProfileController;
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

Route::domain(config('app.domain'))->middleware(['auth', 'superadmin'])->group(function () {

    // dashboard superadmin
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics'])->name('superadmin.dashboard.statistics');

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
    Route::middleware('prevent-doctor-system-configuration')->group(function () {
        Route::get('/activity-history', [ClinicActivityController::class, 'index'])->name('customers.activity-history.index');
        // Configuración del sistema
        Route::get('/professional', [ProfessionalController::class, 'index'])->name('customers.professional.index');
        Route::get('/professional-data', [ProfessionalController::class, 'getProfessional']);
        Route::post('/professional', [ProfessionalController::class, 'store']);
        Route::put('/professional/{id}', [ProfessionalController::class, 'update'])->name('professional.update');
        Route::patch('/professional/{id}/toggle-status', [ProfessionalController::class, 'toggleStatus']);
        Route::delete('/professional/{id}', [ProfessionalController::class, 'destroy']);

        // Administración web
        Route::get('/logo', [LogoController::class, 'index'])->name('customers.logo.index');
        Route::get('/logo-data', [LogoController::class, 'getLogo']);
        Route::post('/logo', [LogoController::class, 'store']);
        Route::post('/logo/{id}', [LogoController::class, 'update']);
        Route::patch('/logo/{id}/toggle-status', [LogoController::class, 'toggleStatus']);
        Route::delete('/logo/{id}', [LogoController::class, 'destroy']);

        Route::get('/menus', [MenuController::class, 'index'])->name('customers.menus.index');
        Route::get('/website-footer', [WebsiteFooterController::class, 'edit'])->name('customers.website-footer.edit');
        Route::put('/website-footer', [WebsiteFooterController::class, 'update'])->name('customers.website-footer.update');
        Route::get('/website-topbar', [WebsiteTopbarController::class, 'edit'])->name('customers.website-topbar.edit');
        Route::put('/website-topbar', [WebsiteTopbarController::class, 'update'])->name('customers.website-topbar.update');
        Route::post('/website-maintenance', [WebsiteMaintenanceController::class, 'toggle'])->name('customers.website-maintenance.toggle');
        Route::get('/website-socials', [WebsiteSocialController::class, 'edit'])->name('customers.website-socials.edit');
        Route::put('/website-socials', [WebsiteSocialController::class, 'update'])->name('customers.website-socials.update');
        Route::post('/menus', [MenuController::class, 'store'])->name('customers.menus.store');
        Route::patch('/menus/reorder', [MenuController::class, 'reorder'])->name('customers.menus.reorder');
        Route::patch('/menus/{id}/toggle-status', [MenuController::class, 'toggleStatus'])->name('customers.menus.toggle-status');
        Route::get('/menus/{id}/edit', [MenuController::class, 'edit'])->name('customers.menus.edit');
        Route::put('/menus/{id}', [MenuController::class, 'update'])->name('customers.menus.update');
        Route::delete('/menus/{id}', [MenuController::class, 'destroy'])->name('customers.menus.destroy');
        Route::get('/banners', [WebsiteBannerController::class, 'index'])->name('customers.banners.index');
        Route::post('/banners', [WebsiteBannerController::class, 'store'])->name('customers.banners.store');
        Route::patch('/banners/reorder', [WebsiteBannerController::class, 'reorder'])->name('customers.banners.reorder');
        Route::patch('/banners/{id}/toggle-status', [WebsiteBannerController::class, 'toggleStatus'])->name('customers.banners.toggle-status');
        Route::get('/banners/{id}/edit', [WebsiteBannerController::class, 'edit'])->name('customers.banners.edit');
        Route::put('/banners/{id}', [WebsiteBannerController::class, 'update'])->name('customers.banners.update');
        Route::delete('/banners/{id}', [WebsiteBannerController::class, 'destroy'])->name('customers.banners.destroy');
        Route::get('/pages/{id}/edit', [WebsitePageController::class, 'edit'])->name('customers.website-pages.edit');
        Route::put('/pages/{id}', [WebsitePageController::class, 'updatePage'])->name('customers.website-pages.update');
        Route::post('/pages/{id}/images', [WebsitePageController::class, 'storeContentImage'])->name('customers.website-pages.images.store');
        Route::post('/pages/{id}/sections', [WebsitePageController::class, 'storeSection'])->name('customers.website-pages.sections.store');
        Route::put('/pages/{id}/sections/{sectionId}', [WebsitePageController::class, 'updateSection'])->name('customers.website-pages.sections.update');
        Route::delete('/pages/{id}/sections/{sectionId}', [WebsitePageController::class, 'destroySection'])->name('customers.website-pages.sections.destroy');

        Route::get('/insurances', [InsuranceController::class, 'index'])->name('customers.insurances.index');
        Route::get('/insurances-data', [InsuranceController::class, 'getInsurances']);
        Route::post('/insurances', [InsuranceController::class, 'store']);
        Route::put('/insurances/{id}', [InsuranceController::class, 'update']);
        Route::patch('/insurances/{id}/toggle-status', [InsuranceController::class, 'toggleStatus']);
        Route::delete('/insurances/{id}', [InsuranceController::class, 'destroy']);

        Route::get('/specialties', [SpecialtyController::class, 'index'])->name('customers.specialties.index');
        Route::get('/specialties-data', [SpecialtyController::class, 'getSpecialties']);
        Route::post('/specialties', [SpecialtyController::class, 'store']);
        Route::put('/specialties/{id}', [SpecialtyController::class, 'update']);
        Route::patch('/specialties/{id}/toggle-status', [SpecialtyController::class, 'toggleStatus']);
        Route::delete('/specialties/{id}', [SpecialtyController::class, 'destroy']);
    });
   


    //patience
    Route::get('/patient', [PatientController::class, 'index'])->name('customers.patient.index');
    Route::get('/patient-data', [PatientController::class, 'getPatient']);
    Route::get('/patients/{id}/appointments/data', [PatientController::class, 'appointmentsData'])->name('customers.patients.appointments.data');
    Route::get('/patients/{id}/appointments', [PatientController::class, 'appointments'])->name('customers.patients.appointments.index');
    Route::post('/patient', [PatientController::class, 'store']);
    Route::put('/patient/{id}', [PatientController::class, 'update']);
    Route::delete('/patient/{id}', [PatientController::class, 'destroy']);

    //ficha clinica 
    Route::get('/patients/{id}/medical-record', [MedicalRecordController::class, 'show'])->name('patients.medical-record.show');
    Route::post('/patients/{id}/encounters', [EncounterController::class, 'store'])->name('patients.encounters.store');
    Route::patch('/patients/{id}/encounters/{encounter}/finish', [EncounterController::class, 'finish'])->name('patients.encounters.finish');
    Route::patch('/patients/{id}/encounters/{encounter}/cancel', [EncounterController::class, 'cancel'])->name('patients.encounters.cancel');

    //alergias paciente
    Route::post('/allergies', [AllergieController::class, 'store'])->name('allergies.store');

    //enfermedades cronicas
    Route::post('/chronic-diseases', [ChronicDiseasesController::class, 'store'])->name('chronic_diseases.store');
    //MEDICAMENTOS HABITUALES
    Route::post('/regular-medications', [RegularMedicationController::class, 'store'])->name('regular_medications.store');
    //Cirugías Previas
    Route::post('/previous-surgeries', [PreviousSurgerieController::class, 'store'])->name('previous_surgeries.store');

    //atencion medica
    Route::post('/encounter', [EncounterController::class, 'store'])->name('encounter.store');


    //horarios
    Route::post('/professional/{professional}/schedule', [ProfessionalScheduleController::class, 'store']);
    Route::get('/professional/{professional}/schedule', [ProfessionalScheduleController::class, 'getSchedule']);
    Route::delete('/professional/schedule/{id}', [ProfessionalScheduleController::class, 'destroy']);
    Route::get('/my-schedules/data', [ProfessionalScheduleController::class, 'myScheduleData'])->name('customers.my-schedules.data');
    Route::post('/my-schedules', [ProfessionalScheduleController::class, 'storeMySchedule'])->name('customers.my-schedules.store');
    Route::delete('/my-schedules/{id}', [ProfessionalScheduleController::class, 'destroyMySchedule'])->name('customers.my-schedules.destroy');
    Route::get('/my-schedules/{schedule}/affected-appointments', [ProfessionalScheduleController::class, 'affectedAppointments'])->name('customers.my-schedules.affected-appointments');
    Route::get('/my-schedules/{schedule}/affected-appointments/data', [ProfessionalScheduleController::class, 'affectedAppointmentsData'])->name('customers.my-schedules.affected-appointments.data');
    Route::get('/customer/schedules/{schedule}/affected-appointments', [ProfessionalScheduleController::class, 'affectedAppointments'])->name('customer.schedules.affected-appointments');
    Route::get('/customer/schedules/{schedule}/affected-appointments/data',[ProfessionalScheduleController::class, 'affectedAppointmentsData'])->name('customer.schedules.affected-appointments.data');

    //agenda citas medicas
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('customers.appointments.index');
    Route::get('/appointments/events', [AppointmentController::class, 'events']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::put('/appointments/{id}', [AppointmentController::class, 'update'])->name('customers.appointments.update');
    Route::get('/notifications', [AppointmentNotificationController::class, 'index'])->name('customers.notifications.index');
    Route::patch('/notifications/read-all', [AppointmentNotificationController::class, 'markAllRead'])->name('customers.notifications.read-all');
    Route::get('/notifications/{notification}/open', [AppointmentNotificationController::class, 'open'])->name('customers.notifications.open');
    Route::patch('/notifications/{notification}/read', [AppointmentNotificationController::class, 'markRead'])->name('customers.notifications.read');
    Route::get('/appointments/availability', [AppointmentController::class,'availability']);
    Route::get('/professionals/by-specialty', [AppointmentController::class, 'professionalsBySpecialty']);
    Route::post('/customer/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])->name('customer.appointments.cancel');

    //dashboard
    Route::get('/dashboard/statistics', [CustomerDashboardController::class,'statistics'])->name('dashboard.statistics');

    //profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('customers.profile.index');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('customers.profile.update');

});


Route::domain('{customer}.' . config('app.domain'))
    ->middleware(['tenant'])
    ->group(function () {

        Route::get('/', [WebController::class, 'index']);
        Route::get('/pages/{slug}', [WebsitePageController::class, 'show'])->name('customer.website-pages.show');
    });
/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
