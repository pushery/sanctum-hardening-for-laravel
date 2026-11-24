<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

/**
 * A model that holds tokens and names the environment its keys carry, `live` or `test`, in place of the application's:
 * a workspace that only reaches the test mode of an API, for instance, whose keys a person or a secret scanner has to
 * tell from real ones in production. Every way to a new token asks it: the screen of tokens, PersonalTokens::mint(),
 * mintFor() and mintForSystem(), a rotation, and `createToken()` of HasApiKeys. Keys::mint() is where they all meet,
 * so the model is asked whichever way is taken.
 */
interface NamesItsKeyEnvironment
{
    /**
     * `live` or `test` for the keys of this model, or null where the environment of the application holds:
     * `keys.environment`, or `app.env`.
     */
    public function keyEnvironment(): ?string;
}
