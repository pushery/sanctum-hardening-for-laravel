<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The requests each token made, kept for `usage.keep_days` days.
 *
 * A use names the route by its name, or by its URI pattern where it has none, never by the address
 * with its values, and keeps no body: nothing a person sent is stored. The writes are held back: the
 * same token, route, method and status are written at most once every `usage.every` seconds, so a
 * busy token does not turn every request into a row.
 */
final readonly class TokenUses
{
    public const string TABLE = 'sanctum_hardening_token_uses';

    /** Record a request a token made, unless the same one was recorded within the interval. */
    public function record(Model $token, string $method, string $route, int $status): void
    {
        $key = Tokens::key($token->getKey());

        if (! Cache::add('sanctum-hardening:use:'.hash('sha256', "{$key} {$method} {$route} {$status}"), true, $this->setting('every', 60))) {
            return;
        }

        DB::table(self::TABLE)->insert([
            'token_id' => $key,
            'method' => mb_substr($method, 0, 10),
            'route' => mb_substr($route, 0, 255),
            'status' => $status,
            'used_at' => Moments::bound(Date::now()),
        ]);
    }

    /**
     * The latest uses of a token, the newest first.
     *
     * @return list<TokenUse>
     */
    public function latest(Model $token, int $count = 5): array
    {
        $uses = [];

        $rows = DB::table(self::TABLE)
            ->where('token_id', Tokens::key($token->getKey()))
            ->orderByDesc('used_at')
            ->orderByDesc('id')
            ->limit(max(1, $count))
            ->get();

        foreach ($rows as $row) {
            $uses[] = $this->useOf($row);
        }

        return $uses;
    }

    /**
     * The latest uses of each of the given tokens, by token key, the newest first, read in one query for all of them:
     * a list of tokens costs as much as a single token. A key that is a number, as Sanctum's are, is an integer
     * key of the array, because PHP stores it as one.
     *
     * @param  iterable<Model>  $tokens
     * @return array<array-key, list<TokenUse>>
     */
    public function latestOf(iterable $tokens, int $count = 5): array
    {
        $uses = [];
        $keys = [];

        foreach ($tokens as $token) {
            $key = Tokens::key($token->getKey());
            $uses[$key] = [];
            $keys[] = $key;
        }

        if ($keys === []) {
            return [];
        }

        // Numbered within each token, the newest first, and cut after `$count`; the rows come ordered by that number,
        // so each token's come newest first.
        $rows = DB::table(self::TABLE)
            ->whereIn('token_id', $keys)
            ->orderByDesc('used_at')
            ->orderByDesc('id')
            ->groupLimit(max(1, $count), 'token_id')
            ->get();

        foreach ($rows as $row) {
            $uses[$this->text($row->token_id ?? null)][] = $this->useOf($row);
        }

        return $uses;
    }

    /** A use as a row of the table holds it. */
    private function useOf(stdClass $row): TokenUse
    {
        return new TokenUse(
            $this->text($row->method ?? null),
            $this->text($row->route ?? null),
            is_numeric($row->status ?? null) ? (int) $row->status : 0,
            new DateTimeImmutable($this->text($row->used_at ?? null)),
        );
    }

    /**
     * Whether a token made a request between two moments, both included. The moments are compared in
     * the application's time zone, the one a use is written in, whichever zone they are given in.
     */
    public function usedBetween(Model $token, DateTimeInterface $from, DateTimeInterface $until): bool
    {
        $zone = new DateTimeZone(date_default_timezone_get());

        return DB::table(self::TABLE)
            ->where('token_id', Tokens::key($token->getKey()))
            ->whereBetween('used_at', [Moments::bound(DateTimeImmutable::createFromInterface($from)->setTimezone($zone)), Moments::bound(DateTimeImmutable::createFromInterface($until)->setTimezone($zone))])
            ->exists();
    }

    /** Delete the uses older than `$days` days, and say how many. */
    public function prune(int $days, int $chunk = 500): int
    {
        $size = max(1, $chunk);
        $before = Date::now()->subDays($days);
        $pruned = 0;

        // By key rather than by offset, so the rows deleted on the way make the walk skip none.
        foreach (DB::table(self::TABLE)->where('used_at', '<', Moments::bound($before))->select('id')->lazyById($size)->chunk($size) as $rows) {
            $pruned += DB::table(self::TABLE)->whereIn('id', $rows->pluck('id')->all())->delete();
        }

        return $pruned;
    }

    /** A whole number of one or more from the usage settings, or the shipped value where the setting is none. */
    private function setting(string $key, int $shipped): int
    {
        $value = Container::getInstance()->make(Repository::class)->get('sanctum-hardening.usage.'.$key);

        return is_int($value) && $value >= 1 ? $value : $shipped;
    }

    /**
     * A value a row holds as the text it is stored as.
     *
     * @param  mixed  $value  the value of a column, as the connection returns it, which differs between the engines
     */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
