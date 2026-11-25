{{-- The self-service screen of a user's API tokens, built from WireKit components. The same screen as the plain
     view: it offers only the abilities and lifetimes a mint accepts, and never a reserved ability. Every id comes
     from $domId(), which adds the id of the component, so two screens on one page never share one. Each part of the
     screen, the form, the list and the quickstart, stands in a card with a head, as the parts of a settings page do:
     an icon, the name of the part and one sentence on what it is for. With `:heading="false"` the page heads the screen
     itself: the section then takes the screen's name from aria-label, it is where the focus moves once the last token
     of the list is revoked, and the heads of the parts stand one level under the page's heading. --}}
<section id="{{ $domId('tokens') }}" @if ($heading) aria-labelledby="{{ $domId('tokens-heading') }}" @else aria-label="{{ $lang->get('sanctum-hardening::messages.screen.heading') }}" tabindex="-1" @endif data-sanctum-hardening-view="wirekit">
    <x-wirekit::stack>
        @if ($heading)
            {{-- Focusable from a script alone: the focus moves here once the last token of the list is revoked. --}}
            <x-wirekit::heading id="{{ $domId('tokens-heading') }}" level="2" tabindex="-1">{{ $lang->get('sanctum-hardening::messages.screen.heading') }}</x-wirekit::heading>
            <x-wirekit::text>{{ $holder ? $lang->get('sanctum-hardening::messages.screen.holder.intro') : $lang->get('sanctum-hardening::messages.screen.intro') }}</x-wirekit::text>
        @endif

        <p role="status" aria-live="polite" id="{{ $domId('tokens-status') }}">{{ $status }}</p>

        <x-wirekit::card>
            <x-wirekit::card.header :description="$lang->get('sanctum-hardening::messages.screen.parts.mint_description')">
                <x-slot:iconSlot><x-wirekit::icon name="key" size="md" /></x-slot:iconSlot>
                <x-wirekit::heading :level="$heading ? 3 : 2" size="lg">{{ $lang->get('sanctum-hardening::messages.screen.parts.mint') }}</x-wirekit::heading>
            </x-wirekit::card.header>
            <x-wirekit::card.body>
                <x-wirekit::stack>
                    @if ($asksForPassword)
                        <form wire:submit="confirmPassword" novalidate>
                            <x-wirekit::stack>
                                <x-wirekit::text>{{ $lang->get('sanctum-hardening::messages.screen.confirm_intro') }}</x-wirekit::text>
                                {{-- Required and said so with aria-required: the form submits with novalidate, so `required` only drew
                                     WireKit's asterisk, which a form of one field does not need. --}}
                                <x-wirekit::input type="password" wire:model="password" name="password" autocomplete="current-password" aria-required="true" :label="$lang->get('sanctum-hardening::messages.screen.password')" />
                                <x-wirekit::button type="submit" class="self-start">{{ $lang->get('sanctum-hardening::messages.screen.confirm') }}</x-wirekit::button>
                            </x-wirekit::stack>
                        </form>
                    @endif

                    <form wire:submit="mint" novalidate>
                        <x-wirekit::stack>
                            {{-- The form marks the field that is optional, Networks, and says the required one with aria-required
                                 rather than an asterisk nothing on the screen explains. A refused name is announced on the status
                                 line, so the message under the field is not announced a second time. --}}
                            <x-wirekit::input wire:model="name" name="name" maxlength="255" aria-required="true" :announce-error="false" :label="$lang->get('sanctum-hardening::messages.screen.name')" />

                            <x-wirekit::field.set :legend="$lang->get('sanctum-hardening::messages.screen.abilities')" name="abilities">
                                @foreach ($grantable as $ability => $label)
                                    <x-wirekit::checkbox wire:key="ability-{{ $ability }}" wire:model="abilities" value="{{ $ability }}" :label="$label" />
                                @endforeach
                            </x-wirekit::field.set>

                            <x-wirekit::select wire:model="days" name="days" :label="$lang->get('sanctum-hardening::messages.screen.lifetime')">
                                <option value="">{{ $lang->choice('sanctum-hardening::messages.screen.days', $defaultLifetime) }}</option>
                                @foreach ($lifetimes as $lifetime)
                                    <option value="{{ $lifetime }}">{{ $lang->choice('sanctum-hardening::messages.screen.days', $lifetime) }}</option>
                                @endforeach
                                @if ($allowsNoExpiry)
                                    <option value="0">{{ $lang->get('sanctum-hardening::messages.screen.no_expiry') }}</option>
                                @endif
                            </x-wirekit::select>

                            {{-- A refused entry marks the field, and WireKit points it to the message under it. The status line announces the
                                 refusal, so the message is not announced a second time. --}}
                            <x-wirekit::textarea wire:model="networks" name="networks" rows="3" :announce-error="false" :label="$lang->get('sanctum-hardening::messages.screen.networks')" :hint="$lang->get('sanctum-hardening::messages.screen.networks_hint')" />

                            {{-- At its own width: a stack stretches its children across, and a button as wide as the form
                                 reads as a bar rather than as the form's action. --}}
                            <x-wirekit::button type="submit" class="self-start">{{ $lang->get('sanctum-hardening::messages.screen.mint') }}</x-wirekit::button>
                        </x-wirekit::stack>
                    </form>

                    @if ($plainTextToken !== null)
                        {{-- The token the mint has just created, right after the form, where the next Tab goes from the button
                             that created it. A read-only field, so the keyboard selects the token in it, and copyable: the
                             button beside it copies the token, and WireKit announces that it did. --}}
                        <div id="{{ $domId('new-token') }}" data-sanctum-hardening-new-token>
                            <x-wirekit::input :value="$plainTextToken" readonly mono copyable :label="$lang->get('sanctum-hardening::messages.screen.new_token')" :hint="$lang->get('sanctum-hardening::messages.screen.copy_now')" />
                        </div>
                    @endif
                </x-wirekit::stack>
            </x-wirekit::card.body>
        </x-wirekit::card>

        <x-wirekit::card>
            <x-wirekit::card.header :description="$lang->get('sanctum-hardening::messages.screen.parts.tokens_description')">
                <x-slot:iconSlot><x-wirekit::icon name="list-bullets" size="md" /></x-slot:iconSlot>
                <x-wirekit::heading :level="$heading ? 3 : 2" size="lg">{{ $lang->get('sanctum-hardening::messages.screen.parts.tokens') }}</x-wirekit::heading>
            </x-wirekit::card.header>
            <x-wirekit::card.body>
                <x-wirekit::stack>
                    @if ($overlaps !== [] && $rotatable !== [])
                        {{-- How long a token a rotation replaces keeps working, chosen before the rotation. Live, so the question of
                             each rotate button names the overlap chosen. --}}
                        <x-wirekit::select wire:model.live="overlap" name="overlap" :label="$lang->get('sanctum-hardening::messages.screen.overlap')">
                            <option value="">{{ $duration($defaultOverlap) }}</option>
                            @foreach ($overlaps as $hours)
                                <option value="{{ $hours }}">{{ $duration($hours) }}</option>
                            @endforeach
                        </x-wirekit::select>
                    @endif

                    {{-- No tableLabel: the section is the screen's landmark and carries its name, and a table named the same would
                         be a second landmark under that name. WireKit keeps the table's scroller reachable by keyboard without one. --}}
                    {{-- Stacked below md, each token a card of its own: six columns are wider than a phone, and the
                         buttons of a token stood outside the screen. WireKit stacks from 2.70.0 on; an older WireKit
                         takes the attribute as an attribute and keeps the table. --}}
                    <x-wirekit::table stack-below="md">
                        <x-wirekit::table.head>
                            <x-wirekit::table.row>
                                <x-wirekit::table.th>{{ $lang->get('sanctum-hardening::messages.screen.name') }}</x-wirekit::table.th>
                                <x-wirekit::table.th>{{ $lang->get('sanctum-hardening::messages.screen.prefix') }}</x-wirekit::table.th>
                                <x-wirekit::table.th>{{ $lang->get('sanctum-hardening::messages.screen.abilities') }}</x-wirekit::table.th>
                                <x-wirekit::table.th>{{ $lang->get('sanctum-hardening::messages.screen.expires') }}</x-wirekit::table.th>
                                <x-wirekit::table.th>{{ $lang->get('sanctum-hardening::messages.screen.last_used') }}</x-wirekit::table.th>
                                <x-wirekit::table.th><span class="sr-only">{{ $lang->get('sanctum-hardening::messages.screen.revoke') }}</span></x-wirekit::table.th>
                            </x-wirekit::table.row>
                        </x-wirekit::table.head>
                        <x-wirekit::table.body>
                            @forelse ($tokens as $token)
                                <x-wirekit::table.row wire:key="token-{{ $token->getKey() }}">
                                    <x-wirekit::table.td>{{ $token->getAttribute('name') }}</x-wirekit::table.td>
                                    {{-- In a block, so a long key breaks inside its cell where the table stacks its rows: a code that is
                                         the cell's own item keeps the width of its unbroken word, and a key wider than the card ran out of it. --}}
                                    <x-wirekit::table.td><div><x-wirekit::code>{{ $prefixes[$token->getKey()] ?? '' }}</x-wirekit::code></div></x-wirekit::table.td>
                                    <x-wirekit::table.td>
                                        {{ implode(', ', array_map(static fn (mixed $ability): string => is_string($ability) ? ($grantable[$ability] ?? $ability) : '', (array) $token->getAttribute('abilities'))) }}
                                        @if (($limits[(string) $token->getKey()] ?? []) !== [])
                                            <x-wirekit::text size="sm" intent="muted" leading="tight" break="anywhere">{{ $lang->get('sanctum-hardening::messages.screen.only_from', ['networks' => implode(', ', $limits[(string) $token->getKey()])]) }}</x-wirekit::text>
                                        @endif
                                    </x-wirekit::table.td>
                                    {{-- A date or a moment on one line: broken over two it reads as two values. --}}
                                    <x-wirekit::table.td>
                                        <span class="whitespace-nowrap">{{ $token->getAttribute('expires_at')?->locale($lang->getLocale())->isoFormat($lang->get('sanctum-hardening::messages.screen.formats.date')) ?? $lang->get('sanctum-hardening::messages.screen.never') }}</span>
                                        @isset ($replacedUntil[(string) $token->getKey()])
                                            {{-- Replaced by a rotation: the old key works until the overlap has passed, to the hour. --}}
                                            <x-wirekit::text size="sm" intent="muted" leading="tight" class="whitespace-nowrap">{{ $lang->get('sanctum-hardening::messages.screen.replaced_until', ['date' => \Illuminate\Support\Carbon::instance($replacedUntil[(string) $token->getKey()])->locale($lang->getLocale())->isoFormat($lang->get('sanctum-hardening::messages.screen.formats.moment'))]) }}</x-wirekit::text>
                                        @endisset
                                    </x-wirekit::table.td>
                                    <x-wirekit::table.td>
                                        {{-- Where Sanctum writes no last use, the column says it is not recorded rather than "Never". --}}
                                        <span class="whitespace-nowrap">{{ $lastUseRecorded ? ($token->getAttribute('last_used_at')?->locale($lang->getLocale())->isoFormat($lang->get('sanctum-hardening::messages.screen.formats.moment')) ?? $lang->get('sanctum-hardening::messages.screen.never')) : $lang->get('sanctum-hardening::messages.screen.not_recorded') }}</span>
                                        @if (($uses[(string) $token->getKey()] ?? []) !== [])
                                            <x-wirekit::list type="none" :items="$uses[(string) $token->getKey()]" :aria-label="$lang->get('sanctum-hardening::messages.screen.recent')" />
                                        @endif
                                    </x-wirekit::table.td>
                                    <x-wirekit::table.td actions>
                                        {{-- A row that wraps: in a narrow last column the two buttons go onto two lines and keep the
                                             same gap between them as side by side. --}}
                                        <x-wirekit::row gap="sm" wrap data-sanctum-hardening-actions>
                                            @if (in_array((string) $token->getKey(), $rotatable, true))
                                                {{-- wire:confirm keeps the question the button was drawn with, so the button is keyed by the
                                                     overlap and drawn anew once another one is chosen. --}}
                                                <x-wirekit::button type="button" wire:key="rotate-{{ $token->getKey() }}-{{ $overlapHours }}" wire:click="rotate('{{ $token->getKey() }}')" wire:confirm="{{ $lang->get('sanctum-hardening::messages.screen.confirm_rotate', ['hours' => $overlapHours, 'overlap' => $duration($overlapHours)]) }}" aria-label="{{ $lang->get('sanctum-hardening::messages.screen.rotate_named', ['name' => $token->getAttribute('name')]) }}" data-sanctum-hardening-rotate="{{ $token->getKey() }}">
                                                    {{ $lang->get('sanctum-hardening::messages.screen.rotate') }}
                                                </x-wirekit::button>
                                            @endif
                                            {{-- Revoking asks first, in the kit's question, and its confirmation revokes. The button that opens it
                                                 is marked with its token: once a token is revoked, the focus moves to the button of the one beside it.
                                                 The dialog closes on the confirmation and hands the focus back to that button before the row leaves,
                                                 and `contents` lets the button stand in the row as the one beside it does. --}}
                                            <x-wirekit::alert-dialog :name="'sanctum-hardening-revoke-'.$token->getKey()" close-on-confirm class="contents">
                                                <x-slot:trigger>
                                                    <x-wirekit::button type="button" intent="danger" aria-label="{{ $lang->get('sanctum-hardening::messages.screen.revoke_named', ['name' => $token->getAttribute('name')]) }}" data-sanctum-hardening-revoke="{{ $token->getKey() }}">
                                                        {{ $lang->get('sanctum-hardening::messages.screen.revoke') }}
                                                    </x-wirekit::button>
                                                </x-slot:trigger>

                                                <x-wirekit::alert-dialog.title>{{ $lang->get('sanctum-hardening::messages.screen.revoke_named', ['name' => $token->getAttribute('name')]) }}</x-wirekit::alert-dialog.title>
                                                <x-wirekit::alert-dialog.description>{{ $lang->get('sanctum-hardening::messages.screen.confirm_revoke') }}</x-wirekit::alert-dialog.description>
                                                <x-wirekit::alert-dialog.actions>
                                                    <x-wirekit::alert-dialog.cancel />
                                                    <x-wirekit::button type="button" intent="danger" data-wk-dialog-primary wire:click="revoke('{{ $token->getKey() }}')" data-sanctum-hardening-revoke-confirm="{{ $token->getKey() }}">{{ $lang->get('sanctum-hardening::messages.screen.revoke') }}</x-wirekit::button>
                                                </x-wirekit::alert-dialog.actions>
                                            </x-wirekit::alert-dialog>
                                        </x-wirekit::row>
                                    </x-wirekit::table.td>
                                </x-wirekit::table.row>
                            @empty
                                <x-wirekit::table.row>
                                    <x-wirekit::table.td colspan="6">{{ $holder ? $lang->get('sanctum-hardening::messages.screen.holder.none') : $lang->get('sanctum-hardening::messages.screen.none') }}</x-wirekit::table.td>
                                </x-wirekit::table.row>
                            @endforelse
                        </x-wirekit::table.body>
                    </x-wirekit::table>
                </x-wirekit::stack>
            </x-wirekit::card.body>
        </x-wirekit::card>

        {{-- A part of the screen under its own heading rather than an aside: a complementary landmark inside the screen's
             section is announced as content beside the page. --}}
        <x-wirekit::card id="{{ $domId('quickstart') }}">
            <x-wirekit::card.header :description="$lang->get('sanctum-hardening::messages.screen.parts.quickstart_description')">
                <x-slot:iconSlot><x-wirekit::icon name="command-line" size="md" /></x-slot:iconSlot>
                <x-wirekit::heading id="{{ $domId('quickstart-heading') }}" :level="$heading ? 3 : 2" size="lg">{{ $lang->get('sanctum-hardening::messages.screen.quickstart') }}</x-wirekit::heading>
            </x-wirekit::card.header>
            <x-wirekit::card.body>
                {{-- A stack, so the two sentences and the example keep the screen's spacing apart. --}}
                <x-wirekit::stack gap="sm">
                    <x-wirekit::text>{{ $lang->get('sanctum-hardening::messages.screen.quickstart_endpoint') }} <x-wirekit::code>{{ $endpoint }}</x-wirekit::code>.</x-wirekit::text>
                    {{-- The sentence on the limits, with a part left out where the application names no limit for it. --}}
                    @if ($quickstartLimits !== null)
                        <x-wirekit::text>{{ $quickstartLimits }}</x-wirekit::text>
                    @endif
                    <x-wirekit::code-block language="bash">curl -H "Authorization: Bearer &lt;token&gt;" {{ $endpoint }}</x-wirekit::code-block>
                </x-wirekit::stack>
            </x-wirekit::card.body>
        </x-wirekit::card>
    </x-wirekit::stack>
</section>
