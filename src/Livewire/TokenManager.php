<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Livewire;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\View as ViewFactory;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Pushery\SanctumHardening\Abilities\AbilityCatalog;
use Pushery\SanctumHardening\Abilities\AbilityRefused;
use Pushery\SanctumHardening\Tokens\MintRefused;
use Pushery\SanctumHardening\Tokens\PersonalTokens;
use Pushery\SanctumHardening\Tokens\TokenNetworks;
use Pushery\SanctumHardening\Tokens\TokenPrefixes;
use Pushery\SanctumHardening\Tokens\TokenUse;
use Pushery\SanctumHardening\Tokens\TokenUses;
use Pushery\WireKit\WireKitServiceProvider;

/**
 * The self-service screen of a user's personal access tokens: mint one with the abilities of the
 * catalog, a lifetime from the list and, where it should be, the networks it may be used from, see
 * each with its prefix, abilities, networks, expiry, last use and latest requests, and revoke it.
 *
 * Every rule is the one PersonalTokens applies, so the screen offers nothing a mint would refuse: no
 * reserved ability, no lifetime off the list. The plain text of a token exists once, on the answer to
 * its mint, and is gone with the next action.
 *
 * Given a `tokenable`, `<livewire:sanctum-hardening.tokens :tokenable="$workspace" />`, the screen
 * manages that holder's tokens instead, where the application's Gate lets the signed-in user, and its
 * texts speak of the holder rather than of the account. Where the password was not confirmed a moment
 * ago, the screen asks for it itself, so it needs no `password.confirm` route on its host.
 */
final class TokenManager extends Component
{
    /**
     * The holder whose tokens the screen manages: null for the signed-in user. Locked, so no request
     * points the screen at another holder; PersonalTokens asks the Gate about it on every action as well.
     */
    #[Locked]
    public ?Model $tokenable = null;

    /** The password typed to confirm it on the screen, cleared on every attempt. */
    public string $password = '';

    public string $name = '';

    /** @var list<string> */
    public array $abilities = [];

    public ?int $days = null;

    /** The addresses and networks the new token may be used from, as typed: one a line, none for anywhere. */
    public string $networks = '';

    public ?string $plainTextToken = null;

    public string $status = '';

    // Set in boot() on every request, so nothing of them is serialized into the component's snapshot.
    protected PersonalTokens $tokens;

    protected Translator $translator;

    protected Request $request;

    public function boot(PersonalTokens $tokens, Translator $translator, Request $request): void
    {
        $this->tokens = $tokens;
        $this->translator = $translator;
        $this->request = $request;
    }

    public function mint(): void
    {
        $this->plainTextToken = null;
        $this->validate(['name' => ['required', 'string', 'max:255']]);

        try {
            $minted = $this->tokenable instanceof Model
                ? $this->tokens->mintFor($this->request, $this->tokenable, $this->name, $this->abilities, $this->days, $this->typedNetworks())
                : $this->tokens->mint($this->request, $this->name, $this->abilities, $this->days, $this->typedNetworks());
            $this->plainTextToken = $minted->plainTextToken;
        } catch (AbilityRefused|MintRefused $refusal) {
            $this->status = $this->line('refused.'.$refusal->reason);

            return;
        }

        $this->name = '';
        $this->abilities = [];
        $this->networks = '';
        $this->status = $this->line('minted');
    }

    public function revoke(string $tokenId): void
    {
        $this->plainTextToken = null;
        $this->status = $this->line($this->tokens->revoke($this->request, $tokenId, $this->tokenable) ? 'revoked' : 'not_revoked');
    }

    /** Confirm the signed-in user's password on the screen, under the throttle of PersonalTokens. */
    public function confirmPassword(): void
    {
        $password = $this->password;
        $this->password = '';
        $this->plainTextToken = null;

        try {
            $this->status = $this->line($this->tokens->confirmPassword($this->request, $password) ? 'confirmed' : 'not_confirmed');
        } catch (MintRefused) {
            $this->status = $this->line('confirm_too_fast');
        }
    }

