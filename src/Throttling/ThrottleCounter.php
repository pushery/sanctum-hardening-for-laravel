<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Throttling;

use InvalidArgumentException;

/**
 * What one counter of a tier allows: each token on its own, or their holder over all of its tokens.
 *
 * Every request counts against the requests a minute on average. A limit per second caps each second
 * on top of that. The writes a minute are a budget of their own for every request with a method other
 * than GET, HEAD, OPTIONS and TRACE, counted besides the rate: 0 refuses every write, and null leaves
 * writes to the rate alone. A burst lets a caller that was quiet send up to that many requests at once,
 * in a window of `burst / perMinute` minutes; a burst no larger than the rate keeps the window at the
 * minute.
 */
final readonly class ThrottleCounter
{
    /**
     * @throws InvalidArgumentException where a number lies outside its range
     */
    public function __construct(
        public int $perMinute,
        public ?int $perSecond = null,
        public ?int $writesPerMinute = null,
        public ?int $burst = null,
    ) {
        if ($perMinute < 1) {
            throw new InvalidArgumentException('A throttle counter allows one request a minute or more, not '.$perMinute.'.');
        }

        if ($perSecond !== null && $perSecond < 1) {
            throw new InvalidArgumentException('A limit per second allows one request or more, not '.$perSecond.'.');
        }

        if ($writesPerMinute !== null && $writesPerMinute < 0) {
            throw new InvalidArgumentException('A budget of writes allows 0 writes a minute or more, not '.$writesPerMinute.'.');
        }

        if ($burst !== null && $burst < 1) {
            throw new InvalidArgumentException('A burst allows one request or more, not '.$burst.'.');
        }
    }
}
