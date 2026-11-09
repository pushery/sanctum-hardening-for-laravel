<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Zu viele Anfragen. Eine weitere kann gesendet werden, sobald die Zeit im Header Retry-After vergangen ist.',
    // The self-service screen of a user's tokens.
    'screen' => [
        'heading' => 'API-Tokens',
        'intro' => 'Ein Token kann, was seine Fähigkeiten nennen, und nie mehr als dein Konto.',
        'name' => 'Name',
        'abilities' => 'Fähigkeiten',
        'lifetime' => 'Laufzeit',
        'days' => ':count Tag|:count Tage',
        'no_expiry' => 'Kein Ablauf',
        'mint' => 'Token erstellen',
        'copy_now' => 'Kopiere das Token jetzt. Es wird nur dieses eine Mal angezeigt.',
        'minted' => 'Das Token wurde erstellt.',
        'revoke' => 'Widerrufen',
        'revoke_named' => '„:name“ widerrufen',
        'confirm_revoke' => 'Dieses Token widerrufen? Anfragen, die es nutzen, funktionieren dann nicht mehr.',
        'revoked' => 'Das Token wurde widerrufen.',
        'not_revoked' => 'Es wurde kein Token widerrufen.',
        'none' => 'Noch keine Tokens.',
        'prefix' => 'Präfix',
        'expires' => 'Läuft ab',
        'last_used' => 'Zuletzt genutzt',
        'recent' => 'Letzte Anfragen',
        'never' => 'Nie',
        'networks' => 'Netze',
        'networks_hint' => 'Optional. Eine Adresse oder ein Netz je Zeile, etwa 203.0.113.7 oder 10.0.0.0/8. Das Token funktioniert dann nur von dort.',
        'only_from' => 'Nur von :networks',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'L',
            'moment' => 'L LT [Uhr]',
        ],
        'quickstart' => 'Schnellstart',
        'quickstart_endpoint' => 'Sende eine Anfrage mit dem Token an',
        'quickstart_limits' => 'Jedes Token stellt bis zu :token Anfragen pro Minute, ein Konto bis zu :user über alle seine Tokens.',
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Wähle mindestens eine Fähigkeit.',
            'wildcard' => 'Ein Token kann nicht alle Fähigkeiten auf einmal tragen.',
            'unknown' => 'Eine der Fähigkeiten wird nicht angeboten.',
            'reserved' => 'Eine der Fähigkeiten wird hier nicht angeboten.',
            'malformed' => 'Eine der Fähigkeiten wird nicht angeboten.',
            'not_tokenable' => 'Für dieses Konto lassen sich keine Tokens erstellen.',
            'password_not_confirmed' => 'Bestätige dein Passwort, bevor du ein Token erstellst.',
            'too_fast' => 'Tokens werden zu schnell erstellt. Versuche es gleich noch einmal.',
            'limit_reached' => 'Du hast so viele Tokens wie erlaubt. Widerrufe zuerst eines.',
            'lifetime' => 'Diese Laufzeit wird nicht angeboten.',
            'name' => 'Gib dem Token einen Namen mit höchstens 255 Zeichen.',
            'network' => 'Eine der Adressen oder eines der Netze taugt nicht als Beschränkung eines Tokens.',
        ],
    ],
];
