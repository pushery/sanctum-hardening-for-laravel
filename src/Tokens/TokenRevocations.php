<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
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
     * application's own code.
     */
    public function revoke(Model $token, ?Model $by): void
    {
        $now = Date::now();

        DB::transaction(static function () use ($token, $by, $now): void {
            DB::table(self::TABLE)->insert([
                'token_id' => TokenPrefixes::key($token->getKey()),
                'token_name' => self::text($token->getAttribute('name')),
                'tokenable_type' => self::text($token->getAttribute('tokenable_type')),
                'tokenable_id' => TokenPrefixes::key($token->getAttribute('tokenable_id')),
                'revoked_by_type' => $by?->getMorphClass(),
                'revoked_by_id' => $by instanceof Model ? TokenPrefixes::key($by->getKey()) : null,
                'revoked_at' => $now,
            ]);

            $token->setAttribute('expires_at', $now);
            $token->save();
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
     * Delete the tokens revoked more than `$days` days ago, with their records and their prefixes, and
     * say how many.
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
        foreach (DB::table(self::TABLE)->where('revoked_at', '<', $before)->select('token_id')->lazyById($size, 'token_id')->chunk($size) as $rows) {
            $keys = array_values(array_filter($rows->pluck('token_id')->all(), is_string(...)));

            $model->newQuery()->whereIn($model->getKeyName(), $keys)->delete();
            DB::table(TokenPrefixes::TABLE)->whereIn('token_id', $keys)->delete();
            DB::table(TokenNetworks::TABLE)->whereIn('token_id', $keys)->delete();
            $pruned += DB::table(self::TABLE)->whereIn('token_id', $keys)->delete();
        }

        return $pruned;
    }

    /** A value a row holds as the text it is stored as. */
    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
