<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Throttling;

use Illuminate\Database\Eloquent\Model;

/**
 * The tier a holder's requests count against, given by the application: for a user, or the workspace
 * a token was minted for, from the plan it booked for instance. The package's limiter asks on every
 * request it counts, so a changed tier counts at once, and a lookup that costs keeps a cache of its own.
 * Where it gives no tier, the one `throttle.*` configures counts.
 */
interface ResolvesThrottleTier
{
    /** The holder's tier, or null for the one `throttle.*` configures. */
    public function tierFor(Model $holder): ?ThrottleTier;
}
