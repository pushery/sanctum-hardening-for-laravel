<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Pushery\SanctumHardening\Tokens\KeyIds;
use Pushery\SanctumHardening\Tokens\TokenAlerts;
use Pushery\SanctumHardening\Tokens\TokenNetworks;
use Pushery\SanctumHardening\Tokens\TokenRotations;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Forget the key prefixes and identifiers, the networks, the alerts and the rotations of tokens that are gone:
 * the ones `sanctum:prune-expired` deleted, and every other token deleted by a query rather than through its
 * model. Schedule it beside Sanctum's prune.
 */
#[AsCommand(name: 'sanctum-hardening:prune-prefixes')]
final class PrunePrefixesCommand extends Command implements Isolatable
{
    /** @var string */
    protected $signature = 'sanctum-hardening:prune-prefixes';

    /** @var string */
    protected $description = 'Forget the prefixes, the networks, the alerts and the rotations of tokens that no longer exist';

    /**
     * One run at a time without the `--isolated` flag, so the schedulers of two servers do not walk the same rows at
     * once.
     *
     * @var bool
     */
    protected $isolated = true;

    public function handle(KeyIds $keyIds, TokenNetworks $networks, TokenAlerts $alerts, TokenRotations $rotations): int
    {
        $pruned = $keyIds->prune();
        $limits = $networks->prune();
        $mailed = $alerts->prune();
        $replaced = $rotations->prune();

        $this->components->info($pruned === 1 ? 'Forgot the prefix of 1 deleted token.' : "Forgot the prefixes of {$pruned} deleted tokens.");
        $this->components->info($limits === 1 ? 'Forgot the networks of 1 deleted token.' : "Forgot the networks of {$limits} deleted tokens.");
        $this->components->info($mailed === 1 ? 'Forgot the alerts of 1 deleted token.' : "Forgot the alerts of {$mailed} deleted tokens.");
        $this->components->info($replaced === 1 ? 'Forgot the rotation of 1 deleted token.' : "Forgot the rotations of {$replaced} deleted tokens.");

        return self::SUCCESS;
    }
}
