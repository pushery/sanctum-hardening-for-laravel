<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Isolatable;
use Pushery\SanctumHardening\Tokens\TokenUses;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Delete the recorded uses of tokens older than `sanctum-hardening.usage.keep_days`. Schedule it daily.
 */
#[AsCommand(name: 'sanctum-hardening:prune-uses')]
final class PruneUsesCommand extends Command implements Isolatable
{
    /** @var string */
    protected $signature = 'sanctum-hardening:prune-uses';

    /** @var string */
    protected $description = 'Delete the recorded uses of tokens once the retention has passed';

    public function handle(TokenUses $uses, Repository $config): int
    {
        $setting = $config->get('sanctum-hardening.usage.keep_days');
        $days = is_int($setting) && $setting >= 1 ? $setting : 30;
        $pruned = $uses->prune($days);

        $this->components->info($pruned === 1 ? "Deleted 1 use older than {$days} days." : "Deleted {$pruned} uses older than {$days} days.");

        return self::SUCCESS;
    }
}
