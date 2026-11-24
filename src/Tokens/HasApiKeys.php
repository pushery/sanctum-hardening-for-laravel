<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeInterface;
use Illuminate\Container\Container;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum's `HasApiTokens`, with a `createToken()` that hands out a key in the key format.
 *
 * Sanctum's own `createToken()` hands out `<row id>|<text>`, a value the package's token model does
 * not find. The self-service and PersonalTokens::mintForSystem() mint keys whichever trait a model
 * uses. A model whose tokens are also minted by the application's own code, through `createToken()`,
 * uses this trait in place of Sanctum's, so those tokens are keys as well.
 */
trait HasApiKeys
{
    /** @use HasApiTokens<PersonalAccessToken> */
    use HasApiTokens;

    /**
     * Mint a token whose plain text is a key in the key format. A model that implements
     * RefusesNewTokens is asked first, as on every other way to a token.
     *
     * @param  array<array-key, mixed>  $abilities
     *
     * @throws MintRefused where the model refuses the new token
     */
    public function createToken(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        return Container::getInstance()->make(Keys::class)->mint($this, $name, $abilities, $expiresAt);
    }
}
