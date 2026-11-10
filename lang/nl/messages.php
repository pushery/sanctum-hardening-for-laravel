<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Te veel verzoeken. Een nieuw verzoek kan worden verstuurd zodra de tijd in de header Retry-After voorbij is.',
    // The self-service screen of a user's tokens.
    'screen' => [
        'heading' => 'API-tokens',
        'intro' => 'Een token kan wat zijn bevoegdheden noemen, en nooit meer dan je account.',
        'name' => 'Naam',
        'abilities' => 'Bevoegdheden',
        'lifetime' => 'Looptijd',
        'days' => ':count dag|:count dagen',
        'no_expiry' => 'Geen vervaldatum',
        'mint' => 'Token aanmaken',
        'copy_now' => 'Kopieer het token nu. Het wordt alleen deze ene keer getoond.',
        'minted' => 'Het token is aangemaakt.',
        'revoke' => 'Intrekken',
        'revoke_named' => ':name intrekken',
        'confirm_revoke' => 'Dit token intrekken? Verzoeken die het gebruiken, werken dan niet meer.',
        'revoked' => 'Het token is ingetrokken.',
        'not_revoked' => 'Er is geen token ingetrokken.',
        'none' => 'Nog geen tokens.',
        'prefix' => 'Prefix',
        'expires' => 'Verloopt',
        'last_used' => 'Laatst gebruikt',
        'recent' => 'Recente verzoeken',
        'never' => 'Nooit',
        'networks' => 'Netwerken',
        'networks_hint' => 'Optioneel. Eén adres of netwerk per regel, zoals 203.0.113.7 of 10.0.0.0/8. Het token werkt dan alleen vanaf daar.',
        'only_from' => 'Alleen vanaf :networks',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'L',
            'moment' => 'L LT',
        ],
        'quickstart' => 'Snelstart',
        'quickstart_endpoint' => 'Stuur een verzoek met het token naar',
        'quickstart_limits' => 'Elk token doet tot :token verzoeken per minuut, en een account tot :user over al zijn tokens.',
        // Asking for the password on the screen itself, where it was not confirmed a moment ago.
        'confirm_intro' => 'Bevestig je wachtwoord voordat je een token aanmaakt.',
        'password' => 'Wachtwoord',
        'confirm' => 'Wachtwoord bevestigen',
        'confirmed' => 'Je wachtwoord is bevestigd.',
        'not_confirmed' => 'Dat is niet je wachtwoord.',
        'confirm_too_fast' => 'Te veel verkeerde wachtwoorden. Probeer het over een minuut opnieuw.',
        // The lines that speak of the account, for a screen that manages the tokens of another holder,
        // a workspace for instance. An application names its holder by publishing these lines.
        'holder' => [
            'intro' => 'Een token kan wat zijn bevoegdheden noemen, en nooit meer dan de houder waartoe het behoort.',
            'none' => 'Nog geen tokens.',
            'quickstart_limits' => 'Elk token doet tot :token verzoeken per minuut, en de tokens van deze houder samen tot :user.',
            'refused' => [
                'not_tokenable' => 'Voor deze houder kunnen geen tokens worden aangemaakt.',
                'limit_reached' => 'Deze houder heeft zoveel tokens als toegestaan. Trek er eerst een in.',
            ],
        ],
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Kies minstens één bevoegdheid.',
            'wildcard' => 'Een token kan niet alle bevoegdheden tegelijk dragen.',
            'unknown' => 'Een van de bevoegdheden wordt niet aangeboden.',
            'reserved' => 'Een van de bevoegdheden wordt hier niet aangeboden.',
            'malformed' => 'Een van de bevoegdheden wordt niet aangeboden.',
            'not_tokenable' => 'Voor dit account kunnen geen tokens worden aangemaakt.',
            'not_allowed' => 'Je mag de tokens van deze houder niet beheren.',
            'password_not_confirmed' => 'Bevestig je wachtwoord voordat je een token aanmaakt.',
            'too_fast' => 'Er worden te snel tokens aangemaakt. Probeer het zo opnieuw.',
            'limit_reached' => 'Je hebt zoveel tokens als toegestaan. Trek er eerst een in.',
            'lifetime' => 'Deze looptijd wordt niet aangeboden.',
            'name' => 'Geef het token een naam van hoogstens 255 tekens.',
            'network' => 'Een van de adressen of netwerken kan een token niet beperken.',
        ],
    ],
];
