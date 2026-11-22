<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Throttling;

use Illuminate\Database\Eloquent\Model;

/** The default: the application gives no tiers, so every holder counts against the one `throttle.*` configures. */
final class NoThrottleTier implements ResolvesThrottleTier
{
    public function tierFor(Model $holder): ?ThrottleTier
    {
        return null;
    }
}
