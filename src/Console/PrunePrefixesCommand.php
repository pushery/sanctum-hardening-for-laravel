<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Pushery\SanctumHardening\Tokens\TokenNetworks;
use Pushery\SanctumHardening\Tokens\TokenPrefixes;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Forget the prefixes and the networks of tokens that are gone: the ones `sanctum:prune-expired`
 * deleted, and every other token deleted by a query rather than through its model. Schedule it beside
 * Sanctum's prune.
 */
#[AsCommand(name: 'sanctum-hardening:prune-prefixes')]
final class PrunePrefixesCommand extends Command implements Isolatable
{
    /** @var string */
    protected $signature = 'sanctum-hardening:prune-prefixes';

    /** @var string */
    protected $description = 'Forget the prefixes and the networks of tokens that no longer exist';

    public function handle(TokenPrefixes $prefixes, TokenNetworks $networks): int
    {
        $pruned = $prefixes->prune();
        $limits = $networks->prune();

        $this->components->info($pruned === 1 ? 'Forgot the prefix of 1 deleted token.' : "Forgot the prefixes of {$pruned} deleted tokens.");
        $this->components->info($limits === 1 ? 'Forgot the networks of 1 deleted token.' : "Forgot the networks of {$limits} deleted tokens.");

        return self::SUCCESS;
    }
}
