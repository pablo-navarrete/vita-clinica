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
use App\Http\Controllers\customer\ClinicMailSettingController;
use App\Http\Controllers\customer\WebsiteMaintenanceController;
use App\Http\Controllers\customer\ClinicActivityController;
use App\Http\Controllers\customer\ClinicBranchContextController;
use App\Http\Controllers\customer\ChatController;
use App\Http\Controllers\customer\MedicalRecordController;
use App\Http\Controllers\customer\PatientController;
use App\Http\Controllers\customer\PatientDocumentController;
use App\Http\Controllers\customer\PreviousSurgerieController;
use App\Http\Controllers\customer\ProfessionalController;
use App\Http\Controllers\customer\ProfessionalScheduleController;
use App\Http\Controllers\customer\RegularMedicationController;
use App\Http\Controllers\customer\WebController;
use App\Http\Controllers\customer\PublicBookingController;
use App\Http\Controllers\customer\TelemedicineController;
use App\Http\Controllers\customer\VitaAssistantController;
use App\Http\Controllers\customer\WebsiteBlogController;
use App\Http\Controllers\customer\ProfileController;
use App\Http\Controllers\superadmin\CustomerController;
use App\Http\Controllers\superadmin\DashboardController;
use App\Http\Controllers\superadmin\ProfileController as SuperadminProfileController;
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

Route::domain(config('app.domain'))->get('/vita', [\App\Http\Controllers\superadmin\VitaWebsiteController::class, 'index'])->name('vita.website');
Route::domain(config('app.domain'))->post('/superadmin/assistant/ask',[\App\Http\Controllers\superadmin\VitaAssistantController::class,'ask'])
    ->middleware(['auth:web','superadmin','throttle:30,1'])->name('superadmin.assistant.ask');
Route::domain(config('app.domain'))->group(function(){
    Route::get('/',[\App\Http\Controllers\PublicCustomerDirectoryController::class,'index'])->name('vita.directory.index');
    Route::post('/directory-location',[\App\Http\Controllers\PublicCustomerDirectoryController::class,'location'])->middleware('throttle:12,1')->name('vita.directory.location');
    Route::get('/clinicas',function(\Illuminate\Http\Request $request){
        return redirect()->route('vita.directory.index',$request->only(['buscar','page','especialidad','cercanas']),301);
    });
    Route::get('/clinicas/{slug}',[\App\Http\Controllers\PublicCustomerDirectoryController::class,'show'])->name('vita.directory.show');
    Route::get('/directory-sitemap.xml',[\App\Http\Controllers\PublicCustomerDirectoryController::class,'sitemap'])->name('vita.directory.sitemap');
});
Route::domain(config('app.domain'))->get('/pages/{slug}', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'publicPage'])->name('vita.website.page');

