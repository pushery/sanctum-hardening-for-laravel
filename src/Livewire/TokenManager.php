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
use Laravel\Sanctum\NewAccessToken;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Pushery\SanctumHardening\Abilities\AbilityCatalog;
use Pushery\SanctumHardening\Abilities\AbilityRefused;
use Pushery\SanctumHardening\Throttling\QuickstartLimits;
use Pushery\SanctumHardening\Throttling\ResolvesQuickstartLimits;
use Pushery\SanctumHardening\Throttling\ThrottleQuickstartLimits;
use Pushery\SanctumHardening\Tokens\KeyIds;
use Pushery\SanctumHardening\Tokens\MintRefused;
use Pushery\SanctumHardening\Tokens\PersonalTokens;
use Pushery\SanctumHardening\Tokens\TokenNetworks;
use Pushery\SanctumHardening\Tokens\Tokens;
use Pushery\SanctumHardening\Tokens\TokenUse;
use Pushery\SanctumHardening\Tokens\TokenUses;

/**
 * The self-service screen of a user's personal access tokens: mint one with the abilities of the
 * catalog, a lifetime from the list and, where it should be, the networks it may be used from, see
 * each with its prefix, abilities, networks, expiry, last use and latest requests, and revoke it.
 *
 * Every rule is the one PersonalTokens applies, so the screen offers nothing a mint would refuse: no
 * reserved ability, no lifetime off the list. The plain text of a token exists once, on the answer to
 * its mint, and never in the snapshot Livewire writes into the page and the browser sends back with
 * its next request. The password typed to confirm it is cleared before every answer for the same
 * reason.
 *
 * Given a `tokenable`, `<livewire:sanctum-hardening.tokens :tokenable="$workspace" />`, the screen
 * manages that holder's tokens instead, where the application's Gate lets the signed-in user, and its
 * texts speak of the holder rather than of the account. Where the password was not confirmed a moment
 * ago, the screen asks for it itself, so it needs no `password.confirm` route on its host. Given
 * `:heading="false"`, it leaves out its heading and the sentence under it, for a page that heads it with
 * a page header of its own.
 */
final class TokenManager extends Component
{
    /**
     * The holder whose tokens the screen manages: null for the signed-in user. Locked, so no request
     * points the screen at another holder; PersonalTokens asks the Gate about it on every action as well.
     */
    #[Locked]
    public ?Model $tokenable = null;

    /**
     * Whether the screen shows its heading and the sentence under it. A page that heads the screen itself, with a page
     * header of its own, passes `:heading="false"`; the parts of the screen keep their heads, one level higher. Locked,
     * as it is the page's choice.
     */
    #[Locked]
    public bool $heading = true;

    /** The password typed to confirm it on the screen, cleared before every answer by dehydrate(). */
    public string $password = '';

    public string $name = '';

    /** @var list<string> */
    public array $abilities = [];

    /**
     * The lifetime chosen in the form, in days, null for the default. A select bound without `.number` sends the text of
     * its option, the empty text of the default among them, and a host's copy of the plain view may lack the modifier, so
     * the property takes the text as well, and mint() reads it.
     */
    public int|string|null $days = null;

    /**
     * The overlap chosen for the next rotation, in hours, null for the default one. Like the lifetime, the property takes
     * the text of an option as well, and rotate() reads it.
     */
    public int|string|null $overlap = null;

    /** The addresses and networks the new token may be used from, as typed: one a line, none for anywhere. */
    public string $networks = '';

    public string $status = '';

    /**
     * The plain text of the token this request minted, for the answer to its mint alone. Protected, so Livewire
     * leaves it out of the snapshot, and the next request starts without it.
     */
    protected ?string $plainTextToken = null;

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

    /**
     * Clear the typed password before the component is written into the snapshot of its answer, whatever action
     * ran. A password typed while another action was sent, a mint or a revocation, would otherwise travel back to
     * the page and to the server with every later request.
     */
    public function dehydrate(): void
    {
        $this->password = '';
    }

