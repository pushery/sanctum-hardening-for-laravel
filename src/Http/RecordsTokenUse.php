<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Http;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Pushery\SanctumHardening\Tokens\TokenUses;
use Symfony\Component\HttpFoundation\Response;

/**
 * Record the request a token made once the answer is sent: the route's name, or its URI pattern where
 * it has none, the method and the status. A request no token authenticated is not recorded.
 * SanctumHardening::routes() puts it on every route it registers; a route of the application's own
 * behind `auth:sanctum` takes it by its class name.
 */
final readonly class RecordsTokenUse
{
    public function __construct(private TokenUses $uses) {}

    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();
        // @phpstan-ignore function.alreadyNarrowedType (only the analyzed user model is known to use HasApiTokens; a host's model need not)
        $token = $user instanceof Model && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        $route = $request->route();

        if ($token instanceof Model && $route instanceof Route) {
            $this->uses->record($token, $request->getMethod(), $route->getName() ?? $route->uri(), $response->getStatusCode());
        }
    }
}