Route::domain(config('app.domain'))->middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/customer-directory',[\App\Http\Controllers\superadmin\CustomerDirectoryController::class,'index'])->name('superadmin.directory.index');
    Route::put('/customer-directory/{id}',[\App\Http\Controllers\superadmin\CustomerDirectoryController::class,'update'])->whereNumber('id')->name('superadmin.directory.update');

    Route::get('/website', [\App\Http\Controllers\superadmin\VitaWebsiteController::class, 'edit'])->name('superadmin.website.edit');
    Route::put('/website', [\App\Http\Controllers\superadmin\VitaWebsiteController::class, 'update'])->name('superadmin.website.update');
    Route::get('/website/preview', [\App\Http\Controllers\superadmin\VitaWebsiteController::class, 'preview'])->name('superadmin.website.preview');
    Route::post('/website/menus', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'store'])->name('superadmin.menus.store');
    Route::put('/website/menus/brand', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'updateBrand'])->name('superadmin.menus.brand.update');
    Route::put('/website/menus/navigation-button', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'navigationButton'])->name('superadmin.menus.navigation-button.update');
    Route::patch('/website/menus/reorder', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'reorder'])->name('superadmin.menus.reorder');
    Route::get('/website/menus/{id}/edit', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'edit'])->whereNumber('id')->name('superadmin.menus.edit');
    Route::put('/website/menus/{id}', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'update'])->whereNumber('id')->name('superadmin.menus.update');
    Route::patch('/website/menus/{id}/toggle-status', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'toggle'])->whereNumber('id')->name('superadmin.menus.toggle-status');
    Route::delete('/website/menus/{id}', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'destroy'])->whereNumber('id')->name('superadmin.menus.destroy');
    Route::get('/website/pages/{id}/edit', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'editPage'])->whereNumber('id')->name('superadmin.website-pages.edit');
    Route::put('/website/pages/{id}', [\App\Http\Controllers\superadmin\WebsiteMenuController::class, 'updatePage'])->whereNumber('id')->name('superadmin.website-pages.update');
    Route::post('/website/banners', [\App\Http\Controllers\superadmin\WebsiteBannerController::class, 'store'])->name('superadmin.banners.store');
    Route::patch('/website/banners/reorder', [\App\Http\Controllers\superadmin\WebsiteBannerController::class, 'reorder'])->name('superadmin.banners.reorder');
    Route::get('/website/banners/{id}/edit', [\App\Http\Controllers\superadmin\WebsiteBannerController::class, 'edit'])->whereNumber('id')->name('superadmin.banners.edit');
    Route::put('/website/banners/{id}', [\App\Http\Controllers\superadmin\WebsiteBannerController::class, 'update'])->whereNumber('id')->name('superadmin.banners.update');
    Route::patch('/website/banners/{id}/toggle-status', [\App\Http\Controllers\superadmin\WebsiteBannerController::class, 'toggle'])->whereNumber('id')->name('superadmin.banners.toggle-status');
    Route::delete('/website/banners/{id}', [\App\Http\Controllers\superadmin\WebsiteBannerController::class, 'destroy'])->whereNumber('id')->name('superadmin.banners.destroy');
    Route::get('/website/logos/data', [\App\Http\Controllers\superadmin\LogoController::class, 'data'])->name('superadmin.logos.data');
    Route::post('/website/logos', [\App\Http\Controllers\superadmin\LogoController::class, 'store'])->name('superadmin.logos.store');
    Route::post('/website/logos/{id}', [\App\Http\Controllers\superadmin\LogoController::class, 'update'])->name('superadmin.logos.update');
    Route::patch('/website/logos/{id}/toggle-status', [\App\Http\Controllers\superadmin\LogoController::class, 'toggle'])->name('superadmin.logos.toggle');
    Route::delete('/website/logos/{id}', [\App\Http\Controllers\superadmin\LogoController::class, 'destroy'])->name('superadmin.logos.destroy');
    Route::get('/website/{section}', [\App\Http\Controllers\superadmin\VitaWebsiteController::class, 'editSection'])->whereIn('section', ['logo', 'menus', 'banners', 'footer', 'topbar', 'socials'])->name('superadmin.website.section');
    Route::put('/website/{section}', [\App\Http\Controllers\superadmin\VitaWebsiteController::class, 'updateSection'])->whereIn('section', ['logo', 'menus', 'banners', 'footer', 'topbar', 'socials'])->name('superadmin.website.section.update');

    // dashboard superadmin
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::prefix('billing')->name('superadmin.billing.')->group(function () {
        Route::get('conditions',[\App\Http\Controllers\superadmin\Billing\SubscriptionPolicyController::class,'edit'])->name('conditions.edit');
        Route::put('conditions',[\App\Http\Controllers\superadmin\Billing\SubscriptionPolicyController::class,'update'])->name('conditions.update');
        Route::put('fee-defaults', [\App\Http\Controllers\superadmin\Billing\PaymentFeeController::class, 'saveDefaults'])->name('fees.defaults');
        Route::get('fees',[\App\Http\Controllers\superadmin\Billing\PaymentFeeController::class,'index'])->name('fees.index');
        Route::post('fees',[\App\Http\Controllers\superadmin\Billing\PaymentFeeController::class,'store'])->name('fees.store');
        Route::post('fees/flow-sync',[\App\Http\Controllers\superadmin\Billing\PaymentFeeController::class,'sync'])->middleware('throttle:6,1')->name('fees.flow-sync');
        Route::patch('fees/alerts/{alert}',[\App\Http\Controllers\superadmin\Billing\PaymentFeeController::class,'acknowledge'])->name('fees.acknowledge');
        Route::get('subscriptions-data',[\App\Http\Controllers\superadmin\Billing\SubscriptionController::class,'data'])->name('subscriptions.data');
        Route::resource('subscriptions',\App\Http\Controllers\superadmin\Billing\SubscriptionController::class)->only(['index','create','store','show']);
        Route::post('subscriptions/{subscription}/payments',[\App\Http\Controllers\superadmin\Billing\SubscriptionController::class,'payment'])->name('subscriptions.payments.store');
        Route::post('subscriptions/{subscription}/changes',[\App\Http\Controllers\superadmin\Billing\SubscriptionController::class,'change'])->name('subscriptions.changes.store');
        Route::delete('subscriptions/{subscription}/pending-change',[\App\Http\Controllers\superadmin\Billing\SubscriptionController::class,'cancelChange'])->name('subscriptions.changes.cancel');
        Route::post('subscriptions/{subscription}/cancel',[\App\Http\Controllers\superadmin\Billing\SubscriptionController::class,'cancel'])->name('subscriptions.cancel');
        Route::get('plans-data', [\App\Http\Controllers\superadmin\Billing\PlanController::class, 'data'])->name('plans.data');
        Route::resource('plans', \App\Http\Controllers\superadmin\Billing\PlanController::class);
        Route::patch('plans/{plan}/status', [\App\Http\Controllers\superadmin\Billing\PlanController::class, 'status'])->name('plans.status');
        Route::post('plans/{plan}/versions', [\App\Http\Controllers\superadmin\Billing\PlanVersionController::class, 'store'])->name('versions.store');
        Route::get('versions/{version}/edit', [\App\Http\Controllers\superadmin\Billing\PlanVersionController::class, 'edit'])->name('versions.edit');
        Route::get('versions/{version}', [\App\Http\Controllers\superadmin\Billing\PlanVersionController::class, 'show'])->name('versions.show');
        Route::put('versions/{version}', [\App\Http\Controllers\superadmin\Billing\PlanVersionController::class, 'update'])->name('versions.update');
        Route::post('versions/{version}/publish', [\App\Http\Controllers\superadmin\Billing\PlanVersionController::class, 'publish'])->name('versions.publish');
    });
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics'])->name('superadmin.dashboard.statistics');
    Route::get('/profile', [SuperadminProfileController::class, 'index'])->name('superadmin.profile.index');
    Route::patch('/profile', [SuperadminProfileController::class, 'update'])->name('superadmin.profile.update');

    // clientes
    Route::get('/customer-data', [CustomerController::class, 'getCustomer']);

    Route::get('/superadmin/customers', [CustomerController::class, 'index'])->name('superadmin.customer');
    Route::get('/customers/{customer}/branches', [CustomerController::class, 'branches'])->name('superadmin.customers.branches');
    Route::post('/customers/{customer}/branches', [CustomerController::class, 'storeBranch'])->name('superadmin.customers.branches.store');
    Route::put('/customers/{customer}/branches/{branchId}', [CustomerController::class, 'updateBranch'])->whereNumber('branchId')->name('superadmin.customers.branches.update');
    Route::patch('/customers/{customer}/branches/{branchId}/status', [CustomerController::class, 'toggleBranchStatus'])->whereNumber('branchId')->name('superadmin.customers.branches.status');
    Route::delete('/customers/{customer}/branches/{branchId}', [CustomerController::class, 'destroyBranch'])->whereNumber('branchId')->name('superadmin.customers.branches.destroy');

    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{customer}/identification', [CustomerController::class, 'identification']);
    Route::get('/customers/{customer}/administrators/{userId}/identification', [CustomerController::class, 'administratorIdentification'])->whereNumber('userId');
    Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    Route::patch('/customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus']);
    Route::patch('/customers/{customer}/toggle-payment-status', [CustomerController::class, 'togglePaymentStatus']);
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);
    Route::get('/customer-trash', [CustomerController::class, 'getTrash']);
    Route::patch('/customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
});

