<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    public function __construct(public ?array $motivation = null)
    {
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        if ($this->motivation === null) return view('layouts.guest');

        $clinic = app()->bound('customer') ? app('customer') : null;
        $publicSiteUrl = null;
        if ($clinic && !empty($clinic->domain)
            && !\App\Models\WebsiteMaintenance::where('customer_id', $clinic->id)->where('enabled', true)->exists()) {
            $publicSiteUrl = action([\App\Http\Controllers\customer\WebController::class, 'index'], ['customer' => $clinic->domain]);
        }

        return view('layouts.login', compact('publicSiteUrl'));
    }
}
