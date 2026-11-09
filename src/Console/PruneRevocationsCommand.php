<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Isolatable;
use Pushery\SanctumHardening\Tokens\TokenRevocations;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Delete the tokens revoked longer ago than `sanctum-hardening.revocations.keep_days`, with the record
 * of who revoked them and their prefixes. Schedule it daily.
 */
#[AsCommand(name: 'sanctum-hardening:prune-revocations')]
final class PruneRevocationsCommand extends Command implements Isolatable
{
    /** @var string */
    protected $signature = 'sanctum-hardening:prune-revocations';

    /** @var string */
    protected $description = 'Delete revoked tokens and the records of their revocation once the retention has passed';

    public function handle(TokenRevocations $revocations, Repository $config): int
    {
        $setting = $config->get('sanctum-hardening.revocations.keep_days');
        $days = is_int($setting) && $setting >= 1 ? $setting : 90;
        $pruned = $revocations->prune($days);

        $this->components->info($pruned === 1 ? "Deleted 1 token revoked more than {$days} days ago." : "Deleted {$pruned} tokens revoked more than {$days} days ago.");

        return self::SUCCESS;
    }
}
