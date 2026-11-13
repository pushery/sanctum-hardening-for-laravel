<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Troppe richieste. Se ne può inviare un’altra trascorso il tempo dell’intestazione Retry-After.',
    // The self-service screen of a user's tokens.
    'screen' => [
        'heading' => 'Token API',
        'intro' => 'Un token può ciò che nominano le sue capacità e mai più del tuo account.',
        'name' => 'Nome',
        'abilities' => 'Capacità',
        'lifetime' => 'Durata',
        'days' => ':count giorno|:count giorni',
        'no_expiry' => 'Senza scadenza',
        'mint' => 'Crea token',
        'copy_now' => 'Copia il token ora. Viene mostrato solo questa volta.',
        'minted' => 'Il token è stato creato.',
        'revoke' => 'Revoca',
        'revoke_named' => 'Revoca :name',
        'confirm_revoke' => 'Revocare questo token? Le richieste che lo usano smetteranno di funzionare.',
        'revoked' => 'Il token è stato revocato.',
        'not_revoked' => 'Nessun token è stato revocato.',
        'none' => 'Ancora nessun token.',
        'prefix' => 'Prefisso',
        'expires' => 'Scade',
        'last_used' => 'Ultimo uso',
        'recent' => 'Richieste recenti',
        'never' => 'Mai',
        'networks' => 'Reti',
        'networks_hint' => 'Facoltativo. Un indirizzo o una rete per riga, ad esempio 203.0.113.7 o 10.0.0.0/8. Il token funziona allora solo da lì.',
        'only_from' => 'Solo da :networks',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'll',
            'moment' => 'll LT',
        ],
        'quickstart' => 'Avvio rapido',
        'quickstart_endpoint' => 'Invia una richiesta con il token a',
        'quickstart_limits' => 'Ogni token fa fino a :token richieste al minuto, e un account fino a :user con tutti i suoi token.',
        // Asking for the password on the screen itself, where it was not confirmed a moment ago.
        'confirm_intro' => 'Conferma la password prima di creare un token.',
        'password' => 'Password',
        'confirm' => 'Conferma password',
        'confirmed' => 'La tua password è confermata.',
        'not_confirmed' => 'Questa non è la tua password.',
        'confirm_too_fast' => 'Troppe password sbagliate. Riprova tra un minuto.',
        // The lines that speak of the account, for a screen that manages the tokens of another holder,
        // a workspace for instance. An application names its holder by publishing these lines.
        'holder' => [
            'intro' => 'Un token può ciò che nominano le sue capacità e mai più del titolare a cui appartiene.',
            'none' => 'Ancora nessun token.',
            'quickstart_limits' => 'Ogni token fa fino a :token richieste al minuto, e i token di questo titolare fino a :user insieme.',
            'refused' => [
                'not_tokenable' => 'Per questo titolare non si possono creare token.',
                'limit_reached' => 'Questo titolare ha tutti i token consentiti. Revocane prima uno.',
            ],
        ],
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Scegli almeno una capacità.',
            'wildcard' => 'Un token non può avere tutte le capacità insieme.',
            'unknown' => 'Una delle capacità non è offerta.',
            'reserved' => 'Una delle capacità non è offerta qui.',
            'malformed' => 'Una delle capacità non è offerta.',
            'not_tokenable' => 'Per questo account non si possono creare token.',
            'not_allowed' => 'Non puoi gestire i token di questo titolare.',
            'password_not_confirmed' => 'Conferma la password prima di creare un token.',
            'too_fast' => 'I token vengono creati troppo in fretta. Riprova tra un momento.',
            'limit_reached' => 'Hai tutti i token consentiti. Revocane prima uno.',
            'lifetime' => 'Questa durata non è offerta.',
            'name' => 'Dai al token un nome di al massimo 255 caratteri.',
            'network' => 'Uno degli indirizzi o delle reti non può limitare un token.',
        ],
    ],
    // The mails to the holder of a token before it expires and once it has gone unused.
    'alerts' => [
        'prefix' => 'Il token inizia con :prefix.',
        'revoke' => 'Se non ti serve più, revocalo nella pagina dei tuoi token API.',
        'open' => 'Apri i tuoi token API',
        'expiring' => [
            'subject' => 'Il tuo token API «:name» scade il :date',
            'line' => 'Il tuo token API «:name» scade il :date. Da allora le richieste che lo usano smetteranno di funzionare, quindi creane uno nuovo prima se ti serve ancora.',
        ],
        'inactive' => [
            'subject_used' => 'Il tuo token API «:name» non viene usato dal :date',
            'line_used' => 'Il tuo token API «:name» è stato usato l’ultima volta il :date.',
            'subject_never' => 'Il tuo token API «:name» non è mai stato usato',
            'line_never' => 'Il tuo token API «:name» è stato creato il :date e non è mai stato usato.',
            'risk' => 'Un token che nessuno usa funziona comunque per chiunque lo possieda.',
        ],
    ],
];