    /**
     * Mint a token from the form. A refusal is said on the status line, which a screen reader announces, the name's
     * own among them: PersonalTokens refuses an empty name and one longer than it allows, and the field of a refused
     * name is marked as well. A refused network names the entry that is no address and no network, so the person
     * finds the one line among the ones they typed, and marks the field of the networks. So does a list longer than
     * a token may have, and its line names how many it may.
     */
    public function mint(): void
    {
        $this->resetErrorBag(['name', 'networks']);
        $days = $this->chosenDays();

        if ($days === false) {
            $this->status = $this->line('refused.lifetime');

            return;
        }

        try {
            $minted = $this->tokenable instanceof Model
                ? $this->tokens->mintFor($this->request, $this->tokenable, $this->name, $this->abilities, $days, $this->typedNetworks())
                : $this->tokens->mint($this->request, $this->name, $this->abilities, $days, $this->typedNetworks());
            $this->plainTextToken = $minted->plainTextToken;
        } catch (AbilityRefused|MintRefused $refusal) {
            $this->status = $this->line('refused.'.$refusal->reason, $refusal instanceof MintRefused ? $this->placeholdersOf($refusal) : []);
            $field = match ($refusal->reason) {
                'name' => 'name',
                'network', 'too_many_networks' => 'networks',
                default => null,
            };

            if ($field !== null) {
                $this->addError($field, $this->status);
            }

            return;
        }

        $this->name = '';
        $this->abilities = [];
        $this->networks = '';
        $this->status = $this->line('minted');
    }

    /**
     * Revoke a token of the list. Its row leaves the list, and with it the button that had the focus, so the focus
     * moves on once the screen is drawn anew: to the revoke button of the token below, to the one of the token above
     * where the last token went, and to the screen's heading once no token is left, or to the screen itself where it
     * shows no heading.
     */
    public function revoke(string $tokenId): void
    {
        $listed = $this->tokens->listFor($this->request, $this->tokenable)->map(static fn (Model $token): string => Tokens::key($token->getKey()))->values()->all();
        $revoked = $this->tokens->revoke($this->request, $tokenId, $this->tokenable);
        $this->status = $this->line($revoked ? 'revoked' : 'not_revoked');

        if ($revoked) {
            $this->js('$el.querySelector('.json_encode($this->focusAfterRevoking($listed, $tokenId), JSON_THROW_ON_ERROR).')?.focus()');
        }
    }

    /**
     * Replace one token of the list by its successor, which is shown once as a new token is, and let the old one work
     * until the overlap has passed: the one chosen on the screen, where it offers a choice, otherwise the default one.
     * The next rotation starts from the default again.
     */
    public function rotate(string $tokenId): void
    {
        $overlap = $this->chosenOverlap();

        if ($overlap === false) {
            $this->status = $this->line('refused.overlap');

            return;
        }

        try {
            $successor = $this->tokens->rotate($this->request, $tokenId, $this->tokenable, $overlap);
        } catch (AbilityRefused|MintRefused $refusal) {
            $this->status = $this->line('refused.'.$refusal->reason, $refusal instanceof MintRefused ? $this->placeholdersOf($refusal) : []);

            return;
        }

        if (! $successor instanceof NewAccessToken) {
            $this->status = $this->line('not_rotated');

            return;
        }

        $this->plainTextToken = $successor->plainTextToken;
        $this->overlap = null;
        $this->status = $this->line('rotated');
    }

