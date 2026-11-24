<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Route;
use Pushery\SanctumHardening\Http\EnsureApiIsEnabled;
use Pushery\SanctumHardening\Http\RecordsTokenUse;
use Pushery\SanctumHardening\Tokens\Tokens;

/**
 * The API of an application, behind Sanctum's token guard and the package's throttle, and the switch
 * that removes it.
 *
 * Switched off, routes() registers nothing: the API's routes do not exist, so a request meets no 403
 * and no 404 from a route that refuses, but no route at all, and `route:list` names none. A route
 * registered while the API was on, which a host that caches its routes keeps until it caches them
 * again, answers the same 404 through EnsureApiIsEnabled, which reads the switch on every request.
 */
final class SanctumHardening
{
    /** The middleware the routes run behind where the settings name none. */
    private const array SHIPPED_MIDDLEWARE = ['api', 'auth:sanctum'];

    /**
     * A token's or a holder's key as the string the package's tables store it under, in `token_id`,
     * `tokenable_id`, `revoked_by_id` and `rotated_by_id`, so an application finds the rows of an account
     * or a workspace for an export or a deletion. A key that is no scalar gives an empty string, which no
     * row carries.
     *
     * @param  mixed  $key  the key as a model gives it, an int, a string or whatever a custom key type returns
     */
    public static function storedKey(mixed $key): string
    {
        return Tokens::key($key);
    }

    /**
     * The guards Sanctum asks for a signed-in user before it reads a bearer token: the names in
     * `sanctum-hardening.session_guards`, a list or one name as Sanctum takes it, none unless the
     * application names them. The provider sets `sanctum.guard` to this list.
     *
     * @return list<string>
     */
    public static function sessionGuards(Repository $config): array
    {
        $guards = $config->get('sanctum-hardening.session_guards');
        $names = [];

        foreach (is_array($guards) ? $guards : [$guards] as $guard) {
            if (is_string($guard) && trim($guard) !== '' && ! in_array(trim($guard), $names, true)) {
                $names[] = trim($guard);
            }
        }

        return $names;
    }

    /** Whether the API is switched on. */
    public static function enabled(): bool
    {
        return self::setting('enabled') !== false;
    }

    /**
     * Register the application's API routes under the configured prefix, behind the configured
     * middleware and the throttle of the package's limiter, or none at all while the API is off. Every
     * request a token makes there is recorded, see TokenUses. The switch comes first, before the token
     * guard, so a route that outlives the switch in a cached table answers every request with a 404.
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
                EnsureApiIsEnabled::class,
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

    /**
     * @return mixed the setting as the configuration holds it, which a host may write as any type
     */
    private static function setting(string $key): mixed
    {
        return Container::getInstance()->make(Repository::class)->get('sanctum-hardening.'.$key);
    }
}
