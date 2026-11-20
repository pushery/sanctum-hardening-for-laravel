<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Revoking a token with a record of who ended it and when.
 *
 * A revoked token is not deleted. Its row stays, with its expiry moved to the moment it was revoked,
 * which is what makes Sanctum refuse it from then on, and a record here names the token, its holder,
 * the signed-in user who revoked it and the time. prune() deletes both together once the retention has
 * passed, so the record lasts as long as the configuration says and no longer.
 */
final readonly class TokenRevocations
{
    public const string TABLE = 'sanctum_hardening_token_revocations';

    /**
     * Revoke a token now, and record it: `$by` is the signed-in user who revoked it, or null for the
     * application's own code. Whether this call revoked the token: one revoked before keeps the
     * record of that revocation and the expiry it was given then, and revoking it again changes
     * nothing and answers false. A loop over every token of a user therefore goes on past one revoked
     * earlier, and of two requests that revoke the same token at once, the second answers false.
     *
     * The record keeps as much of the token's name as its column holds. Sanctum keeps the name as
     * text, so a token named longer outside the package, through `createToken()`, would fail the
     * insert, and the token would stay valid. The column is a string of the length Laravel gives
     * one, 255 characters unless the application set another with `Schema::defaultStringLength()`.
     */
    public function revoke(Model $token, ?Model $by): bool
    {
        $now = Date::now();

        return DB::connection()->transaction(static function () use ($token, $by, $now): bool {
            // The record is keyed by the token, so the insert of a second revocation finds the first record and
            // writes nothing, rather than failing on the key, and the token keeps the expiry it was given then.
            $recorded = DB::table(self::TABLE)->insertOrIgnore([
                'token_id' => TokenPrefixes::key($token->getKey()),
                'token_name' => mb_substr(self::text($token->getAttribute('name')), 0, Builder::$defaultStringLength),
                'tokenable_type' => self::text($token->getAttribute('tokenable_type')),
                'tokenable_id' => TokenPrefixes::key($token->getAttribute('tokenable_id')),
                'revoked_by_type' => $by?->getMorphClass(),
                'revoked_by_id' => $by instanceof Model ? TokenPrefixes::key($by->getKey()) : null,
                'revoked_at' => Moments::bound($now),
            ]) > 0;

            if ($recorded) {
                $token->setAttribute('expires_at', $now);
                $token->save();
            }

            return $recorded;
        });
    }

    /**
     * The keys of the given tokens that were revoked.
     *
     * @param  iterable<Model>  $tokens
     * @return list<string>
     */
    public function among(iterable $tokens): array
    {
        $keys = [];

        foreach ($tokens as $token) {
            $keys[] = TokenPrefixes::key($token->getKey());
        }

        return array_values(array_filter(DB::table(self::TABLE)->whereIn('token_id', $keys)->pluck('token_id')->all(), is_string(...)));
    }

    /**
     * The revocations of the tokens of `$tokenable`, the latest first.
     *
     * @return list<TokenRevocation>
     */
    public function of(Model $tokenable): array
    {
        $revocations = [];

        $rows = DB::table(self::TABLE)
            ->where('tokenable_type', $tokenable->getMorphClass())
            ->where('tokenable_id', TokenPrefixes::key($tokenable->getKey()))
            ->orderByDesc('revoked_at')
            ->get();

        foreach ($rows as $row) {
            $revocations[] = new TokenRevocation(
                self::text($row->token_id ?? null),
                self::text($row->token_name ?? null),
                self::text($row->tokenable_type ?? null),
                self::text($row->tokenable_id ?? null),
                is_string($row->revoked_by_type ?? null) ? $row->revoked_by_type : null,
                is_string($row->revoked_by_id ?? null) ? $row->revoked_by_id : null,
                new DateTimeImmutable(self::text($row->revoked_at ?? null)),
            );
        }

        return $revocations;
    }

    /**
     * Delete the tokens revoked more than `$days` days ago, with their records, their prefixes, their
     * networks and their alerts, and say how many.
     *
     * The tokens are deleted by key through the token model rather than joined, because a record's key
     * is a string and a token's key is a number on most schemas.
     */
    public function prune(int $days, int $chunk = 500): int
    {
        $model = Tokens::model();
        $size = max(1, $chunk);
        $before = Date::now()->subDays($days);
        $pruned = 0;

        // By key rather than by offset, so the rows deleted on the way make the walk skip none.
        foreach (DB::table(self::TABLE)->where('revoked_at', '<', Moments::bound($before))->select('token_id')->lazyById($size, 'token_id')->chunk($size) as $rows) {
            $keys = array_values(array_filter($rows->pluck('token_id')->all(), is_string(...)));

            $model->newQuery()->whereIn($model->getKeyName(), $keys)->delete();
            DB::table(TokenPrefixes::TABLE)->whereIn('token_id', $keys)->delete();
            DB::table(TokenNetworks::TABLE)->whereIn('token_id', $keys)->delete();
            DB::table(TokenAlerts::TABLE)->whereIn('token_id', $keys)->delete();
            DB::table(TokenRotations::TABLE)->whereIn('token_id', $keys)->delete();
            $pruned += DB::table(self::TABLE)->whereIn('token_id', $keys)->delete();
        }

        return $pruned;
    }

    /**
     * A value a row holds as the text it is stored as.
     *
     * @param  mixed  $value  the value of a column, as the connection returns it, which differs between the engines
     */
    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
