<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Throttling;

use InvalidArgumentException;

/**
 * The limits a holder's requests count against: a counter for each of its tokens on its own, one for
 * the holder over all of its tokens, or both. A request goes through only while every counter it falls
 * under has room left. A tier without a counter for the holder lets the holder past every limit of a
 * token by minting another one.
 */
final readonly class ThrottleTier
{
    /**
     * @throws InvalidArgumentException where the tier counts neither the tokens nor their holder
     */
    public function __construct(
        public ?ThrottleCounter $perToken,
        public ?ThrottleCounter $perHolder,
    ) {
        if (! $perToken instanceof ThrottleCounter && ! $perHolder instanceof ThrottleCounter) {
            throw new InvalidArgumentException('A throttle tier counts the tokens, their holder or both.');
        }
    }
}
