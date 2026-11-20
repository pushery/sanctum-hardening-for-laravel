<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Pushery\SanctumHardening\SanctumHardening;

/**
 * Answer a request with the 404 of a route that is not there while the API is switched off.
 *
 * SanctumHardening::routes() registers no route while the API is off, but it runs only where the
 * routes are registered. A host that caches its routes loads the table it cached instead, which keeps
 * every route registered while the API was on until the routes are cached again. This middleware
 * reads the switch on every request, so such a route goes silent as soon as the configuration says
 * off. SanctumHardening::routes() puts it first on every route it registers, before the token guard,
 * so a request without a token meets the same 404; a route of the application's own takes it by its
 * class name.
 */
final readonly class EnsureApiIsEnabled
{
    /**
     * @return mixed what the rest of the middleware returns, which Laravel leaves untyped
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! SanctumHardening::enabled()) {
            // A table without routes matches no request, so this throws the exception the router throws
            // where no route matches, and the application answers it as it answers a route that is not there.
            new RouteCollection()->match($request);
        }

        return $next($request);
    }
}
