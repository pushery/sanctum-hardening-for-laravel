<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Route;
use Pushery\SanctumHardening\Http\RecordsTokenUse;

/**
 * The API of an application, behind Sanctum's token guard and the package's throttle, and the switch
 * that removes it.
 *
 * Switched off, routes() registers nothing: the API's routes do not exist, so a request meets no 403
 * and no 404 from a route that refuses, but no route at all, and `route:list` names none.
 */
final class SanctumHardening
{
    /** The middleware the routes run behind where the settings name none. */
    private const array SHIPPED_MIDDLEWARE = ['api', 'auth:sanctum'];

    /** Whether the API is switched on. */
    public static function enabled(): bool
    {
        return self::setting('enabled') !== false;
    }

    /**
     * Register the application's API routes under the configured prefix, behind the configured
     * middleware and the throttle of the package's limiter, or none at all while the API is off. Every
     * request a token makes there is recorded, see TokenUses.
     */
    public static function routes(Closure $routes): void
    {
        if (! self::enabled()) {
            return;
        }

        $prefix = self::setting('routes.prefix');
        $limiter = self::setting('throttle.limiter');

        Route::prefix(is_string($prefix) ? $prefix : 'api')
            ->middleware([
                ...self::middleware(),
                RecordsTokenUse::class,
                'throttle:'.(is_string($limiter) && $limiter !== '' ? $limiter : 'sanctum-hardening'),
            ])
            ->group($routes);
    }

    /**
     * The middleware the routes run behind: the configured list, a single middleware given as a
     * string, or the shipped group where the setting names none, so that an empty or unreadable
     * setting never leaves the routes without the token guard.
     *
     * @return list<string>
     */
    private static function middleware(): array
    {
        $configured = self::setting('routes.middleware');
        $configured = is_string($configured) ? [$configured] : $configured;
        $named = [];

        foreach (is_array($configured) ? $configured : [] as $name) {
            if (is_string($name) && trim($name) !== '') {
                $named[] = trim($name);
            }
        }

        return $named === [] ? self::SHIPPED_MIDDLEWARE : $named;
    }

    private static function setting(string $key): mixed
    {
        return Container::getInstance()->make(Repository::class)->get('sanctum-hardening.'.$key);
    }
}
