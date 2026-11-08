<?php

declare(strict_types=1);

namespace Pushery\SanctumHardening\Livewire;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use Livewire\Component;
use Pushery\SanctumHardening\Abilities\AbilityCatalog;
use Pushery\SanctumHardening\Abilities\AbilityRefused;
use Pushery\SanctumHardening\Tokens\MintRefused;
use Pushery\SanctumHardening\Tokens\PersonalTokens;
use Pushery\SanctumHardening\Tokens\TokenPrefixes;
use Pushery\WireKit\WireKitServiceProvider;

/**
 * The self-service screen of a user's personal access tokens: mint one with the abilities of the
 * catalog and a lifetime from the list, see each with its prefix, abilities, expiry and last use, and
 * revoke it.
 *
 * Every rule is the one PersonalTokens applies, so the screen offers nothing a mint would refuse: no
 * reserved ability, no lifetime off the list. The plain text of a token exists once, on the answer to
 * its mint, and is gone with the next action.
 */
final class TokenManager extends Component
{
    public string $name = '';

    /** @var list<string> */
    public array $abilities = [];

    public ?int $days = null;

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
            $this->plainTextToken = $this->tokens->mint($this->request, $this->name, $this->abilities, $this->days)->plainTextToken;
        } catch (AbilityRefused|MintRefused $refusal) {
            $this->status = $this->line('refused.'.$refusal->reason);

            return;
        }

        $this->name = '';
        $this->abilities = [];
        $this->status = $this->line('minted');
    }

    public function revoke(string $tokenId): void
    {
        $this->plainTextToken = null;
        $this->status = $this->line($this->tokens->revoke($this->request, $tokenId) ? 'revoked' : 'not_revoked');
    }

    public function render(): View
    {
        $container = Container::getInstance();
        $catalog = $container->make(AbilityCatalog::class);
        $config = $container->make(Repository::class);
        $owned = $this->tokens->listFor($this->request);

        $variant = $config->get('sanctum-hardening.ui.variant');
        $wireKit = $variant === 'wirekit' || ($variant !== 'plain' && class_exists(WireKitServiceProvider::class));

        return ViewFactory::make($wireKit ? 'sanctum-hardening::wirekit.livewire.token-manager' : 'sanctum-hardening::livewire.token-manager', [
            // The views translate through the contract rather than __() and trans_choice(), which
            // laravel/framework defines and this package does not require.
            'lang' => $this->translator,
            'tokens' => $owned,
            'prefixes' => $container->make(TokenPrefixes::class)->of($owned),
            'grantable' => array_combine($catalog->grantable(), array_map($catalog->label(...), $catalog->grantable())),
            'lifetimes' => $this->tokens->lifetimes(),
            'defaultLifetime' => $this->tokens->defaultLifetime(),
            'allowsNoExpiry' => $config->get('sanctum-hardening.minting.allow_no_expiry') === true,
            // The quickstart: the address and the limits as the application has them, never written out in a view.
            'endpoint' => $container->make(UrlGenerator::class)->to(is_string($prefix = $config->get('sanctum-hardening.routes.prefix')) ? $prefix : 'api'),
            'perToken' => $config->get('sanctum-hardening.throttle.per_token'),
            'perUser' => $config->get('sanctum-hardening.throttle.per_user'),
        ]);
    }

    private function line(string $key): string
    {
        $line = $this->translator->get('sanctum-hardening::messages.screen.'.$key);

        return is_string($line) ? $line : $key;
    }
}
