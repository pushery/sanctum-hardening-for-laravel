<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Override;

/**
 * The mail to the holder of a token that expires within `sanctum-hardening.alerts.expiring.days` days.
 */
final class TokenExpiring extends TokenAlert
{
    #[Override]
    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['name' => $this->name, 'date' => $this->day()];

        return $this->mail(self::text('expiring.subject', $replace), self::text('expiring.line', $replace));
    }
}
