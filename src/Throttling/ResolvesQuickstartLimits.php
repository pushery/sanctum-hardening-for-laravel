<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Throttling;

use Illuminate\Database\Eloquent\Model;

/**
 * The limits the token screen's quickstart names for a tokenable: a user, or the workspace a screen
 * manages the tokens of. Bind one where the API runs behind a limiter of the application's own, a
 * plan's for instance, so that the sentence names what that limiter allows. Unbound, the quickstart
 * names the limits of the package's limiter.
 */
interface ResolvesQuickstartLimits
{
    public function limitsFor(Model $tokenable): QuickstartLimits;
}
