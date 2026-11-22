<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Tokens;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use LogicException;
use RuntimeException;

/**
 * Mints tokens whose plain text is a key in the package's key format, see KeyFormat.
 *
 * Sanctum's own `createToken()` hands out `<row id>|<text>` and stores the SHA-256 of the text alone.
 * Here the key is the whole text: Sanctum's `token` column holds the SHA-256 of the whole key, and the
 * identifier inside it is kept in KeyIds, which is how the key finds its token again.
 */
final readonly class Keys
{
    /** The keys a mint draws before it gives up finding an identifier no token has. */
    public const int ATTEMPTS = 5;

    /**
     * @param  (Closure(int): int)|null  $draw  the source of each character, as KeyFormat::issue() takes it; random_int() where null
     */
    public function __construct(private Repository $config, private KeyIds $ids, private ?Closure $draw = null) {}

    /**
     * Mint a token for a model that uses Sanctum's `HasApiTokens`, with a key in the key format.
     *
     * @param  array<array-key, mixed>  $abilities
     *
     * @throws MintRefused where the model holds no tokens
     * @throws InvalidArgumentException where `keys.product` or `keys.environment` is set to something a key cannot name
     */
    public function mint(Model $tokenable, string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $tokens = method_exists($tokenable, 'tokens') ? $tokenable->tokens() : null;

        if (! $tokens instanceof MorphMany) {
            throw MintRefused::notTokenable();
        }

        $product = $this->product();
        $environment = $this->environment();

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $key = KeyFormat::issue($product, $environment, null, $this->draw);
            $parsed = KeyFormat::parse($key) ?? throw new LogicException('A key the format issued broke the format.');

            // The identifier is unique among the tokens, and a taken one is drawn again. The check comes
            // before the insert: on PostgreSQL a failed insert ends the transaction around it, so the
            // primary key of the table is the backstop, not the test.
            if ($this->ids->taken($parsed->id)) {
                continue;
            }

            $token = DB::transaction(function () use ($tokens, $name, $abilities, $expiresAt, $key, $parsed): PersonalAccessToken {
                // @phpstan-ignore argument.type (the token model is Sanctum's setting, and Larastan reads no migration of Sanctum's to know its columns)
                $token = $tokens->create([
                    'name' => $name,
                    'token' => hash('sha256', $key),
                    'abilities' => $abilities,
                    'expires_at' => $expiresAt,
                ]);

                if (! $token instanceof PersonalAccessToken) {
                    throw new LogicException("Sanctum's token model is no Laravel\\Sanctum\\PersonalAccessToken, so no NewAccessToken can carry it.");
                }

                $this->ids->record($token, $parsed);

                return $token;
            });

            return new NewAccessToken($token, $key);
        }

        throw new RuntimeException('Every identifier drawn for a new key belonged to a token already.');
    }

    /**
     * The product the keys name: `keys.product`, or where that is unset the letters of `app.name` in
     * lower case, cut to ten, and `app` where those are fewer than two.
     *
     * @throws InvalidArgumentException where `keys.product` is set to something a key cannot name
     */
    public function product(): string
    {
        $configured = $this->config->get('sanctum-hardening.keys.product');

        if (is_string($configured) && $configured !== '') {
            return KeyFormat::isProduct($configured)
                ? $configured
                : throw new InvalidArgumentException("sanctum-hardening.keys.product is [{$configured}], and a key names a product of 2 to 10 letters a-z.");
        }

        $name = $this->config->get('app.name');
        $letters = substr((string) preg_replace('/[^a-z]/', '', strtolower(is_string($name) ? $name : '')), 0, 10);

        return KeyFormat::isProduct($letters) ? $letters : 'app';
    }

    /**
     * The environment the keys name: `keys.environment`, or where that is unset `live` where `app.env`
     * is `production` and `test` everywhere else.
     *
     * @throws InvalidArgumentException where `keys.environment` is set to something a key cannot name
     */
    public function environment(): string
    {
        $configured = $this->config->get('sanctum-hardening.keys.environment');

        if (is_string($configured) && $configured !== '') {
            return in_array($configured, KeyFormat::ENVIRONMENTS, true)
                ? $configured
                : throw new InvalidArgumentException("sanctum-hardening.keys.environment is [{$configured}], and a key names the environment live or test.");
        }

        return $this->config->get('app.env') === 'production' ? 'live' : 'test';
    }
}
