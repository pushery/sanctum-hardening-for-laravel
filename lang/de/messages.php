<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Zu viele Anfragen. Eine weitere kann gesendet werden, sobald die Zeit im Header Retry-After vergangen ist.',
    // The detail of the problem document a write is answered with where the tier allows no writes.
    'writes_refused' => 'Diese Stufe erlaubt keine Anfragen, die schreiben oder löschen.',
    // The self-service screen of a user's tokens.
    'screen' => [
        'heading' => 'API-Tokens',
        'intro' => 'Ein Token kann, was seine Fähigkeiten nennen, und nie mehr als dein Konto.',
        'name' => 'Name',
        'abilities' => 'Fähigkeiten',
        'lifetime' => 'Laufzeit',
        'days' => '{1} :count Tag|[2,*] :count Tage',
        'no_expiry' => 'Kein Ablauf',
        'mint' => 'Token erstellen',
        'copy_now' => 'Kopiere das Token jetzt. Es wird nur dieses eine Mal angezeigt.',
        // The label of the field that shows a new token once. WireKit names the copy button beside it after it.
        'new_token' => 'Token',
        'minted' => 'Das Token wurde erstellt.',
        'revoke' => 'Widerrufen',
        'revoke_named' => '„:name“ widerrufen',
        'confirm_revoke' => 'Dieses Token widerrufen? Anfragen, die es nutzen, funktionieren dann nicht mehr.',
        'revoked' => 'Das Token wurde widerrufen.',
        'not_revoked' => 'Es wurde kein Token widerrufen.',
        'rotate' => 'Erneuern',
        'rotate_named' => '„:name“ erneuern',
        'confirm_rotate' => 'Dieses Token durch ein neues ersetzen? Es funktioniert noch :overlap, damit alles, was es nutzt, das neue übernehmen kann.',
        'overlap' => 'Nach dem Erneuern funktioniert das alte Token noch',
        'hours' => '{1} :count Stunde|[2,*] :count Stunden',
        'rotated' => 'Ein neues Token ersetzt es. Das alte funktioniert bis zu der Zeit, die daneben steht.',
        'not_rotated' => 'Es wurde kein Token ersetzt.',
        'replaced_until' => 'Ersetzt, funktioniert bis :date',
        'none' => 'Noch keine Tokens.',
        'prefix' => 'Präfix',
        'expires' => 'Läuft ab',
        'last_used' => 'Zuletzt genutzt',
        'recent' => 'Letzte Anfragen',
        'never' => 'Nie',
        'not_recorded' => 'Nicht erfasst',
        'networks' => 'Netze',
        'networks_hint' => 'Optional. Eine Adresse oder ein Netz je Zeile, etwa 203.0.113.7 oder 10.0.0.0/8. Das Token funktioniert dann nur von dort.',
        'only_from' => 'Nur von :networks',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'll',
            'moment' => 'll LT [Uhr]',
        ],
        'quickstart' => 'Schnellstart',
        'quickstart_endpoint' => 'Sende eine Anfrage mit dem Token an',
        'quickstart_limits' => 'Jedes Token stellt bis zu :token Anfragen pro Minute, ein Konto bis zu :user über alle seine Tokens.',
        'quickstart_token_limit' => 'Jedes Token stellt bis zu :token Anfragen pro Minute.',
        'quickstart_user_limit' => 'Ein Konto stellt bis zu :user Anfragen pro Minute über alle seine Tokens.',
        // The parts of the screen, each with its name and one sentence on what it is for, as the WireKit view heads the
        // card each part stands in. The quickstart takes its name from `quickstart` above.
        'parts' => [
            'mint' => 'Neues Token',
            'mint_description' => 'Ein Token mit den Fähigkeiten, der Laufzeit und den Netzen, die du wählst.',
            'tokens' => 'Tokens',
            'tokens_description' => 'Jedes Token, das neueste zuerst, mit seinen Fähigkeiten, seinem Ablauf und seiner letzten Nutzung.',
            'quickstart_description' => 'Wohin ein Programm seine Anfragen mit einem Token schickt, und wie viele es schicken darf.',
        ],
        // Asking for the password on the screen itself, where it was not confirmed a moment ago.
        'confirm_intro' => 'Bestätige dein Passwort, bevor du ein Token erstellst.',
        'password' => 'Passwort',
        'confirm' => 'Passwort bestätigen',
        'confirmed' => 'Dein Passwort ist bestätigt.',
        'not_confirmed' => 'Das ist nicht dein Passwort.',
        'confirm_too_fast' => 'Zu viele falsche Passwörter. Versuche es in einer Minute noch einmal.',
        // The lines that speak of the account, for a screen that manages the tokens of another holder,
        // a workspace for instance. An application names its holder by publishing these lines.
        'holder' => [
            'intro' => 'Ein Token kann, was seine Fähigkeiten nennen, und nie mehr als der Inhaber, dem es gehört.',
            'none' => 'Noch keine Tokens.',
            'quickstart_limits' => 'Jedes Token stellt bis zu :token Anfragen pro Minute, die Tokens dieses Inhabers zusammen bis zu :user.',
            'quickstart_user_limit' => 'Die Tokens dieses Inhabers stellen zusammen bis zu :user Anfragen pro Minute.',
            'refused' => [
                'not_tokenable' => 'Für diesen Inhaber lassen sich keine Tokens erstellen.',
                'limit_reached' => 'Dieser Inhaber hat so viele Tokens wie erlaubt. Widerrufe zuerst eines.',
            ],
        ],
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Wähle mindestens eine Fähigkeit.',
            'wildcard' => 'Ein Token kann nicht alle Fähigkeiten auf einmal tragen.',
            'unknown' => 'Eine der Fähigkeiten wird nicht angeboten.',
            'reserved' => 'Eine der Fähigkeiten wird hier nicht angeboten.',
            'holder' => 'Eine der Fähigkeiten wird für diesen Halter nicht angeboten.',
            'malformed' => 'Eine der Fähigkeiten wird nicht angeboten.',
            'not_tokenable' => 'Für dieses Konto lassen sich keine Tokens erstellen.',
            'not_allowed' => 'Du darfst die Tokens dieses Inhabers nicht verwalten.',
            'password_not_confirmed' => 'Bestätige dein Passwort, bevor du ein Token erstellst.',
            'too_fast' => 'Tokens werden zu schnell erstellt. Versuche es gleich noch einmal.',
            'limit_reached' => 'Du hast so viele Tokens wie erlaubt. Widerrufe zuerst eines.',
            'lifetime' => 'Diese Laufzeit wird nicht angeboten.',
            'overlap' => 'Wähle aus der Liste, wie lange das alte Token noch funktioniert.',
            'name' => 'Gib dem Token einen Namen mit höchstens 255 Zeichen.',
            'network' => 'Ein Token lässt sich nicht auf „:network“ beschränken: Das ist keine Adresse und kein Netz.',
            'too_many_networks' => 'Ein Token lässt sich auf höchstens :limit Adressen und Netze beschränken.',
            'refused_by_holder' => ':refusal',
        ],
    ],
    // The mails to the holder of a token before it expires and once it has gone unused.
    'alerts' => [
        'prefix' => 'Das Token beginnt mit :prefix.',
        'revoke' => 'Wenn du es nicht mehr brauchst, widerrufe es auf der Seite mit deinen API-Tokens.',
        'open' => 'API-Tokens öffnen',
        'expiring' => [
            'subject' => 'Dein API-Token „:name“ läuft am :date ab',
            'line' => 'Dein API-Token „:name“ läuft am :date ab. Anfragen, die es nutzen, funktionieren dann nicht mehr. Erstelle also vorher ein neues, wenn du es noch brauchst.',
        ],
        'inactive' => [
            'subject_used' => 'Dein API-Token „:name“ wurde seit dem :date nicht mehr genutzt',
            'line_used' => 'Dein API-Token „:name“ wurde zuletzt am :date genutzt.',
            'subject_never' => 'Dein API-Token „:name“ wurde noch nie genutzt',
            'line_never' => 'Dein API-Token „:name“ wurde am :date erstellt und noch nie genutzt.',
            'risk' => 'Ein Token, das niemand nutzt, funktioniert trotzdem für jeden, der es hat.',
        ],
    ],
];
