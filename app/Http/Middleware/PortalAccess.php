<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalAccess
{
    public static function localPreviewAllowed(): bool
    {
        $connection=config('database.connections.'.config('database.default'));
        return app()->environment('local') && ($connection['driver']??null)==='mysql'
            && in_array($connection['host']??null,['127.0.0.1','localhost','::1'],true);
    }

    public function handle(Request $request, Closure $next, string $level = 'account')
    {
        $guard=Auth::guard('mi_salud'); $account=$guard->user()?->fresh();
        if (!$account || !$account->active || (int)$account->session_version !== (int)$request->session()->get('portal_session_version')) {
            $guard->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
            if ($request->isMethod('GET') && $request->routeIs('mi-salud.reviews.show')) {
                $request->session()->put('portal_review_slug', (string)$request->route('slug'));
            }
            return redirect()->route('mi-salud.login');
        }
        $guard->setUser($account);
        $localPreview=self::localPreviewAllowed() && (int)$request->session()->get('portal_local_preview_account')===(int)$account->id;
        if ($level === 'verified' && !$account->email_verified_at && !$localPreview) return redirect()->route('mi-salud.verify.notice');
        return $next($request);
    }
}
