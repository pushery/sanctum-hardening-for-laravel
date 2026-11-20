<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Pushery\SanctumHardening\Abilities\AbilityCatalog;
use Pushery\SanctumHardening\Notifications\TokenExpiring;
use Pushery\SanctumHardening\Notifications\TokenInactive;
use Throwable;

/**
 * The mails to the holder of a token before it expires and once it has gone unused.
 *
 * Each goes out once for the same occasion: once for an expiry, and once for a last use, so a token that
 * is used again and then falls silent again is reported again. A token with a reserved ability is left
 * out, because the screen the mail sends its holder to neither lists nor revokes it, and so is one that
 * has expired or was revoked.
 *
 * A mail that cannot be sent, because the transport refuses its recipient for instance, ends that mail
 * alone: its exception goes to the application's exception handler, it is counted as failed, and the
 * next token is mailed. It is not recorded as sent, so the next run tries it again.
 */
final readonly class TokenAlerts
{
    public const string TABLE = 'sanctum_hardening_token_alerts';

    public const string EXPIRING = 'expiring';

    public const string INACTIVE = 'inactive';

    public function __construct(
        private Repository $config,
        private AbilityCatalog $catalog,
        private TokenPrefixes $prefixes,
        private ResolvesTokenAlerts $resolver,
        private ExceptionHandler $exceptions,
    ) {}

    /**
     * Mail the holder of every token that expires within `expiring.days` days, and of every one no request
     * used for `inactive.days` days, a token never used counted from its creation. Say how many mails went
     * out of each kind, how many tokens were passed over for want of a recipient or of a page to link, and
     * how many mails could not be sent.
     *
     * The second kind goes out only where Sanctum writes the last use of a token: without it, nothing tells
     * a token used yesterday from one never used, and every token would read as unused.
     *
     * @return array{expiring: int, inactive: int, unreachable: int, unlinked: int, failed: int}
     */
    public function send(int $chunk = 200): array
    {
        $now = Date::now()->toImmutable();
        $model = Tokens::model();
        $expiresAt = $model->qualifyColumn('expires_at');
        $counts = ['expiring' => 0, 'inactive' => 0, 'unreachable' => 0, 'unlinked' => 0, 'failed' => 0];

        if ($this->enabled(self::EXPIRING)) {
            $due = $model->newQuery()
                ->where($expiresAt, '>', $now)
                ->where($expiresAt, '<=', $now->addDays($this->days(self::EXPIRING, 7)));

            $counts = $this->sendFor(self::EXPIRING, $due, $chunk, $counts);
        }

        if ($this->enabled(self::INACTIVE) && Tokens::lastUseIsRecorded()) {
            $before = $now->subDays($this->days(self::INACTIVE, 90));
            $lastUsedAt = $model->qualifyColumn('last_used_at');
            $createdAt = $model->qualifyColumn('created_at');

            $due = $model->newQuery()
                ->where(static fn (Builder $query): Builder => $query->whereNull($expiresAt)->orWhere($expiresAt, '>', $now))
                ->where(static fn (Builder $query): Builder => $query
                    ->where($lastUsedAt, '<=', $before)
                    ->orWhere(static fn (Builder $never): Builder => $never->whereNull($lastUsedAt)->where($createdAt, '<=', $before)));

            $counts = $this->sendFor(self::INACTIVE, $due, $chunk, $counts);
        }

        return $counts;
    }

    /**
     * Forget the alerts of a token that was deleted.
     *
     * @param  mixed  $tokenKey  the key of the token as its model holds it, an integer or a string by the type of the key
     */
    public function forget(mixed $tokenKey): void
    {
        DB::table(self::TABLE)->where('token_id', TokenPrefixes::key($tokenKey))->delete();
    }

    /**
     * Forget the alerts of tokens that no longer exist, and say of how many tokens, the way
     * TokenPrefixes::prune() forgets their prefixes.
     */
    public function prune(int $chunk = 500): int
    {
        $model = Tokens::model();
        $size = max(1, $chunk);
        $pruned = 0;

        // By key rather than by offset, so the rows deleted on the way make the walk skip none.
        foreach (DB::table(self::TABLE)->select(['id', 'token_id'])->lazyById($size)->chunk($size) as $rows) {
            $keys = array_values(array_unique(array_filter($rows->pluck('token_id')->all(), is_string(...))));
            $existing = array_map(TokenPrefixes::key(...), $model->newQuery()->whereIn($model->getKeyName(), $keys)->pluck($model->getKeyName())->all());
            $gone = array_values(array_diff($keys, $existing));

            if ($gone !== []) {
                DB::table(self::TABLE)->whereIn('token_id', $gone)->delete();
                $pruned += count($gone);
            }
        }

        return $pruned;
    }

    /**
     * Mail the holders of the tokens a query finds about one kind of occasion, and add what happened to
     * the counts.
     *
     * @param  self::EXPIRING|self::INACTIVE  $kind
     * @param  Builder<Model>  $due
     * @param  array{expiring: int, inactive: int, unreachable: int, unlinked: int, failed: int}  $counts
     * @return array{expiring: int, inactive: int, unreachable: int, unlinked: int, failed: int}
     */
    private function sendFor(string $kind, Builder $due, int $chunk, array $counts): array
    {
        $size = max(1, $chunk);

        foreach ($due->with('tokenable')->lazyById($size)->chunk($size) as $tokens) {
            $keys = $tokens->map(static fn (Model $token): string => TokenPrefixes::key($token->getKey()))->values()->all();
            $mailed = DB::table(self::TABLE)->where('kind', $kind)->whereIn('token_id', $keys)->pluck('occasion_at', 'token_id')->all();
            $prefixes = $this->prefixes->of($tokens);

            foreach ($tokens as $token) {
                $key = TokenPrefixes::key($token->getKey());
                $occasion = $this->occasionOf($kind, $token);

                if (! $occasion instanceof DateTimeImmutable || $this->catalog->holdsReserved(Tokens::abilitiesOf($token)) || $this->sameMoment($mailed[$key] ?? null, $occasion)) {
                    continue;
                }

                $recipient = $this->resolver->recipient($token);

                if ($recipient === null) {
                    $counts['unreachable']++;

                    continue;
                }

                $link = $this->resolver->link($token);

                if ($link === null) {
                    $counts['unlinked']++;

                    continue;
                }

                $name = $token->getAttribute('name');
                $name = is_scalar($name) ? (string) $name : '';
                $prefix = $prefixes[$key] ?? null;

                try {
                    Notification::send($recipient, $kind === self::EXPIRING
                        ? new TokenExpiring($name, $prefix, $occasion, $link)
                        : new TokenInactive($name, $prefix, $occasion, $link, neverUsed: ! $token->getAttribute('last_used_at') instanceof DateTimeInterface));
                } catch (Throwable $failure) {
                    // One refused recipient ends its own mail, not the run. Without a row for it, the next run
                    // tries it again.
                    $this->exceptions->report($failure);
                    $counts['failed']++;

                    continue;
                }

                DB::table(self::TABLE)->upsert(
                    [['token_id' => $key, 'kind' => $kind, 'occasion_at' => Moments::bound($occasion), 'sent_at' => Moments::bound(Date::now())]],
                    ['token_id', 'kind'],
                    ['occasion_at', 'sent_at'],
                );
                $counts[$kind]++;
            }
        }

        return $counts;
    }

    /**
     * The moment an alert of `$kind` is about: the expiry, or the last use and the creation where there is none.
     *
     * @param  self::EXPIRING|self::INACTIVE  $kind
     */
    private function occasionOf(string $kind, Model $token): ?DateTimeImmutable
    {
        $moment = $kind === self::EXPIRING
            ? $token->getAttribute('expires_at')
            : ($token->getAttribute('last_used_at') ?? $token->getAttribute('created_at'));

        return $moment instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($moment) : null;
    }

    /**
     * Whether the occasion a stored alert names is the moment given, to the second.
     *
     * @param  mixed  $stored  the moment a row stores, as the connection returns it, which differs between the engines
     */
    private function sameMoment(mixed $stored, DateTimeImmutable $moment): bool
    {
        return is_string($stored) && $stored !== '' && new DateTimeImmutable($stored)->getTimestamp() === $moment->getTimestamp();
    }

    /** Whether the alert of `$kind`, EXPIRING or INACTIVE, is on: anything but a setting that names off. */
    public function enabled(string $kind): bool
    {
        $value = $this->config->get("sanctum-hardening.alerts.{$kind}.enabled");

        return ! in_array(is_string($value) ? strtolower(trim($value)) : $value, [false, 0, '', '0', 'false', 'no', 'off'], true);
    }

    /** The days of an alert's setting, or the shipped value where the setting is no whole number of one or more. */
    private function days(string $kind, int $shipped): int
    {
        $value = $this->config->get("sanctum-hardening.alerts.{$kind}.days");

        return is_int($value) && $value >= 1 ? $value : $shipped;
    }
}