    public function render(): View
    {
        $container = Container::getInstance();
        $catalog = $container->make(AbilityCatalog::class);
        $config = $container->make(Repository::class);
        $owned = $this->tokens->listFor($this->request, $this->tokenable);
        $endpoint = $config->get('sanctum-hardening.ui.endpoint');

        $variant = $config->get('sanctum-hardening.ui.variant');
        $wireKit = $variant === 'wirekit' || ($variant !== 'plain' && class_exists(WireKitServiceProvider::class));

        return ViewFactory::make($wireKit ? 'sanctum-hardening::wirekit.livewire.token-manager' : 'sanctum-hardening::livewire.token-manager', [
            // The views translate through the contract rather than __() and trans_choice(), which
            // laravel/framework defines and this package does not require.
            'lang' => $this->translator,
            'holder' => $this->holdsAnother(),
            'asksForPassword' => ! $this->tokens->passwordConfirmed($this->request),
            'tokens' => $owned,
            'prefixes' => $container->make(TokenPrefixes::class)->of($owned),
            'limits' => $container->make(TokenNetworks::class)->ofEach($owned),
            'uses' => $this->latestUses($container->make(TokenUses::class)->latestOf($owned)),
            'grantable' => array_combine($catalog->grantable(), array_map($catalog->label(...), $catalog->grantable())),
            'lifetimes' => $this->tokens->lifetimes(),
            'defaultLifetime' => $this->tokens->defaultLifetime(),
            'allowsNoExpiry' => $config->get('sanctum-hardening.minting.allow_no_expiry') === true,
            // The quickstart: the address and the limits as the application has them, never written out in a view.
            // `ui.endpoint` names an API that answers on a host of its own; without it, the prefix on this host.
            'endpoint' => is_string($endpoint) && trim($endpoint) !== ''
                ? $endpoint
                : $container->make(UrlGenerator::class)->to(is_string($prefix = $config->get('sanctum-hardening.routes.prefix')) ? $prefix : 'api'),
            'perToken' => $config->get('sanctum-hardening.throttle.per_token'),
            'perUser' => $config->get('sanctum-hardening.throttle.per_user'),
        ]);
    }

    /**
     * The latest requests of each token as the views show them: the method, the route and the status,
     * and the moment in the format of the screen's language.
     *
     * @param  array<string, list<TokenUse>>  $uses
     * @return array<string, list<array{text: string, meta: string}>>
     */
    private function latestUses(array $uses): array
    {
        $format = $this->translator->get('sanctum-hardening::messages.screen.formats.moment');
        $format = is_string($format) ? $format : 'LLL';
        $shown = [];

        foreach ($uses as $key => $list) {
            $shown[$key] = array_map(fn (TokenUse $use): array => [
                'text' => "{$use->method} {$use->route} · {$use->status}",
                'meta' => $this->momentOf($use, $format),
            ], $list);
        }

        return $shown;
    }

    /** The moment of a use in `$format`, in the language of the screen. */
    private function momentOf(TokenUse $use, string $format): string
    {
        $moment = Date::instance($use->usedAt);
        // On its own line: given a locale, `locale()` sets it and answers the instance, and given none it
        // answers the locale, so a chained call would read as either.
        $moment->locale($this->translator->getLocale());

        return $moment->isoFormat($format);
    }

    /**
     * The entries of the networks field: one a line, and a comma or a space parts them as well, since no
     * address and no network holds either.
     *
     * @return list<string>
     */
    private function typedNetworks(): array
    {
        return array_values(array_filter(preg_split('/[\s,]+/', $this->networks) ?: [], static fn (string $entry): bool => $entry !== ''));
    }

    /**
     * Whether the screen manages the tokens of a holder other than the signed-in user, the case its texts
     * speak of the holder in rather than of the account.
     */
    private function holdsAnother(): bool
    {
        if (! $this->tokenable instanceof Model) {
            return false;
        }

        $user = $this->request->user();

        return ! ($user instanceof Model && $user->getMorphClass() === $this->tokenable->getMorphClass() && $user->is($this->tokenable));
    }

    /** The line of `$key` under `screen`, in the holder's wording where the screen manages another holder and has one. */
    private function line(string $key): string
    {
        foreach ($this->holdsAnother() ? ['holder.'.$key, $key] : [$key] as $candidate) {
            $name = 'sanctum-hardening::messages.screen.'.$candidate;
            $line = $this->translator->get($name);

            if (is_string($line) && $line !== $name) {
                return $line;
            }
        }

        return $key;
    }
}
