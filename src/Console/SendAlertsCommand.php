<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Pushery\SanctumHardening\Tokens\TokenAlerts;
use Pushery\SanctumHardening\Tokens\Tokens;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Mail the holder of each token that expires soon or has gone unused, once for the same expiry and the
 * same last use. Schedule it daily. It fails while a token due for a mail has no page to link, and when a
 * mail could not be sent. Where Sanctum writes no last use, it sends no mail about a token gone unused and
 * says why.
 */
#[AsCommand(name: 'sanctum-hardening:send-alerts')]
final class SendAlertsCommand extends Command implements Isolatable
{
    /** @var string */
    protected $signature = 'sanctum-hardening:send-alerts';

    /** @var string */
    protected $description = 'Mail the holders of tokens that expire soon or have gone unused';

    /**
     * One run at a time without the `--isolated` flag. Two runs at once, from the schedulers of two servers for
     * instance, would each read the alerts mailed before either recorded its own, and mail every alert due twice.
     *
     * @var bool
     */
    protected $isolated = true;

    public function handle(TokenAlerts $alerts): int
    {
        $sent = $alerts->send();

        $this->components->info(sprintf('Sent %s and %s.', $this->count($sent['expiring'], 'expiry alert'), $this->count($sent['inactive'], 'inactivity alert')));

        if ($alerts->enabled(TokenAlerts::INACTIVE) && ! Tokens::lastUseIsRecorded()) {
            $this->components->warn('Sent no inactivity alert: Sanctum writes no last use while sanctum.last_used_at is off, so a token used yesterday reads as one never used. Switch the alert off with sanctum-hardening.alerts.inactive.enabled, or let Sanctum write the last use.');
        }

        if ($sent['unreachable'] > 0) {
            $this->components->warn($sent['unreachable'] === 1 ? 'Skipped 1 token whose holder receives no mail.' : "Skipped {$sent['unreachable']} tokens whose holders receive no mail.");
        }

        if ($sent['unlinked'] > 0) {
            $this->components->error(($sent['unlinked'] === 1 ? 'Skipped 1 token without a page to revoke it on.' : "Skipped {$sent['unlinked']} tokens without a page to revoke them on.").' Name the page in sanctum-hardening.alerts.link.');
        }

        if ($sent['failed'] > 0) {
            $this->components->error($sent['failed'] === 1
                ? 'Could not send 1 mail; its exception went to the exception handler, and the next run tries it again.'
                : "Could not send {$sent['failed']} mails; their exceptions went to the exception handler, and the next run tries them again.");
        }

        return $sent['unlinked'] > 0 || $sent['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function count(int $count, string $noun): string
    {
        return $count === 1 ? "1 {$noun}" : "{$count} {$noun}s";
    }
}
