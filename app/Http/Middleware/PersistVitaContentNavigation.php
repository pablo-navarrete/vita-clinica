<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PersistVitaContentNavigation
{
    public function handle(Request $request, Closure $next)
    {
        $referer = parse_url($request->headers->get('referer', ''));
        parse_str($referer['query'] ?? '', $refererQuery);
        if ($request->header('Sec-Fetch-Dest') === 'iframe' && !$request->query->has('_vita_content') && ! $request->has('signature')
            && ($referer['host'] ?? null) === $request->getHost()
            && ($refererQuery['_vita_content'] ?? null) === '1') {
            $request->query->set('_vita_content', 1);
        }
        $response = $next($request);
        if ($request->boolean('_vita_content') && !$request->ajax() && $response instanceof RedirectResponse) {
            $target = $response->getTargetUrl();
            $parts = parse_url($target);
            $sameOrigin = !isset($parts['host']) || ($parts['host'] === $request->getHost()
                && ($parts['scheme'] ?? $request->getScheme()) === $request->getScheme()
                && ($parts['port'] ?? $request->getPort()) === $request->getPort());
            $path = $parts['path'] ?? '/';
            parse_str($parts['query'] ?? '', $query);
            if ($sameOrigin && !isset($query['signature']) && !in_array($path, ['/login', '/logout'])) {
                $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
                $base = explode('?', explode('#', $target, 2)[0], 2)[0];
                $query['_vita_content'] = 1;
                $response->setTargetUrl($base . '?' . http_build_query($query) . $fragment);
            }
        }
        return $response;
    }
}
