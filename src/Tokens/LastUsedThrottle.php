<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Sanctum writes `last_used_at` on every request a token makes. This lets that write through once
 * every `last_used_at.every` seconds, so a busy token does not turn each request into a write on the
 * same row. A save that changes anything else goes through as it is.
 *
 * Asked from the token model's `saving` event, which every Sanctum 4 release saves the last use
 * through, so the package needs no setting of a particular release.
 */
final class LastUsedThrottle
{
    /** Whether the save of a token writes nothing but a last use within the interval, and is held back. */
    public static function holdsBack(Model $token): bool
    {
        if (! $token->exists || array_keys($token->getDirty()) !== ['last_used_at']) {
            return false;
        }

        $previous = $token->getOriginal('last_used_at');
        $every = Container::getInstance()->make(Repository::class)->get('sanctum-hardening.last_used_at.every');

        return $previous instanceof DateTimeInterface && Date::now()->getTimestamp() - $previous->getTimestamp() < (is_int($every) && $every >= 1 ? $every : 60);
    }
}
