{{-- The self-service screen of a user's API tokens, built from WireKit components. The same screen as the plain
     view: it offers only the abilities and lifetimes a mint accepts, and never a reserved ability. --}}
<section aria-labelledby="sanctum-hardening-tokens-heading" data-sanctum-hardening-view="wirekit">
    <x-wirekit::stack>
        <x-wirekit::heading id="sanctum-hardening-tokens-heading" level="2">{{ $lang->get('sanctum-hardening::messages.screen.heading') }}</x-wirekit::heading>
        <x-wirekit::text>{{ $lang->get('sanctum-hardening::messages.screen.intro') }}</x-wirekit::text>

        <p role="status" aria-live="polite" id="sanctum-hardening-tokens-status">{{ $status }}</p>

        @if ($plainTextToken !== null)
            <div id="sanctum-hardening-new-token">
                <x-wirekit::text>{{ $lang->get('sanctum-hardening::messages.screen.copy_now') }}</x-wirekit::text>
                <x-wirekit::code>{{ $plainTextToken }}</x-wirekit::code>
            </div>
        @endif

        <form wire:submit="mint" novalidate>
            <x-wirekit::stack>
                <x-wirekit::input wire:model="name" name="name" maxlength="255" required :label="$lang->get('sanctum-hardening::messages.screen.name')" />

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

                <x-wirekit::textarea wire:model="networks" name="networks" rows="3" :label="$lang->get('sanctum-hardening::messages.screen.networks')" :hint="$lang->get('sanctum-hardening::messages.screen.networks_hint')" />

                <x-wirekit::button type="submit">{{ $lang->get('sanctum-hardening::messages.screen.mint') }}</x-wirekit::button>
            </x-wirekit::stack>
        </form>

        <x-wirekit::table :tableLabel="$lang->get('sanctum-hardening::messages.screen.heading')">
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
                        <x-wirekit::table.td><x-wirekit::code>{{ $prefixes[$token->getKey()] ?? '' }}</x-wirekit::code></x-wirekit::table.td>
                        <x-wirekit::table.td>
                            {{ implode(', ', array_map(static fn (mixed $ability): string => is_string($ability) ? ($grantable[$ability] ?? $ability) : '', (array) $token->getAttribute('abilities'))) }}
                            @if (($limits[(string) $token->getKey()] ?? []) !== [])
                                <x-wirekit::text size="sm" intent="muted" leading="tight" break="anywhere">{{ $lang->get('sanctum-hardening::messages.screen.only_from', ['networks' => implode(', ', $limits[(string) $token->getKey()])]) }}</x-wirekit::text>
                            @endif
                        </x-wirekit::table.td>
                        <x-wirekit::table.td>{{ $token->getAttribute('expires_at')?->locale($lang->getLocale())->isoFormat($lang->get('sanctum-hardening::messages.screen.formats.date')) ?? $lang->get('sanctum-hardening::messages.screen.never') }}</x-wirekit::table.td>
                        <x-wirekit::table.td>
                            {{ $token->getAttribute('last_used_at')?->locale($lang->getLocale())->isoFormat($lang->get('sanctum-hardening::messages.screen.formats.moment')) ?? $lang->get('sanctum-hardening::messages.screen.never') }}
                            @if (($uses[(string) $token->getKey()] ?? []) !== [])
                                <x-wirekit::list type="none" :items="$uses[(string) $token->getKey()]" :aria-label="$lang->get('sanctum-hardening::messages.screen.recent')" />
                            @endif
                        </x-wirekit::table.td>
                        <x-wirekit::table.td>
                            <x-wirekit::button type="button" intent="danger" wire:click="revoke('{{ $token->getKey() }}')" wire:confirm="{{ $lang->get('sanctum-hardening::messages.screen.confirm_revoke') }}" aria-label="{{ $lang->get('sanctum-hardening::messages.screen.revoke_named', ['name' => $token->getAttribute('name')]) }}">
                                {{ $lang->get('sanctum-hardening::messages.screen.revoke') }}
                            </x-wirekit::button>
                        </x-wirekit::table.td>
                    </x-wirekit::table.row>
                @empty
                    <x-wirekit::table.row>
                        <x-wirekit::table.td colspan="6">{{ $lang->get('sanctum-hardening::messages.screen.none') }}</x-wirekit::table.td>
                    </x-wirekit::table.row>
                @endforelse
            </x-wirekit::table.body>
        </x-wirekit::table>
        <aside aria-labelledby="sanctum-hardening-quickstart-heading" id="sanctum-hardening-quickstart">
            <x-wirekit::heading id="sanctum-hardening-quickstart-heading" level="3">{{ $lang->get('sanctum-hardening::messages.screen.quickstart') }}</x-wirekit::heading>
            <x-wirekit::text>{{ $lang->get('sanctum-hardening::messages.screen.quickstart_endpoint') }} <x-wirekit::code>{{ $endpoint }}</x-wirekit::code></x-wirekit::text>
            <x-wirekit::text>{{ $lang->get('sanctum-hardening::messages.screen.quickstart_limits', ['token' => $perToken, 'user' => $perUser]) }}</x-wirekit::text>
            <x-wirekit::code-block language="bash">curl -H "Authorization: Bearer &lt;token&gt;" {{ $endpoint }}</x-wirekit::code-block>
        </aside>
    </x-wirekit::stack>
</section>
