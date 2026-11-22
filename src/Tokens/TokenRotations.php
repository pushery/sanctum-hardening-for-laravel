<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Which token replaced which, and until when the replaced one still holds.
 *
 * A rotation ends the replaced token through its expiry, as a revocation does, only later: Sanctum refuses a token
 * once its `expires_at` has passed, so whatever still calls with the old key keeps working until then and not a
 * moment after. The row keeps the successor and who rotated, so the screen can say until when a replaced token holds,
 * and the expiry alert names the end before it comes, as it does for any token about to expire.
 */
final readonly class TokenRotations
{
    public const string TABLE = 'sanctum_hardening_token_rotations';

    /**
     * Record that `$successor` replaces `$token`, and end `$token` at `$endsAt`, or at its own expiry where that comes
     * sooner. Whether the rotation was recorded: false where the token was rotated already.
     */
    public function record(Model $token, Model $successor, DateTimeInterface $endsAt, ?Model $by): bool
    {
        $now = Date::now();
        $expiresAt = $token->getAttribute('expires_at');
        $ends = $expiresAt instanceof DateTimeInterface && $expiresAt < $endsAt ? $expiresAt : $endsAt;

        return DB::connection()->transaction(static function () use ($token, $successor, $ends, $by, $now): bool {
            $recorded = DB::table(self::TABLE)->insertOrIgnore([
                'token_id' => Tokens::key($token->getKey()),
                'successor_id' => Tokens::key($successor->getKey()),
                'rotated_by_type' => $by?->getMorphClass(),
                'rotated_by_id' => $by instanceof Model ? Tokens::key($by->getKey()) : null,
                'rotated_at' => Moments::bound($now),
                'ends_at' => Moments::bound($ends),
            ]) > 0;

            if ($recorded) {
                $token->setAttribute('expires_at', $ends);
                $token->save();
            }

            return $recorded;
        });
    }

    /**
     * When each of these tokens that was replaced stops holding, by token key. A token that was not replaced has no
     * entry.
     *
     * @param  iterable<Model>  $tokens
     * @return array<string, DateTimeImmutable>
     */
    public function endsOf(iterable $tokens): array
    {
        $keys = [];

        foreach ($tokens as $token) {
            $keys[] = Tokens::key($token->getKey());
        }

        $ends = [];

        foreach (DB::table(self::TABLE)->whereIn('token_id', $keys)->get(['token_id', 'ends_at']) as $row) {
            $ends[$this->text($row->token_id ?? null)] = new DateTimeImmutable($this->text($row->ends_at ?? null));
        }

        return $ends;
    }

    /**
     * Forget the rotation of a token that is being deleted.
     *
     * @param  mixed  $tokenKey  the key of the token as its model holds it, an integer or a string by the type of the key
     */
    public function forget(mixed $tokenKey): void
    {
        DB::table(self::TABLE)->where('token_id', Tokens::key($tokenKey))->delete();
    }

    /**
     * Delete the rows of tokens that are gone, which a query that deleted tokens without their model leaves behind.
     * How many rows went.
     */
    public function prune(int $chunk = 500): int
    {
        $model = Tokens::model();
        $size = max(1, $chunk);
        $pruned = 0;

        // By key rather than by offset, so the rows deleted on the way make the walk skip none.
        foreach (DB::table(self::TABLE)->select('token_id')->lazyById($size, 'token_id')->chunk($size) as $rows) {
            $keys = array_values(array_filter($rows->pluck('token_id')->all(), is_string(...)));
            $existing = array_map(Tokens::key(...), $model->newQuery()->whereIn($model->getKeyName(), $keys)->pluck($model->getKeyName())->all());
            $gone = array_values(array_diff($keys, $existing));

            if ($gone !== []) {
                $pruned += DB::table(self::TABLE)->whereIn('token_id', $gone)->delete();
            }
        }

        return $pruned;
    }

    /**
     * @param  mixed  $value  the value of a column, as the connection returns it, which differs between the engines
     */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