    /** Confirm the signed-in user's password on the screen, under the throttle of PersonalTokens. */
    public function confirmPassword(): void
    {
        try {
            $this->status = $this->line($this->tokens->confirmPassword($this->request, $this->password) ? 'confirmed' : 'not_confirmed');
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
        $replacedUntil = $this->tokens->replacedUntil($owned);
        $defaultOverlap = $this->tokens->defaultOverlap();
        $holder = $this->tokenable ?? $this->request->user();
        $grantable = $catalog->grantable($holder instanceof Model ? $holder : null);
        $endpoint = $config->get('sanctum-hardening.ui.endpoint');
        // The limits the application names for the holder; a screen with no holder names the package limiter's.
        $quickstart = $holder instanceof Model
            ? $container->make(ResolvesQuickstartLimits::class)->limitsFor($holder)
            : $container->make(ThrottleQuickstartLimits::class)->limits();

        $variant = $config->get('sanctum-hardening.ui.variant');
        // `auto` takes the WireKit view where the installed WireKit renders it, `wirekit` whatever the version.
        $wireKit = $variant === 'wirekit' || ($variant !== 'plain' && WireKitView::installed());

        return ViewFactory::make($wireKit ? 'sanctum-hardening::wirekit.livewire.token-manager' : 'sanctum-hardening::livewire.token-manager', [
            // The views translate through the contract rather than __() and trans_choice(), which
            // laravel/framework defines and this package does not require.
            'lang' => $this->translator,
            'domId' => $this->domId(...),
            'plainTextToken' => $this->plainTextToken,
            'holder' => $this->holdsAnother(),
            'asksForPassword' => ! $this->tokens->passwordConfirmed($this->request),
            'tokens' => $owned,
            'prefixes' => $container->make(KeyIds::class)->of($owned),
            'replacedUntil' => $replacedUntil,
            'rotatable' => $owned->reject(static fn (Model $token): bool => Tokens::expired($token) || isset($replacedUntil[Tokens::key($token->getKey())]))
                ->map(static fn (Model $token): string => Tokens::key($token->getKey()))->values()->all(),
            // The default overlap is the first option and the one without a value, as the default lifetime is in the form.
            'defaultOverlap' => $defaultOverlap,
            'overlaps' => array_values(array_diff($this->tokens->overlaps(), [$defaultOverlap])),
            // What a rotation's question names: the overlap chosen, where it is one the screen offers.
            'overlapHours' => is_int($chosenOverlap = $this->chosenOverlap()) ? $chosenOverlap : $defaultOverlap,
            'duration' => $this->duration(...),
            'limits' => $container->make(TokenNetworks::class)->ofEach($owned),
            'uses' => $this->latestUses($container->make(TokenUses::class)->latestOf($owned)),
            // Where Sanctum writes no last use, `last_used_at` says nothing about a token, so the views say so.
            'lastUseRecorded' => Tokens::lastUseIsRecorded(),
            'grantable' => array_combine($grantable, array_map($catalog->label(...), $grantable)),
            'lifetimes' => $this->tokens->lifetimes(),
            'defaultLifetime' => $this->tokens->defaultLifetime(),
            'allowsNoExpiry' => $config->get('sanctum-hardening.minting.allow_no_expiry') === true,
            // The quickstart: the address and the limits as the application has them, never written out in a view.
            // `ui.endpoint` names an API that answers on a host of its own; without it, the prefix on this host.
            'endpoint' => is_string($endpoint) && trim($endpoint) !== ''
                ? $endpoint
                : $container->make(UrlGenerator::class)->to(is_string($prefix = $config->get('sanctum-hardening.routes.prefix')) ? $prefix : 'api'),
            'quickstartLimits' => $this->quickstartLimits($quickstart),
            // For a view a host published before the sentence above was written here.
            'perToken' => $quickstart->perToken,
            'perUser' => $quickstart->perHolder,
        ]);
    }

    /**
     * The selector of the element the focus moves to once `$revoked` left the list `$listed` showed, newest first: the
     * revoke button of the token below it, of the token above where it was the last, or the screen's heading, the
     * screen itself where it shows none.
     *
     * @param  array<int, string>  $listed
     */
    private function focusAfterRevoking(array $listed, string $revoked): string
    {
        $at = array_search($revoked, $listed, true);
        $beside = is_int($at) ? ($listed[$at + 1] ?? $listed[$at - 1] ?? null) : null;

        // A key is a number or an identifier of letters, digits and dashes; any other character would end the selector.
        // `$` is enough here: the key is one of the rows this screen has just listed, never a value of the request, and
        // a key the database or Laravel generates ends in no line feed.
        return is_string($beside) && preg_match('/^[A-Za-z0-9_-]+$/', $beside) === 1
            ? '[data-sanctum-hardening-revoke="'.$beside.'"]'
            : '#'.$this->domId($this->heading ? 'tokens-heading' : 'tokens');
    }

    /**
     * The id of the element `$name` of this screen. It carries the id of this instance of the component, so two screens
     * on one page, the user's and a workspace's, never share an id, and no label names a field of the other one. The
     * component's id stays the same over its requests, and so do these.
     */
    private function domId(string $name): string
    {
        // Livewire declares no type for the id; it is the random string it gave the component on mounting it.
        $component = $this->getId();

        return 'sanctum-hardening-'.$name.'-'.(is_string($component) ? $component : '');
    }

    /**
     * The latest requests of each token as the views show them: the method, the route and the status,
     * and the moment in the format of the screen's language.
     *
     * @param  array<array-key, list<TokenUse>>  $uses
     * @return array<array-key, list<array{text: string, meta: string}>>
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
     * The overlap of the next rotation as rotate() takes it: null for the default, where nothing or the empty text of the
     * default's option was chosen, and the hours of the chosen option otherwise, given as a number or as its text. An
     * overlap the screen does not offer is refused, and the answer is false, since the property is written by the
     * browser: no request gives a replaced token an overlap the application did not list.
     */
    private function chosenOverlap(): int|false|null
    {
        if ($this->overlap === null || $this->overlap === '') {
            return null;
        }

        $hours = is_int($this->overlap) ? $this->overlap : filter_var($this->overlap, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return is_int($hours) && in_array($hours, $this->tokens->overlaps(), true) ? $hours : false;
    }

    /** An overlap as the screen names it: in days where it is whole days, otherwise in hours. */
    private function duration(int $hours): string
    {
        return $hours % 24 === 0
            ? $this->translator->choice('sanctum-hardening::messages.screen.days', intdiv($hours, 24))
            : $this->translator->choice('sanctum-hardening::messages.screen.hours', $hours);
    }

    /**
     * The lifetime of the form as a mint takes it: null for the default, where nothing or the empty text of the default's
     * option was chosen, and the number of days otherwise, given as a number or as its text. A text that is no whole
     * number names no lifetime the screen offers, and the answer is false. Cast, it would be 0, a token without an
     * expiry wherever one is allowed.
     */
    private function chosenDays(): int|false|null
    {
        if ($this->days === null || $this->days === '') {
            return null;
        }

        if (is_int($this->days)) {
            return $this->days;
        }

        return filter_var($this->days, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? false;
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
     * What the line of a refused mint puts in for its placeholders: the entry of the networks it refused, and the
     * limit it met.
     *
     * @return array<string, string>
     */
    private function placeholdersOf(MintRefused $refusal): array
    {
        $placeholders = [];

        if ($refusal->network !== null) {
            $placeholders['network'] = $refusal->network;
        }

        if ($refusal->limit !== null) {
            $placeholders['limit'] = (string) $refusal->limit;
        }

        return $placeholders;
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

    /**
     * The quickstart's sentence on the limits a token meets, in the holder's wording where the screen manages another
     * holder: both limits, the one of them the application names, or null where it names neither.
     */
    private function quickstartLimits(QuickstartLimits $limits): ?string
    {
        return match (true) {
            $limits->perToken !== null && $limits->perHolder !== null => $this->line('quickstart_limits', ['token' => (string) $limits->perToken, 'user' => (string) $limits->perHolder]),
            $limits->perToken !== null => $this->line('quickstart_token_limit', ['token' => (string) $limits->perToken]),
            $limits->perHolder !== null => $this->line('quickstart_user_limit', ['user' => (string) $limits->perHolder]),
            default => null,
        };
    }

    /**
     * The line of `$key` under `screen`, in the holder's wording where the screen manages another holder and has one,
     * with `$replace` put in for its placeholders.
     *
     * @param  array<string, string>  $replace
     */
    private function line(string $key, array $replace = []): string
    {
        foreach ($this->holdsAnother() ? ['holder.'.$key, $key] : [$key] as $candidate) {
            $name = 'sanctum-hardening::messages.screen.'.$candidate;
            $line = $this->translator->get($name, $replace);

            if (is_string($line) && $line !== $name) {
                return $line;
            }
        }

        return $key;
    }
}
