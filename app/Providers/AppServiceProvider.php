<?php

namespace App\Providers;

use App\Models\Logo;
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


        View::composer('*', function ($view) {

            if (!app()->bound('customer')) {
                $view->with('logosByLocation', []);
                return;
            }

            $customerId = app('customer')->id;

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
