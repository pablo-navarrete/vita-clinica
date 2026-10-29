<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\WebsitePage;
use App\Models\WebsiteFooter;
use App\Models\WebsiteSocialSetting;
use App\Models\WebsiteBanner;
use App\Models\WebsiteTopbar;
use App\Models\Specialty;
use App\Models\WebsiteMaintenance;
use App\Models\WebsiteNavigationButton;

class WebController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customerId = (int) app('customer')->id;
        $maintenance = WebsiteMaintenance::query()->firstOrNew(['customer_id' => $customerId]);
        $user = auth()->user();
        $hasAdminAccess = $user && ($user->roles()->where('roles.customer_id', $customerId)->where('roles.name', 'Administrador')->exists()
            || $user->roles()->whereNull('roles.customer_id')->where('roles.name', 'Super Administrador')->exists());
        $isDoctorOnly = $user && $user->roles()->where('roles.customer_id', $customerId)->where('roles.name', 'Doctor')->exists() && ! $hasAdminAccess;
        $canManageWebsite = (bool) $user && ! $isDoctorOnly;
        if ($maintenance->enabled && (request()->boolean('public_view') || !$canManageWebsite)) {
            return response()->view('customer.web.maintenance', compact('maintenance'), 503);
        }
        WebsitePage::ensureHomeForCustomer($customerId);
        $publicMenus = $this->publicMenus($customerId);
        $websiteNavigationButton = WebsiteNavigationButton::query()->where('customer_id', $customerId)->first();
        $publicBanners = WebsiteBanner::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $publicSpecialties = Specialty::query()->where('customer_id', $customerId)->where('status', true)->orderBy('name')->get();
        $websiteFooter = WebsiteFooter::where('customer_id', $customerId)->first();
        $websiteSocialSettings = WebsiteSocialSetting::where('customer_id', $customerId)->first();
        $websiteTopbar = WebsiteTopbar::firstOrNew(
            ['customer_id' => $customerId],
            [
                'enabled' => true,
                'show_social_links' => (bool) ($websiteSocialSettings?->show_in_topbar ?? false),
                'phone' => $websiteFooter?->phone,
                'email' => $websiteFooter?->email,
            ]
        );
        $homePage = WebsitePage::query()
            ->where('customer_id', $customerId)
            ->where('slug', 'inicio')
            ->where('status', true)
            ->with('sections')
            ->first();

        if ($homePage) {
            return view('customer.web.page', [
                'page' => $homePage,
                'publicMenus' => $publicMenus,
                'websiteNavigationButton' => $websiteNavigationButton,
                'publicBanners' => $publicBanners,
                'publicSpecialties' => $publicSpecialties,
                'hasConfiguredMenus' => $publicMenus->isNotEmpty(),
                'websiteFooter' => $websiteFooter,
                'websiteSocialSettings' => $websiteSocialSettings,
                'websiteTopbar' => $websiteTopbar,
            ]);
        }

        return view('customer.web.index', [
            'publicMenus' => $publicMenus,
            'websiteNavigationButton' => $websiteNavigationButton,
            'publicBanners' => $publicBanners,
            'publicSpecialties' => $publicSpecialties,
            'hasConfiguredMenus' => $publicMenus->isNotEmpty(),
            'websiteFooter' => $websiteFooter,
            'websiteSocialSettings' => $websiteSocialSettings,
            'websiteTopbar' => $websiteTopbar,
        ]);
    }

    private function publicMenus(int $customerId)
    {
        return Menu::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->orderByRaw('CASE WHEN position < 1 THEN 1 ELSE 0 END')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

}
