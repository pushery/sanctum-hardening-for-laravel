<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Closure;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * The format of the keys the package issues.
 *
 *     <product>_<environment>_[<tenant>_]<id>_<secret><checksum>
 *
 * The prefix of product and environment tells a person, and a secret scanner, what a key belongs to.
 * The identifier names the key on the screen and in logs, and the secret of 43 characters, about 256
 * random bits, appears nowhere once the key was shown. The checksum is CRC-32 over everything before
 * it, written in base 62, so a reader refuses a mistyped or made-up key before it asks the database.
 * The fingerprint of a key is the SHA-256 of the whole key: prefix, tenant and identifier are part of
 * the hashed value, so a secret never matches another identifier or another tenant.
 *
 * Only letters, digits and the underscore occur, so a double click selects the whole key, and it fits
 * a header, a `.env` file and a shell without quotes. Nothing is normalized: an upper-case letter in
 * the prefix or the identifier makes the value no key, since the fingerprint covers the exact bytes.
 */
final class KeyFormat
{
    /** The characters of the secret and the digits of the checksum, in ASCII order. */
    public const string ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /** The characters of the identifier. */
    public const string ID_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /** The longest key: a product of ten letters and a tenant of 32 characters. */
    public const int MAX_LENGTH = 111;

    public const int ID_LENGTH = 12;

    public const int SECRET_LENGTH = 43;

    public const int CHECKSUM_LENGTH = 6;

    /** The pattern a secret scanner finds a key by in a text. */
    public const string PATTERN = '\b[a-z]{2,10}_(?:live|test)_(?:[a-z0-9]{1,32}_)?[a-z0-9]{12}_[A-Za-z0-9]{49}\b';

    /** The environments a key names. */
    public const array ENVIRONMENTS = ['live', 'test'];

    /**
     * Issue a key. Each character of the identifier and the secret is drawn uniformly from its
     * alphabet: `$draw` returns an integer from 0 up to its argument, and is random_int(), which
     * rejects internally and so favors no character, unless a test needs a known key.
     *
     * @param  (Closure(int): int)|null  $draw
     *
     * @throws InvalidArgumentException where the product, the environment or the tenant breaks the format
     */
    public static function issue(string $product, string $environment, ?string $tenant = null, ?Closure $draw = null): string
    {
        if (! self::isProduct($product) || ! in_array($environment, self::ENVIRONMENTS, true) || ($tenant !== null && ! self::isTenant($tenant))) {
            throw new InvalidArgumentException('A key is issued for a product of 2 to 10 letters a-z, the environment live or test, and an optional tenant of 1 to 32 characters a-z and 0-9.');
        }

        $draw ??= static fn (int $max): int => random_int(0, $max);

        $body = $product.'_'.$environment.'_'.($tenant === null ? '' : $tenant.'_')
            .self::draw(self::ID_ALPHABET, self::ID_LENGTH, $draw).'_'
            .self::draw(self::ALPHABET, self::SECRET_LENGTH, $draw);

        return $body.self::checksum($body);
    }

    /**
     * The reason a value is no key, or null for a key. The rules are applied in a fixed order and the
     * first one the value breaks names the reason, so a value with several defects gets the same reason
     * from every reader of the format: `too_long`, `invalid_character`, `wrong_part_count`,
     * `invalid_product`, `unknown_environment`, `invalid_tenant`, `invalid_id`, `invalid_secret` and
     * `checksum_mismatch`.
     */
    public static function refusal(#[SensitiveParameter] string $value): ?string
    {
        if (strlen($value) > self::MAX_LENGTH) {
            return 'too_long';
        }

        if (preg_match('/\A[A-Za-z0-9_]*\z/', $value) !== 1) {
            return 'invalid_character';
        }

        $parts = explode('_', $value);

        if (count($parts) !== 4 && count($parts) !== 5) {
            return 'wrong_part_count';
        }

        $last = array_pop($parts);
        $tenant = count($parts) === 4 ? $parts[2] : null;

        return match (true) {
            ! self::isProduct($parts[0]) => 'invalid_product',
            ! in_array($parts[1], self::ENVIRONMENTS, true) => 'unknown_environment',
            $tenant !== null && ! self::isTenant($tenant) => 'invalid_tenant',
            preg_match('/\A[a-z0-9]{12}\z/', $parts[count($parts) - 1]) !== 1 => 'invalid_id',
            strlen($last) !== self::SECRET_LENGTH + self::CHECKSUM_LENGTH => 'invalid_secret',
            ! hash_equals(self::checksum(substr($value, 0, -self::CHECKSUM_LENGTH)), substr($value, -self::CHECKSUM_LENGTH)) => 'checksum_mismatch',
            default => null,
        };
    }

    /** The public parts of a key, or null where the value is no key. */
    public static function parse(#[SensitiveParameter] string $value): ?ParsedKey
    {
        if (self::refusal($value) !== null) {
            return null;
        }

        $parts = explode('_', $value);

        return new ParsedKey(
            product: $parts[0],
            environment: $parts[1],
            tenant: count($parts) === 5 ? $parts[2] : null,
            id: $parts[count($parts) - 2],
        );
    }

    /**
     * CRC-32 as IEEE 802.3 specifies it, the CRC of zlib, gzip and PNG, as an unsigned value on every
     * build. `hash('crc32', ...)` is another variant and does not answer the same.
     */
    public static function crc32(string $bytes): int
    {
        return crc32($bytes) & 0xFFFFFFFF;
    }

    /** The checksum of everything before it: its CRC-32 in base 62, most significant digit first, left-padded with `0` to six digits. */
    public static function checksum(#[SensitiveParameter] string $body): string
    {
        $value = self::crc32($body);
        $digits = '';

        do {
            $digits = self::ALPHABET[$value % 62].$digits;
            $value = intdiv($value, 62);
        } while ($value > 0);

        return str_pad($digits, self::CHECKSUM_LENGTH, '0', STR_PAD_LEFT);
    }

    /** `sha256:` and the lower-case hexadecimal SHA-256 of the whole key. Sanctum's `token` column holds the part after the colon. */
    public static function fingerprint(#[SensitiveParameter] string $key): string
    {
        return 'sha256:'.hash('sha256', $key);
    }

    /** Whether a value is a product a key can name: 2 to 10 letters a-z. */
    public static function isProduct(string $value): bool
    {
        return preg_match('/\A[a-z]{2,10}\z/', $value) === 1;
    }

    /** Whether a value is a tenant a key can name: 1 to 32 characters a-z and 0-9. */
    public static function isTenant(string $value): bool
    {
        return preg_match('/\A[a-z0-9]{1,32}\z/', $value) === 1;
    }

    /**
     * @param  Closure(int): int  $draw
     */
    private static function draw(string $alphabet, int $length, Closure $draw): string
    {
        $max = strlen($alphabet) - 1;
        $characters = '';

        for ($index = 0; $index < $length; $index++) {
            $characters .= $alphabet[$draw($max)];
        }

        return $characters;
    }
}
