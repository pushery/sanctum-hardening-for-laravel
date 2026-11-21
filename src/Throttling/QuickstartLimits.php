<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Throttling;

/**
 * The requests a minute the token screen's quickstart names: for each token, and for all tokens of
 * their holder together, each a whole number of one or more. A null leaves its part of the sentence
 * out, and two nulls leave out the sentence.
 */
final readonly class QuickstartLimits
{
    public function __construct(
        public ?int $perToken,
        public ?int $perHolder,
    ) {}
}
