<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeInterface;

/**
 * A model that holds tokens and can refuse a new one: a workspace that takes no change while it is
 * locked, for instance. Every way to a new token asks it before the token is written: the screen of
 * tokens, PersonalTokens::mint(), mintFor() and mintForSystem(), a rotation, and `createToken()` of
 * HasApiKeys. Keys::mint() is where they all meet, so the model is asked whichever way is taken.
 */
interface RefusesNewTokens
{
    /**
     * Why the model takes no new token right now, in a sentence for the person who asked for it, or
     * null where it takes this one. The sentence reaches a caller as the refusal of MintRefused and is
     * shown on the status line of the screen of tokens.
     *
     * @param  array<array-key, mixed>  $abilities  the abilities the new token would carry
     */
    public function refusalOfANewToken(string $name, array $abilities, ?DateTimeInterface $expiresAt): ?string;
}
