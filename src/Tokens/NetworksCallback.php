<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Sanctum's authentication callback while the package checks networks: the callback the application
 * gave Sanctum answers first, where it gave one, and a token passes only where its networks admit the
 * request as well.
 *
 * @internal
 */
final class NetworksCallback
{
    /** @var mixed the callback the application gave Sanctum, or null where it gave none, which Sanctum keeps untyped */
    public static mixed $application = null;

    /**
     * @param  mixed  $token  the token Sanctum hands its callback, which its API leaves untyped
     */
    public function __invoke(mixed $token, bool $isValid): bool
    {
        $application = self::$application;
        $valid = is_callable($application) ? (bool) $application($token, $isValid) : $isValid;
        $container = Container::getInstance();

        return $valid && (! $token instanceof Model || $container->make(TokenNetworks::class)->admits($token, $container->make(Request::class)->ip()));
    }
}
