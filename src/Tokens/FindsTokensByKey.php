<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Illuminate\Container\Container;
use SensitiveParameter;

/**
 * Finds a token by a key in the key format, for Sanctum's token model.
 *
 * Sanctum's guard calls the model's static `findToken()` with whatever followed `Bearer`. Its own
 * version takes the running number before a `|` and asks the database for every other value. Here
 * the form and the checksum come first, without a query, so a mistyped key, a made-up one and a
 * token of Sanctum's own format are refused before the database is asked. A key that passes is
 * found by its identifier, and the SHA-256 of the whole key is compared with the token's in
 * constant time.
 *
 * The package sets SanctumHardeningPersonalAccessToken as Sanctum's model, which uses this. An
 * application that sets a token model of its own uses this trait on it.
 */
trait FindsTokensByKey
{
    /**
     * The token a key belongs to, or null.
     *
     * @param  mixed  $token  the value that followed `Bearer`, which Sanctum hands over as a string
     */
    public static function findToken(#[SensitiveParameter] $token): ?static
    {
        $key = is_string($token) ? $token : '';
        $parsed = KeyFormat::parse($key);

        if (! $parsed instanceof ParsedKey) {
            return null;
        }

        $tokenId = Container::getInstance()->make(KeyIds::class)->tokenOf($parsed->id);

        if ($tokenId === null) {
            return null;
        }

        $instance = static::query()->whereKey($tokenId)->first();

        if (! $instance instanceof static) {
            return null;
        }

        $stored = $instance->getAttribute('token');

        return is_string($stored) && hash_equals($stored, hash('sha256', $key)) ? $instance : null;
    }
}
