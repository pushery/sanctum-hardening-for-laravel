{{-- The self-service screen of a user's API tokens, in plain Blade. Every rule is the one PersonalTokens applies;
     the screen offers only the abilities and lifetimes a mint accepts, and never a reserved ability. --}}
<section aria-labelledby="sanctum-hardening-tokens-heading" class="sanctum-hardening-tokens" data-sanctum-hardening-view="plain">
    <h2 id="sanctum-hardening-tokens-heading">{{ $lang->get('sanctum-hardening::messages.screen.heading') }}</h2>
    <p>{{ $holder ? $lang->get('sanctum-hardening::messages.screen.holder.intro') : $lang->get('sanctum-hardening::messages.screen.intro') }}</p>

    <p role="status" aria-live="polite" id="sanctum-hardening-tokens-status">{{ $status }}</p>

    @if ($plainTextToken !== null)
        <div id="sanctum-hardening-new-token">
            <p>{{ $lang->get('sanctum-hardening::messages.screen.copy_now') }}</p>
            <code>{{ $plainTextToken }}</code>
        </div>
    @endif

    @if ($asksForPassword)
        <form wire:submit="confirmPassword" novalidate>
            <p>{{ $lang->get('sanctum-hardening::messages.screen.confirm_intro') }}</p>
            <div>
                <label for="sanctum-hardening-password">{{ $lang->get('sanctum-hardening::messages.screen.password') }}</label>
                <input id="sanctum-hardening-password" type="password" wire:model="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit">{{ $lang->get('sanctum-hardening::messages.screen.confirm') }}</button>
        </form>
    @endif

    <form wire:submit="mint" novalidate>
        <div>
            <label for="sanctum-hardening-token-name">{{ $lang->get('sanctum-hardening::messages.screen.name') }}</label>
            <input id="sanctum-hardening-token-name" type="text" wire:model="name" maxlength="255" required
                @error('name') aria-invalid="true" aria-describedby="sanctum-hardening-token-name-error" @enderror>
            @error('name')
                <p id="sanctum-hardening-token-name-error">{{ $message }}</p>
            @enderror
        </div>

        <fieldset>
            <legend>{{ $lang->get('sanctum-hardening::messages.screen.abilities') }}</legend>
            @foreach ($grantable as $ability => $label)
                <div wire:key="ability-{{ $ability }}">
                    <input id="sanctum-hardening-ability-{{ $loop->index }}" type="checkbox" wire:model="abilities" value="{{ $ability }}">
                    <label for="sanctum-hardening-ability-{{ $loop->index }}">{{ $label }}</label>
                </div>
            @endforeach
        </fieldset>

        <div>
            <label for="sanctum-hardening-token-days">{{ $lang->get('sanctum-hardening::messages.screen.lifetime') }}</label>
            <select id="sanctum-hardening-token-days" wire:model="days">
                <option value="">{{ $lang->choice('sanctum-hardening::messages.screen.days', $defaultLifetime) }}</option>
                @foreach ($lifetimes as $lifetime)
                    <option value="{{ $lifetime }}">{{ $lang->choice('sanctum-hardening::messages.screen.days', $lifetime) }}</option>
                @endforeach
                @if ($allowsNoExpiry)
                    <option value="0">{{ $lang->get('sanctum-hardening::messages.screen.no_expiry') }}</option>
                @endif
            </select>
        </div>

        <div>
            <label for="sanctum-hardening-token-networks">{{ $lang->get('sanctum-hardening::messages.screen.networks') }}</label>
            <textarea id="sanctum-hardening-token-networks" wire:model="networks" rows="3" aria-describedby="sanctum-hardening-token-networks-hint"></textarea>
            <p id="sanctum-hardening-token-networks-hint">{{ $lang->get('sanctum-hardening::messages.screen.networks_hint') }}</p>
        </div>

        <button type="submit">{{ $lang->get('sanctum-hardening::messages.screen.mint') }}</button>
    </form>

    <table>
        <thead>
            <tr>
                <th scope="col">{{ $lang->get('sanctum-hardening::messages.screen.name') }}</th>
                <th scope="col">{{ $lang->get('sanctum-hardening::messages.screen.prefix') }}</th>
                <th scope="col">{{ $lang->get('sanctum-hardening::messages.screen.abilities') }}</th>
                <th scope="col">{{ $lang->get('sanctum-hardening::messages.screen.expires') }}</th>
                <th scope="col">{{ $lang->get('sanctum-hardening::messages.screen.last_used') }}</th>
                <th scope="col"><span class="sr-only">{{ $lang->get('sanctum-hardening::messages.screen.revoke') }}</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tokens as $token)
                <tr wire:key="token-{{ $token->getKey() }}">
                    <td>{{ $token->getAttribute('name') }}</td>
                    <td><code>{{ $prefixes[$token->getKey()] ?? '' }}</code></td>
                    <td>
                        {{ implode(', ', array_map(static fn (mixed $ability): string => is_string($ability) ? ($grantable[$ability] ?? $ability) : '', (array) $token->getAttribute('abilities'))) }}
                        @if (($limits[(string) $token->getKey()] ?? []) !== [])
                            <div><small>{{ $lang->get('sanctum-hardening::messages.screen.only_from', ['networks' => implode(', ', $limits[(string) $token->getKey()])]) }}</small></div>
                        @endif
                    </td>
                    <td>{{ $token->getAttribute('expires_at')?->locale($lang->getLocale())->isoFormat($lang->get('sanctum-hardening::messages.screen.formats.date')) ?? $lang->get('sanctum-hardening::messages.screen.never') }}</td>
                    <td>
                        {{ $token->getAttribute('last_used_at')?->locale($lang->getLocale())->isoFormat($lang->get('sanctum-hardening::messages.screen.formats.moment')) ?? $lang->get('sanctum-hardening::messages.screen.never') }}
                        @if (($uses[(string) $token->getKey()] ?? []) !== [])
                            <ul aria-label="{{ $lang->get('sanctum-hardening::messages.screen.recent') }}">
                                @foreach ($uses[(string) $token->getKey()] as $use)
                                    <li>{{ $use['text'] }} <small>{{ $use['meta'] }}</small></li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td>
                        <button type="button" wire:click="revoke('{{ $token->getKey() }}')" wire:confirm="{{ $lang->get('sanctum-hardening::messages.screen.confirm_revoke') }}" aria-label="{{ $lang->get('sanctum-hardening::messages.screen.revoke_named', ['name' => $token->getAttribute('name')]) }}">
                            {{ $lang->get('sanctum-hardening::messages.screen.revoke') }}
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">{{ $holder ? $lang->get('sanctum-hardening::messages.screen.holder.none') : $lang->get('sanctum-hardening::messages.screen.none') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <aside aria-labelledby="sanctum-hardening-quickstart-heading" id="sanctum-hardening-quickstart">
        <h3 id="sanctum-hardening-quickstart-heading">{{ $lang->get('sanctum-hardening::messages.screen.quickstart') }}</h3>
        <p>{{ $lang->get('sanctum-hardening::messages.screen.quickstart_endpoint') }} <code>{{ $endpoint }}</code></p>
        <p>{{ $holder ? $lang->get('sanctum-hardening::messages.screen.holder.quickstart_limits', ['token' => $perToken, 'user' => $perUser]) : $lang->get('sanctum-hardening::messages.screen.quickstart_limits', ['token' => $perToken, 'user' => $perUser]) }}</p>
        <pre><code>curl -H "Authorization: Bearer &lt;token&gt;" {{ $endpoint }}</code></pre>
    </aside>
</section>
