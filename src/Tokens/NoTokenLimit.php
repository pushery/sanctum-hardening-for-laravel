<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Illuminate\Database\Eloquent\Model;

/** The default: the application sets no limit of its own, so `minting.max_tokens_per_user` alone counts. */
final class NoTokenLimit implements ResolvesTokenLimit
{
    public function limitFor(Model $tokenable): ?int
    {
        return null;
    }
}
