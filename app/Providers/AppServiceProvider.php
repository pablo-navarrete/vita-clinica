<?php

namespace App\Providers;

use App\Models\Logo;
use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\Insurance;
use App\Models\LogoLocation;
use App\Models\MedicalRecord;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\ProfessionalSchedule;
use App\Models\Specialty;
use App\Models\User;
use App\Models\WebsiteBanner;
use App\Models\WebsiteBlogPost;
use App\Models\WebsiteBlogSetting;
use App\Models\WebsiteFooter;
use App\Models\WebsiteMaintenance;
use App\Models\WebsitePage;
use App\Models\WebsitePageSection;
use App\Models\WebsiteSocialSetting;
use App\Models\WebsiteTopbar;
use App\Observers\ClinicActivityObserver;
use Illuminate\Support\Facades\View;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Validator::extend('international_phone',
            fn ($attribute, $value) => app(\App\Services\PhoneNumbers::class)->normalize($value) !== null,
            \App\Services\PhoneNumbers::MESSAGE);
        \Illuminate\Support\Facades\Validator::resolver(fn ($translator, $data, $rules, $messages, $attributes) =>
            new \App\Validation\PhoneAwareValidator($translator, $data, $rules, $messages, $attributes));
        Patient::observe(\App\Observers\PortalLinkSubjectObserver::class);
        \App\Models\PortalAccount::observe(\App\Observers\PortalLinkSubjectObserver::class);
        $observer = ClinicActivityObserver::class;
        foreach ([
            Patient::class, User::class, Appointment::class, Encounter::class,
            ProfessionalSchedule::class, MedicalRecord::class,
            Insurance::class, Specialty::class, Menu::class, WebsitePage::class,
            WebsitePageSection::class, WebsiteBanner::class, WebsiteFooter::class,
            WebsiteSocialSetting::class, WebsiteTopbar::class, WebsiteMaintenance::class,
            WebsiteBlogPost::class, WebsiteBlogSetting::class,
            Logo::class, LogoLocation::class,
        ] as $model) {
            $model::observe($observer);
        }

        View::composer('*', function ($view) {

            $customer = app()->bound('customer') ? app('customer') : null;
            if (!$customer) {
                if (!request()->attributes->has('vita_logos_by_location')) {
                    request()->attributes->set('vita_logos_by_location', \App\Models\VitaWebsiteSetting::logosByLocation());
                }
                $logosByLocation = request()->attributes->get('vita_logos_by_location');
                app()->instance('logos_by_location', $logosByLocation);
                $view->with('logosByLocation', $logosByLocation);
                return;
            }

            $customerId = $customer->id;

            $logos = Logo::with('locations')
                ->where('customer_id', $customerId)
                ->where('status', 1)
                ->get();

            $logosByLocation = [];

            foreach ($logos as $logo) {
                foreach ($logo->locations as $loc) {

                    // solo el primer logo activo por ubicación
                    if (!isset($logosByLocation[$loc->location])) {
                        $logosByLocation[$loc->location] = $logo;
                    }
                }
            }

            $view->with('logosByLocation', $logosByLocation);
        });
    }
}