// Acceso del paciente mediante enlace temporal firmado; no requiere cuenta en Vita.
Route::domain('{customer}.' . config('app.domain'))->middleware('signed')->group(function () {
    Route::get('/telemedicine/{sessionId}/patient', [TelemedicineController::class, 'patientRoom'])->name('telemedicine.patient.room');
    Route::post('/telemedicine/{sessionId}/patient/token', [TelemedicineController::class, 'patientToken'])->name('telemedicine.patient.token');
});

/*
|--------------------------------------------------------------------------
| TENANTS (SUBDOMINIOS)
|--------------------------------------------------------------------------
| clinica.vita-clinica.cl
*/

// IdentifyCustomer ya forma parte del grupo web global; no volver a ejecutarlo aquí.
Route::domain('{customer}.' . config('app.domain'))->middleware(['auth', 'role-permission'])->group(function () {

    Route::get('/dashboard', [CustomerDashboardController::class, 'index'])->name('customers.dashboard.index');
    Route::get('/directory-profile',[\App\Http\Controllers\customer\CustomerDirectoryController::class,'edit'])->name('customers.directory.edit');
    Route::get('/directory-reviews', [\App\Http\Controllers\customer\DirectoryReviewController::class, 'index'])->name('customers.directory.reviews');
    Route::post('/directory-reviews/{review}/reply', [\App\Http\Controllers\customer\DirectoryReviewController::class, 'reply'])->whereNumber('review')->middleware('throttle:10,1')->name('customers.directory.reviews.reply');
    Route::put('/directory-profile',[\App\Http\Controllers\customer\CustomerDirectoryController::class,'update'])->name('customers.directory.update');
    Route::get('/my-subscription',[\App\Http\Controllers\customer\ClinicSubscriptionController::class,'index'])->name('customers.subscription.index');
    Route::get('/subscription-conditions',[\App\Http\Controllers\customer\ClinicSubscriptionController::class,'conditions'])->name('customers.subscription.conditions');
    Route::post('/my-subscription/{action}',[\App\Http\Controllers\customer\ClinicSubscriptionController::class,'action'])
        ->whereIn('action',['pay','card','automatic','manual','cancel','refresh'])->middleware('throttle:12,1')->name('customers.subscription.action');
    Route::post('/branch-context', [ClinicBranchContextController::class, 'update'])->name('customers.branch-context.update');
    Route::post('/presence', [ChatController::class, 'presence'])->middleware('throttle:30,1')->name('customers.presence');
    Route::post('/assistant/ask', [VitaAssistantController::class, 'ask'])->middleware('throttle:30,1')->name('customers.assistant.ask');
    //color theme
    Route::post('/theme', [ColorController::class, 'update'])->name('theme.update');

    //professional
    Route::middleware('prevent-doctor-system-configuration')->group(function () {
        Route::get('/roles-permissions', [\App\Http\Controllers\customer\RolePermissionController::class, 'index'])->name('customers.roles-permissions.index');
        Route::get('/roles-permissions/users-data', [\App\Http\Controllers\customer\RolePermissionController::class, 'usersData'])->name('customers.roles-permissions.users-data');
        Route::post('/roles-permissions', [\App\Http\Controllers\customer\RolePermissionController::class, 'store'])->name('customers.roles-permissions.store');
        Route::put('/roles-permissions/{roleId}', [\App\Http\Controllers\customer\RolePermissionController::class, 'update'])->whereNumber('roleId')->name('customers.roles-permissions.update');
        Route::delete('/roles-permissions/{roleId}', [\App\Http\Controllers\customer\RolePermissionController::class, 'destroy'])->whereNumber('roleId')->name('customers.roles-permissions.destroy');
        Route::patch('/roles-permissions/users/{userId}', [\App\Http\Controllers\customer\RolePermissionController::class, 'updateUserRole'])->whereNumber('userId')->name('customers.roles-permissions.users.update');
        Route::get('/activity-history', [ClinicActivityController::class, 'index'])->name('customers.activity-history.index');
        // Configuración del sistema
        Route::get('/users', [ProfessionalController::class, 'index'])->name('customers.users.index');
        Route::get('/users-data', [ProfessionalController::class, 'getProfessional'])->name('customers.users.data');
        Route::post('/users', [ProfessionalController::class, 'store'])->name('customers.users.store');
        Route::get('/users/{id}/identification', [ProfessionalController::class, 'identification'])->whereNumber('id');
        Route::get('/users/{id}/roles', [ProfessionalController::class, 'getUserRoles'])->whereNumber('id')->name('customers.users.roles.show');
        Route::patch('/users/{id}/roles', [ProfessionalController::class, 'updateRoles'])->whereNumber('id')->name('customers.users.roles.update');
        Route::get('/users/{id}/specialties', [ProfessionalController::class, 'getUserSpecialties'])->whereNumber('id')->name('customers.users.specialties.show');
        Route::patch('/users/{id}/specialties', [ProfessionalController::class, 'updateUserSpecialties'])->whereNumber('id')->name('customers.users.specialties.update');
        Route::patch('/users/{id}/branches', [ProfessionalController::class, 'updateBranches'])->whereNumber('id')->name('customers.users.branches.update');
        Route::put('/users/{id}', [ProfessionalController::class, 'update'])->whereNumber('id')->name('customers.users.update');
        Route::patch('/users/{id}/toggle-status', [ProfessionalController::class, 'toggleStatus'])->whereNumber('id')->name('customers.users.toggle-status');
        Route::delete('/users/{id}', [ProfessionalController::class, 'destroy'])->whereNumber('id')->name('customers.users.destroy');
        Route::get('/users/{userId}/absences', [\App\Http\Controllers\customer\UserAbsenceController::class, 'index'])->whereNumber('userId')->name('customers.users.absences.index');
        Route::post('/users/{userId}/absences', [\App\Http\Controllers\customer\UserAbsenceController::class, 'store'])->whereNumber('userId')->name('customers.users.absences.store');
        Route::delete('/users/{userId}/absences/{absenceId}', [\App\Http\Controllers\customer\UserAbsenceController::class, 'destroy'])->whereNumber('userId')->whereNumber('absenceId')->name('customers.users.absences.destroy');
        Route::get('/professional', [ProfessionalController::class, 'index'])->name('customers.professional.index');
        Route::get('/professional-data', [ProfessionalController::class, 'getProfessional']);
        Route::post('/professional', [ProfessionalController::class, 'store']);
        Route::patch('/professional/{id}/roles', [ProfessionalController::class, 'updateRoles'])->whereNumber('id')->name('customers.professional.roles.update');
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
        Route::put('/menus/brand', [MenuController::class, 'updateBrand'])->name('customers.menus.brand.update');
        Route::put('/menus/navigation-button', [MenuController::class, 'updateNavigationButton'])->name('customers.menus.navigation-button.update');
        Route::put('/menus/public-booking', [MenuController::class, 'updatePublicBooking'])->name('customers.menus.public-booking.update');
        Route::get('/website-footer', [WebsiteFooterController::class, 'edit'])->name('customers.website-footer.edit');
        Route::put('/website-footer', [WebsiteFooterController::class, 'update'])->name('customers.website-footer.update');
        Route::get('/website-blog', [WebsiteBlogController::class, 'index'])->name('customers.website-blog.index');
        Route::get('/website-blog/create', [WebsiteBlogController::class, 'create'])->name('customers.website-blog.create');
        Route::post('/website-blog', [WebsiteBlogController::class, 'store'])->name('customers.website-blog.store');
        Route::put('/website-blog/settings', [WebsiteBlogController::class, 'updateSettings'])->name('customers.website-blog.settings');
        Route::get('/website-blog/{id}/preview', [WebsiteBlogController::class, 'preview'])->whereNumber('id')->name('customers.website-blog.preview');
        Route::get('/website-blog/{id}/edit', [WebsiteBlogController::class, 'edit'])->whereNumber('id')->name('customers.website-blog.edit');
        Route::put('/website-blog/{id}', [WebsiteBlogController::class, 'update'])->whereNumber('id')->name('customers.website-blog.update');
        Route::patch('/website-blog/{id}/toggle-status', [WebsiteBlogController::class, 'toggleStatus'])->whereNumber('id')->name('customers.website-blog.toggle-status');
        Route::delete('/website-blog/{id}', [WebsiteBlogController::class, 'destroy'])->whereNumber('id')->name('customers.website-blog.destroy');
        Route::get('/website-topbar', [WebsiteTopbarController::class, 'edit'])->name('customers.website-topbar.edit');
        Route::put('/website-topbar', [WebsiteTopbarController::class, 'update'])->name('customers.website-topbar.update');
        Route::get('/mail-settings', [ClinicMailSettingController::class, 'edit'])->name('customers.mail-settings.edit');
        Route::put('/mail-settings', [ClinicMailSettingController::class, 'update'])->name('customers.mail-settings.update');
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

    });
   


    //patience
    Route::get('/patient', [PatientController::class, 'index'])->name('customers.patient.index');
    Route::get('/patient-data', [PatientController::class, 'getPatient']);
    Route::post('/patient-data/search', [PatientController::class, 'getPatient']);
    Route::get('/patients/{id}/appointments/data', [PatientController::class, 'appointmentsData'])->name('customers.patients.appointments.data');
    Route::get('/patients/{id}/appointments', [PatientController::class, 'appointments'])->name('customers.patients.appointments.index');
    Route::post('/patient', [PatientController::class, 'store']);
    Route::post('/patient/mi-salud-profile', [PatientController::class, 'portalProfile'])->middleware('throttle:20,1');
    Route::get('/patient/{id}/identification', [PatientController::class, 'identification'])->whereNumber('id');
    Route::put('/patient/{id}', [PatientController::class, 'update']);
    Route::delete('/patient/{id}', [PatientController::class, 'destroy']);

    //ficha clinica 
    Route::get('/patients/{id}/mi-salud-access',[\App\Http\Controllers\customer\PortalClinicalAccessController::class,'index'])->whereNumber('id')->name('patients.portal-access.index');
    Route::post('/patients/{id}/mi-salud-access',[\App\Http\Controllers\customer\PortalClinicalAccessController::class,'store'])->whereNumber('id')->middleware('throttle:5,1')->name('patients.portal-access.store');
    Route::delete('/patients/{id}/mi-salud-access/{grant}',[\App\Http\Controllers\customer\PortalClinicalAccessController::class,'revoke'])->whereNumber('id')->whereUuid('grant')->middleware('throttle:5,1')->name('patients.portal-access.revoke');
    Route::get('/patients/{id}/medical-record', [MedicalRecordController::class, 'show'])->name('patients.medical-record.show');
    Route::get('/patients/{id}/medical-record/export/pdfa', [MedicalRecordController::class, 'exportPdfA'])->name('patients.medical-record.export.pdfa');
    Route::get('/patients/{id}/medical-record/export/fhir-r4', [MedicalRecordController::class, 'exportFhirR4'])->name('patients.medical-record.export.fhir-r4');
    Route::get('/patients/{id}/medical-record/activity', [MedicalRecordController::class, 'activity'])->name('patients.medical-record.activity');
    Route::post('/patients/{id}/encounters/{encounter}/documents', [PatientDocumentController::class, 'store'])->whereNumber('id')->whereNumber('encounter')->name('patients.encounters.documents.store');
    Route::get('/patients/{id}/documents/{document}/download', [PatientDocumentController::class, 'download'])->whereNumber('id')->whereNumber('document')->name('patients.documents.download');
    Route::get('/patients/{id}/documents/{document}/preview', [PatientDocumentController::class, 'preview'])->whereNumber('id')->whereNumber('document')->name('patients.documents.preview');
    Route::post('/patients/{id}/encounters', [EncounterController::class, 'store'])->name('patients.encounters.store');
    Route::post('/patients/{id}/telemedicine', [TelemedicineController::class, 'createWithoutAppointment'])->whereNumber('id')->name('patients.telemedicine.create');
    Route::get('/patients/{id}/telemedicine/{sessionId}/status', [TelemedicineController::class, 'directStatus'])->whereNumber(['id', 'sessionId'])->name('patients.telemedicine.status');
    Route::patch('/patients/{id}/encounters/{encounter}/finish', [EncounterController::class, 'finish'])->name('patients.encounters.finish');
    Route::post('/patients/{id}/encounters/{encounter}/signature-preview', [\App\Http\Controllers\customer\EncounterSigningController::class, 'prepare'])->middleware('throttle:30,1')->name('patients.encounters.signature-preview');
    Route::get('/patients/{id}/encounters/{encounter}/addenda/create', [\App\Http\Controllers\customer\EncounterAddendumController::class, 'create'])->whereNumber('id')->whereNumber('encounter')->name('patients.encounters.addenda.create');
    Route::post('/patients/{id}/encounters/{encounter}/addenda/preview', [\App\Http\Controllers\customer\EncounterAddendumController::class, 'prepare'])->middleware('throttle:30,1')->name('patients.encounters.addenda.preview');
    Route::post('/patients/{id}/encounters/{encounter}/addenda', [\App\Http\Controllers\customer\EncounterAddendumController::class, 'store'])->name('patients.encounters.addenda.store');
    Route::patch('/patients/{id}/encounters/{encounter}/cancel', [EncounterController::class, 'cancel'])->name('patients.encounters.cancel');
    Route::post('/patients/{id}/encounters/{encounter}/observations', [EncounterController::class, 'addObservation'])->whereNumber('id')->whereNumber('encounter')->name('patients.encounters.observations.store');

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
    Route::get('/my-schedules', [ProfessionalScheduleController::class, 'indexMine'])->name('customers.my-schedules.index');
    Route::get('/my-schedules/{schedule}/affected-appointments', [ProfessionalScheduleController::class, 'affectedAppointments'])->name('customers.my-schedules.affected-appointments');
    Route::get('/my-schedules/{schedule}/affected-appointments/data', [ProfessionalScheduleController::class, 'affectedAppointmentsData'])->name('customers.my-schedules.affected-appointments.data');
    Route::get('/customer/schedules/{schedule}/affected-appointments', [ProfessionalScheduleController::class, 'affectedAppointments'])->name('customer.schedules.affected-appointments');
    Route::get('/customer/schedules/{schedule}/affected-appointments/data',[ProfessionalScheduleController::class, 'affectedAppointmentsData'])->name('customer.schedules.affected-appointments.data');

    //agenda citas medicas
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('customers.appointments.index');
    Route::get('/appointments/events', [AppointmentController::class, 'events']);
    Route::get('/appointments/branch-professionals', [AppointmentController::class, 'calendarProfessionals'])->name('appointments.branch-professionals');
    Route::get('/appointments/specialties', [AppointmentController::class, 'specialtiesForBranch']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::put('/appointments/{id}', [AppointmentController::class, 'update'])->name('customers.appointments.update');
    Route::post('/appointments/{appointmentId}/telemedicine', [TelemedicineController::class, 'create'])->name('customers.telemedicine.create');
    Route::get('/telemedicine/{sessionId}', [TelemedicineController::class, 'staffRoom'])->name('customers.telemedicine.room');
    Route::post('/telemedicine/{sessionId}/token', [TelemedicineController::class, 'staffToken'])->name('customers.telemedicine.token');
    Route::post('/telemedicine/{sessionId}/end', [TelemedicineController::class, 'end'])->name('customers.telemedicine.end');
    Route::get('/notifications', [AppointmentNotificationController::class, 'index'])->name('customers.notifications.index');
        Route::get('/notifications/summary', [AppointmentNotificationController::class, 'summary'])->name('customers.notifications.summary');
    Route::patch('/notifications/read-all', [AppointmentNotificationController::class, 'markAllRead'])->name('customers.notifications.read-all');
    Route::get('/notifications/{notification}/open', [AppointmentNotificationController::class, 'open'])->name('customers.notifications.open');
    Route::patch('/notifications/{notification}/read', [AppointmentNotificationController::class, 'markRead'])->name('customers.notifications.read');
    Route::get('/appointments/availability', [AppointmentController::class,'availability']);
        Route::get('/appointments/availability-week', [AppointmentController::class, 'weeklyAvailability'])->name('appointments.availability.week');
    Route::get('/professionals/by-specialty', [AppointmentController::class, 'professionalsBySpecialty']);
    Route::post('/customer/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])->name('customer.appointments.cancel');

    // Chat interno, disponible para usuarios activos de la clínica.
    Route::get('/chat', [ChatController::class, 'index'])->name('customers.chat.index');
    Route::get('/chat/summary', [ChatController::class, 'summary'])->name('customers.chat.summary');
    Route::get('/chat/conversation-search', [ChatController::class, 'searchConversations'])->name('customers.chat.conversations.search');
    Route::get('/chat/users/search', [ChatController::class, 'searchUsers'])->name('customers.chat.users.search');
    Route::post('/chat', [ChatController::class, 'store'])->name('customers.chat.store');
    Route::get('/chat/patients/search', [ChatController::class, 'searchPatients'])->name('customers.chat.patients.search');
    Route::post('/chat/{conversation}/participants', [ChatController::class, 'addParticipant'])->name('customers.chat.participants.add');
    Route::delete('/chat/{conversation}/hide', [ChatController::class, 'hideConversation'])->name('customers.chat.hide');
    Route::delete('/chat/{conversation}/participants/{participant}', [ChatController::class, 'removeParticipant'])->name('customers.chat.participants.remove');
    Route::get('/chat/{conversation}/search', [ChatController::class, 'searchMessages'])->name('customers.chat.search');
    Route::get('/chat/{conversation}/messages', [ChatController::class, 'messages'])->name('customers.chat.messages');
    Route::post('/chat/{conversation}/messages', [ChatController::class, 'send'])->name('customers.chat.send');
    Route::delete('/chat/{conversation}/messages/{message}', [ChatController::class, 'deleteMessage'])->name('customers.chat.messages.delete');
    Route::post('/chat/{conversation}/messages/{message}/reactions', [ChatController::class, 'toggleReaction'])->name('customers.chat.reactions.toggle');
    Route::post('/chat/messages/forward', [ChatController::class, 'forward'])->name('customers.chat.forward');

    //dashboard
    Route::get('/dashboard/statistics', [CustomerDashboardController::class,'statistics'])->name('dashboard.statistics');

    //profile
    Route::get('/my-encounters', [\App\Http\Controllers\customer\MyEncounterController::class, 'index'])->name('customers.encounters.mine');
    Route::get('/profile', [ProfileController::class, 'index'])->name('customers.profile.index');
    Route::get('/profile/electronic-signature', [\App\Http\Controllers\customer\ProfessionalSignatureController::class, 'index'])->name('customers.signature.index');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('customers.profile.update');
    Route::post('/profile/electronic-signature', [\App\Http\Controllers\customer\ProfessionalSignatureController::class, 'update'])->middleware('throttle:20,1')->name('customers.signature.update');
    Route::get('/profile/electronic-signature/images/{uuid}', [\App\Http\Controllers\customer\ProfessionalSignatureController::class, 'image'])->whereUuid('uuid')->name('customers.signature.image');

});


Route::domain('{customer}.' . config('app.domain'))
    ->group(function () {

        Route::get('/', [WebController::class, 'index'])->name('customer.website.home');
        Route::post('/public-assistant/ask', [VitaAssistantController::class, 'askPublic'])
            ->middleware('throttle:30,1')
            ->name('customer.public-assistant.ask');
        Route::get('/citas/{appointmentId}/confirmar', [PublicBookingController::class, 'confirmPage'])
            ->middleware('signed')
            ->name('customer.appointment.confirm.page');
        Route::post('/citas/{appointmentId}/confirmar', [PublicBookingController::class, 'confirm'])
            ->middleware('signed')
            ->name('customer.appointment.confirm');
        Route::get('/agendar/verificar/{operation}', [PublicBookingController::class, 'verifyRegistrationPage'])
            ->whereUuid('operation')->middleware('throttle:20,1')->name('customer.public-booking.verify');
        Route::post('/agendar/verificar/{operation}', [PublicBookingController::class, 'verifyRegistration'])
            ->whereUuid('operation')->middleware('throttle:10,1')->name('customer.public-booking.verify.submit');
        Route::post('/agendar/mi-salud', [PublicBookingController::class, 'portalStart'])->middleware('throttle:10,1')->name('customer.public-booking.portal.start');
        Route::get('/agendar/mi-salud/{intent}/volver', [PublicBookingController::class, 'portalReturn'])->whereUuid('intent')->name('customer.public-booking.portal.return');
        Route::post('/agendar/mi-salud/{intent}/volver', [PublicBookingController::class, 'portalRedeem'])->whereUuid('intent')->middleware('throttle:10,1')->name('customer.public-booking.portal.redeem');
        Route::get('/agendar/mi-salud/{intent}/cancelar', [PublicBookingController::class, 'portalCancel'])->whereUuid('intent')->name('customer.public-booking.portal.cancel');
        Route::get('/agendar', [PublicBookingController::class, 'index'])->name('customer.public-booking.index');
        Route::get('/blog', [WebsiteBlogController::class, 'publicIndex'])->name('customer.website-blog.public.index');
        Route::get('/blog/{slug}', [WebsiteBlogController::class, 'publicShow'])->name('customer.website-blog.public.show');
        Route::get('/agendar/verificar-rut', [PublicBookingController::class, 'checkRut'])->middleware('throttle:20,1')->name('customer.public-booking.check-rut');
        Route::get('/agendar/profesionales', [PublicBookingController::class, 'professionals'])->middleware('throttle:60,1')->name('customer.public-booking.professionals');
        Route::get('/agendar/disponibilidad', [PublicBookingController::class, 'availability'])->middleware('throttle:60,1')->name('customer.public-booking.availability');
        Route::get('/agendar/disponibilidad-semanal', [PublicBookingController::class, 'weeklyAvailability'])->middleware('throttle:30,1')->name('customer.public-booking.weekly-availability');
        Route::post('/agendar', [PublicBookingController::class, 'store'])->middleware('throttle:10,1')->name('customer.public-booking.store');
        Route::get('/pages/{slug}', [WebsitePageController::class, 'show'])->name('customer.website-pages.show');
    });
/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
