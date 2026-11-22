<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum's token model, found by a key in the key format instead of a running number.
 *
 * The package sets it as Sanctum's model where the application keeps Sanctum's own. An application
 * with a token model of its own uses FindsTokensByKey on that model instead.
 */
class SanctumHardeningPersonalAccessToken extends PersonalAccessToken
{
    use FindsTokensByKey;

    /**
     * The table Sanctum's own model uses, so the class name of this one changes nothing about where tokens live.
     *
     * @var string
     */
    protected $table = 'personal_access_tokens';
}
