<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Pushery\SanctumHardening\Tokens\TokenAlerts;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Mail the holder of each token that expires soon or has gone unused, once for the same expiry and the
 * same last use. Schedule it daily.
 */
#[AsCommand(name: 'sanctum-hardening:send-alerts')]
final class SendAlertsCommand extends Command implements Isolatable
{
    /** @var string */
    protected $signature = 'sanctum-hardening:send-alerts';

    /** @var string */
    protected $description = 'Mail the holders of tokens that expire soon or have gone unused';

    public function handle(TokenAlerts $alerts): int
    {
        $sent = $alerts->send();

        $this->components->info(sprintf('Sent %s and %s.', $this->count($sent['expiring'], 'expiry alert'), $this->count($sent['inactive'], 'inactivity alert')));

        if ($sent['unreachable'] > 0) {
            $this->components->warn($sent['unreachable'] === 1 ? 'Skipped 1 token whose holder receives no mail.' : "Skipped {$sent['unreachable']} tokens whose holders receive no mail.");
        }

        if ($sent['unlinked'] > 0) {
            $this->components->error(($sent['unlinked'] === 1 ? 'Skipped 1 token without a page to revoke it on.' : "Skipped {$sent['unlinked']} tokens without a page to revoke them on.").' Name the page in sanctum-hardening.alerts.link.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function count(int $count, string $noun): string
    {
        return $count === 1 ? "1 {$noun}" : "{$count} {$noun}s";
    }
}
