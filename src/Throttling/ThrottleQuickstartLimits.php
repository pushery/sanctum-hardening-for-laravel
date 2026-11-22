<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Throttling;

use Illuminate\Database\Eloquent\Model;

/**
 * The default: the quickstart names the limits the package's limiter counts the tokenable with, from its tier. A tier
 * without a counter for the tokens or for their holder leaves that part of the sentence out.
 */
final readonly class ThrottleQuickstartLimits implements ResolvesQuickstartLimits
{
    public function __construct(
        private TokenThrottle $throttle,
    ) {}

    public function limitsFor(Model $tokenable): QuickstartLimits
    {
        $tier = $this->throttle->tierFor($tokenable);

        return new QuickstartLimits($tier->perToken?->perMinute, $tier->perHolder?->perMinute);
    }

    /** The limits of the package's limiter, which needs no tokenable to name them. */
    public function limits(): QuickstartLimits
    {
        return new QuickstartLimits($this->throttle->perToken(), $this->throttle->perUser());
    }
}
