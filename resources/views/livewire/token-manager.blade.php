{{-- The self-service screen of a user's API tokens, in plain Blade. Every rule is the one PersonalTokens applies;
     the screen offers only the abilities and lifetimes a mint accepts, and never a reserved ability. Every id comes
     from $domId(), which adds the id of the component, so two screens on one page never share one. With
     `:heading="false"` the page heads the screen itself: the section then takes the screen's name from aria-label, and
     it is where the focus moves once the last token of the list is revoked, as the heading is otherwise. --}}
<section id="{{ $domId('tokens') }}" @if ($heading) aria-labelledby="{{ $domId('tokens-heading') }}" @else aria-label="{{ $lang->get('sanctum-hardening::messages.screen.heading') }}" tabindex="-1" @endif class="sanctum-hardening-tokens" data-sanctum-hardening-view="plain">
    @if ($heading)
        {{-- Focusable from a script alone: the focus moves here once the last token of the list is revoked. --}}
        <h2 id="{{ $domId('tokens-heading') }}" tabindex="-1">{{ $lang->get('sanctum-hardening::messages.screen.heading') }}</h2>
        <p>{{ $holder ? $lang->get('sanctum-hardening::messages.screen.holder.intro') : $lang->get('sanctum-hardening::messages.screen.intro') }}</p>
    @endif

    <p role="status" aria-live="polite" id="{{ $domId('tokens-status') }}">{{ $status }}</p>

    @if ($asksForPassword)
        <form wire:submit="confirmPassword" novalidate>
            <p>{{ $lang->get('sanctum-hardening::messages.screen.confirm_intro') }}</p>
            <div>
                <label for="{{ $domId('password') }}">{{ $lang->get('sanctum-hardening::messages.screen.password') }}</label>
                <input id="{{ $domId('password') }}" type="password" wire:model="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit">{{ $lang->get('sanctum-hardening::messages.screen.confirm') }}</button>
        </form>
    @endif

    <form wire:submit="mint" novalidate>
        <div>
            <label for="{{ $domId('token-name') }}">{{ $lang->get('sanctum-hardening::messages.screen.name') }}</label>
            <input id="{{ $domId('token-name') }}" type="text" wire:model="name" maxlength="255" required
                @error('name') aria-invalid="true" aria-describedby="{{ $domId('token-name-error') }}" @enderror>
            @error('name')
                <p id="{{ $domId('token-name-error') }}">{{ $message }}</p>
            @enderror
        </div>

        <fieldset>
            <legend>{{ $lang->get('sanctum-hardening::messages.screen.abilities') }}</legend>
            @foreach ($grantable as $ability => $label)
                <div wire:key="ability-{{ $ability }}">
                    <input id="{{ $domId('ability-'.$loop->index) }}" type="checkbox" wire:model="abilities" value="{{ $ability }}">
                    <label for="{{ $domId('ability-'.$loop->index) }}">{{ $label }}</label>
                </div>
            @endforeach
        </fieldset>

        <div>
            <label for="{{ $domId('token-days') }}">{{ $lang->get('sanctum-hardening::messages.screen.lifetime') }}</label>
            {{-- `.number` sends the chosen lifetime as a number, and the default's option, which has no value, as null. --}}
            <select id="{{ $domId('token-days') }}" wire:model.number="days">
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
            <label for="{{ $domId('token-networks') }}">{{ $lang->get('sanctum-hardening::messages.screen.networks') }}</label>
            {{-- A refused entry marks the field, which then points to the message naming that entry as well as to the hint. --}}
            <textarea id="{{ $domId('token-networks') }}" wire:model="networks" rows="3" aria-describedby="{{ $domId('token-networks-hint') }}@error('networks') {{ $domId('token-networks-error') }}@enderror"
                @error('networks') aria-invalid="true" @enderror></textarea>
            <p id="{{ $domId('token-networks-hint') }}">{{ $lang->get('sanctum-hardening::messages.screen.networks_hint') }}</p>
            @error('networks')
                <p id="{{ $domId('token-networks-error') }}">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit">{{ $lang->get('sanctum-hardening::messages.screen.mint') }}</button>
    </form>

    @if ($plainTextToken !== null)
        {{-- The token the mint has just created, right after the form, where the next Tab goes from the button that
             created it. A read-only field rather than text: the keyboard selects the whole token in it with Ctrl or Cmd
             and A, where text on a page is selected from the keyboard only with caret browsing, which Safari lacks. The
             field is as wide as the screen and scrolls the token inside it, so on a phone the page keeps its width. --}}
        <div id="{{ $domId('new-token') }}" data-sanctum-hardening-new-token>
            <label for="{{ $domId('new-token-value') }}">{{ $lang->get('sanctum-hardening::messages.screen.new_token') }}</label>
            <input id="{{ $domId('new-token-value') }}" type="text" value="{{ $plainTextToken }}" readonly aria-describedby="{{ $domId('new-token-hint') }}" style="box-sizing: border-box; width: 100%; font-family: monospace;">
            <p id="{{ $domId('new-token-hint') }}">{{ $lang->get('sanctum-hardening::messages.screen.copy_now') }}</p>
        </div>
    @endif

    {{-- Six columns are wider than a phone. The list scrolls sideways in an area of its own, which a keyboard
         reaches, rather than widening the page around it (WCAG 1.4.10 Reflow). The area is no landmark: the section
         is the screen's landmark and carries its name, and a second one under that name would only repeat it. --}}
    <div tabindex="0" style="overflow-x: auto;">
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
                        <td>
                            {{ $token->getAttribute('expires_at')?->locale($lang->getLocale())->isoFormat($lang->get('sanctum-hardening::messages.screen.formats.date')) ?? $lang->get('sanctum-hardening::messages.screen.never') }}
                            @isset ($replacedUntil[(string) $token->getKey()])
                                {{-- Replaced by a rotation: the old key works until the overlap has passed, to the hour. --}}
                                <div><small>{{ $lang->get('sanctum-hardening::messages.screen.replaced_until', ['date' => \Illuminate\Support\Carbon::instance($replacedUntil[(string) $token->getKey()])->locale($lang->getLocale())->isoFormat($lang->get('sanctum-hardening::messages.screen.formats.moment'))]) }}</small></div>
                            @endisset
                        </td>
                        <td>
                            {{-- Where Sanctum writes no last use, the column says it is not recorded rather than "Never". --}}
                            {{ $lastUseRecorded ? ($token->getAttribute('last_used_at')?->locale($lang->getLocale())->isoFormat($lang->get('sanctum-hardening::messages.screen.formats.moment')) ?? $lang->get('sanctum-hardening::messages.screen.never')) : $lang->get('sanctum-hardening::messages.screen.not_recorded') }}
                            @if (($uses[(string) $token->getKey()] ?? []) !== [])
                                <ul aria-label="{{ $lang->get('sanctum-hardening::messages.screen.recent') }}">
                                    @foreach ($uses[(string) $token->getKey()] as $use)
                                        <li>{{ $use['text'] }} <small>{{ $use['meta'] }}</small></li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td>
                            @if (in_array((string) $token->getKey(), $rotatable, true))
                                <button type="button" wire:click="rotate('{{ $token->getKey() }}')" wire:confirm="{{ $lang->get('sanctum-hardening::messages.screen.confirm_rotate', ['hours' => $overlapHours]) }}" aria-label="{{ $lang->get('sanctum-hardening::messages.screen.rotate_named', ['name' => $token->getAttribute('name')]) }}" data-sanctum-hardening-rotate="{{ $token->getKey() }}">
                                    {{ $lang->get('sanctum-hardening::messages.screen.rotate') }}
                                </button>
                            @endif
                            {{-- Marked with its token: once a token is revoked, the focus moves to the button of the one beside it. --}}
                            <button type="button" wire:click="revoke('{{ $token->getKey() }}')" wire:confirm="{{ $lang->get('sanctum-hardening::messages.screen.confirm_revoke') }}" aria-label="{{ $lang->get('sanctum-hardening::messages.screen.revoke_named', ['name' => $token->getAttribute('name')]) }}" data-sanctum-hardening-revoke="{{ $token->getKey() }}">
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
    </div>
    {{-- A part of the screen under its own heading rather than an aside: a complementary landmark inside the screen's
         section is announced as content beside the page. --}}
    <div id="{{ $domId('quickstart') }}">
        {{-- One level under the screen's heading, or under the page's where the screen shows none. --}}
        @if ($heading)
            <h3 id="{{ $domId('quickstart-heading') }}">{{ $lang->get('sanctum-hardening::messages.screen.quickstart') }}</h3>
        @else
            <h2 id="{{ $domId('quickstart-heading') }}">{{ $lang->get('sanctum-hardening::messages.screen.quickstart') }}</h2>
        @endif
        <p>{{ $lang->get('sanctum-hardening::messages.screen.quickstart_endpoint') }} <code>{{ $endpoint }}</code>.</p>
        <p>{{ $holder ? $lang->get('sanctum-hardening::messages.screen.holder.quickstart_limits', ['token' => $perToken, 'user' => $perUser]) : $lang->get('sanctum-hardening::messages.screen.quickstart_limits', ['token' => $perToken, 'user' => $perUser]) }}</p>
        {{-- Wrapped at its spaces rather than run past the edge: the request is wider than a phone. --}}
        <pre style="white-space: pre-wrap; overflow-wrap: anywhere;"><code>curl -H "Authorization: Bearer &lt;token&gt;" {{ $endpoint }}</code></pre>
    </div>
</section>
