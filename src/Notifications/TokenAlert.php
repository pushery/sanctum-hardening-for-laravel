<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Notifications;

use DateTimeImmutable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\HtmlString;
use Pushery\SanctumHardening\Support\MarkdownText;

/**
 * A mail to the holder of a token about one occasion: the token's name, the start of its secret where
 * the package minted it, the day the occasion falls on, and the way to the page where the token is
 * revoked. The day is written in the language the mail is sent in.
 *
 * The lines of a mail are Markdown, and the name is what somebody typed, the start of the secret what
 * the application's `sanctum.token_prefix` begins it with: both go into a line as text, so a name in the
 * form of a link or an image arrives as those characters (Support\MarkdownText). The subject is a
 * header, not Markdown, and names the token as it was typed.
 */
abstract class TokenAlert extends Notification
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $prefix,
        public readonly DateTimeImmutable $at,
        public readonly string $link,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    abstract public function toMail(object $notifiable): MailMessage;

    /** The mail with a subject and its own lines, ending in the token's prefix, what to do about it and the link. */
    protected function mail(string $subject, HtmlString|string ...$lines): MailMessage
    {
        return (new MailMessage)
            ->subject($subject)
            ->lines($lines)
            ->lineIf($this->prefix !== null, self::markdown('prefix', ['prefix' => (string) $this->prefix]))
            ->line(self::text('revoke'))
            ->action(self::text('open'), $this->link);
    }

    /** The day of the occasion, as the mail's language writes a date. */
    protected function day(): string
    {
        $translator = Container::getInstance()->make(Translator::class);
        $format = $translator->get('sanctum-hardening::messages.screen.formats.date');

        return Date::instance($this->at)->settings(['locale' => $translator->getLocale()])->isoFormat(is_string($format) ? $format : 'L');
    }

    /**
     * A line of the alerts in the mail's language.
     *
     * @param  array<string, string>  $replace
     */
    protected static function text(string $key, array $replace = []): string
    {
        $line = Container::getInstance()->make(Translator::class)->get('sanctum-hardening::messages.alerts.'.$key, $replace);

        return is_string($line) ? $line : $key;
    }

    /**
     * A line of the alerts in the mail's language for the Markdown of a mail, each value set into it as text.
     *
     * @param  array<string, string>  $values
     */
    protected static function markdown(string $key, array $values): HtmlString
    {
        return MarkdownText::interpolate(self::text($key), $values);
    }
}
