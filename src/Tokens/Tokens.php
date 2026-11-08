<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;
use LogicException;

/**
 * The token model Sanctum is set to, `Sanctum::usePersonalAccessTokenModel()` included, and what a row
 * of it says.
 *
 * @internal
 */
final class Tokens
{
    /** A fresh instance of the token model, to query its table. */
    public static function model(): Model
    {
        $class = Sanctum::$personalAccessTokenModel;
        $model = new $class;

        // @phpstan-ignore instanceof.alwaysTrue (Sanctum types the property, but a host can set any class name on it at runtime)
        return $model instanceof Model ? $model : throw new LogicException("Sanctum's token model [{$class}] is no Eloquent model.");
    }

    /** Whether a token has expired: one with an expiry that has passed. */
    public static function expired(Model $token): bool
    {
        $expiresAt = $token->getAttribute('expires_at');

        return $expiresAt instanceof DateTimeInterface && $expiresAt->getTimestamp() <= Date::now()->getTimestamp();
    }

    /**
     * The abilities a token row carries.
     *
     * @return array<array-key, mixed>
     */
    public static function abilitiesOf(Model $token): array
    {
        $abilities = $token->getAttribute('abilities');

        return is_array($abilities) ? $abilities : [];
    }
}
