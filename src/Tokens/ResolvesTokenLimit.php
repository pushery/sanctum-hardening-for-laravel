<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Illuminate\Database\Eloquent\Model;

/**
 * The application's own limit of tokens for a tokenable, a plan's for instance: a user, or the
 * workspace a token is minted for. Where it gives one, the lower of it and
 * `minting.max_tokens_per_user` counts.
 */
interface ResolvesTokenLimit
{
    /** How many tokens that have not expired the tokenable may hold, or null where the application sets no limit. */
    public function limitFor(Model $tokenable): ?int;
}
