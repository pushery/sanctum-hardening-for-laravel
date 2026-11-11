<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Notifications;

use DateTimeImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Override;

/**
 * The mail to the holder of a token no request has used for `sanctum-hardening.alerts.inactive.days`
 * days: `$at` is the last use, or the creation of a token that was never used.
 */
final class TokenInactive extends TokenAlert
{
    public function __construct(string $name, ?string $prefix, DateTimeImmutable $at, string $link, public readonly bool $neverUsed = false)
    {
        parent::__construct($name, $prefix, $at, $link);
    }

    #[Override]
    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['name' => $this->name, 'date' => $this->day()];
        $case = $this->neverUsed ? 'never' : 'used';

        return $this->mail(self::text("inactive.subject_{$case}", $replace), self::text("inactive.line_{$case}", $replace), self::text('inactive.risk'));
    }
}
