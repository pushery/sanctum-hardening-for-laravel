<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Throttling;

use Illuminate\Database\Eloquent\Model;

/** The default: the quickstart names the limits the package's limiter counts with, the same for every tokenable. */
final readonly class ThrottleQuickstartLimits implements ResolvesQuickstartLimits
{
    public function __construct(
        private TokenThrottle $throttle,
    ) {}

    public function limitsFor(Model $tokenable): QuickstartLimits
    {
        return $this->limits();
    }

    /** The limits of the package's limiter, which needs no tokenable to name them. */
    public function limits(): QuickstartLimits
    {
        return new QuickstartLimits($this->throttle->perToken(), $this->throttle->perUser());
    }
}
