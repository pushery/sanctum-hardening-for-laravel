<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Troppe richieste. Se ne può inviare un’altra trascorso il tempo dell’intestazione Retry-After.',
    // The detail of the problem document a write is answered with where the tier allows no writes.
    'writes_refused' => 'Questo livello non consente richieste che scrivano o eliminino.',
    // The self-service screen of a user's tokens.
    'screen' => [
        'heading' => 'Token API',
        'intro' => 'Un token può ciò che nominano le sue capacità e mai più del tuo account.',
        'name' => 'Nome',
        'abilities' => 'Capacità',
        'lifetime' => 'Durata',
        'days' => '{1} :count giorno|[2,*] :count giorni',
        'no_expiry' => 'Senza scadenza',
        'mint' => 'Crea token',
        'copy_now' => 'Copia il token ora. Viene mostrato solo questa volta.',
        // The label of the field that shows a new token once. WireKit names the copy button beside it after it.
        'new_token' => 'Token',
        'minted' => 'Il token è stato creato.',
        'revoke' => 'Revoca',
        'revoke_named' => 'Revoca :name',
        'confirm_revoke' => 'Revocare questo token? Le richieste che lo usano smetteranno di funzionare.',
        'revoked' => 'Il token è stato revocato.',
        'not_revoked' => 'Nessun token è stato revocato.',
        'rotate' => 'Rinnova',
        'rotate_named' => 'Rinnova :name',
        'confirm_rotate' => 'Sostituire questo token con uno nuovo? Funziona ancora per :overlap, così ciò che lo usa può passare al nuovo.',
        'overlap' => 'Dopo il rinnovo, il vecchio token funziona ancora per',
        'hours' => '{1} :count ora|[2,*] :count ore',
        'rotated' => 'Un nuovo token lo sostituisce. Quello vecchio funziona fino all’ora indicata accanto.',
        'not_rotated' => 'Nessun token è stato sostituito.',
        'replaced_until' => 'Sostituito, funziona fino al :date',
        'none' => 'Ancora nessun token.',
        'prefix' => 'Prefisso',
        'expires' => 'Scade',
        'last_used' => 'Ultimo uso',
        'recent' => 'Richieste recenti',
        'never' => 'Mai',
        'not_recorded' => 'Non registrato',
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
        'quickstart_token_limit' => 'Ogni token fa fino a :token richieste al minuto.',
        'quickstart_user_limit' => 'Un account fa fino a :user richieste al minuto con tutti i suoi token.',
        // The parts of the screen, each with its name and one sentence on what it is for, as the WireKit view heads the
        // card each part stands in. The quickstart takes its name from `quickstart` above.
        'parts' => [
            'mint' => 'Nuovo token',
            'mint_description' => 'Un token con le capacità, la durata e le reti che scegli.',
            'tokens' => 'Token',
            'tokens_description' => 'Ogni token, il più recente per primo, con le sue capacità, la sua scadenza e il suo ultimo uso.',
            'quickstart_description' => 'Dove un programma invia le sue richieste con un token, e quante ne può inviare.',
        ],
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
            'quickstart_user_limit' => 'I token di questo titolare fanno fino a :user richieste al minuto insieme.',
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
            'holder' => 'Una delle capacità non è offerta per questo titolare.',
            'malformed' => 'Una delle capacità non è offerta.',
            'not_tokenable' => 'Per questo account non si possono creare token.',
            'not_allowed' => 'Non puoi gestire i token di questo titolare.',
            'password_not_confirmed' => 'Conferma la password prima di creare un token.',
            'too_fast' => 'I token vengono creati troppo in fretta. Riprova tra un momento.',
            'limit_reached' => 'Hai tutti i token consentiti. Revocane prima uno.',
            'lifetime' => 'Questa durata non è offerta.',
            'overlap' => 'Scegli dalla lista per quanto tempo il vecchio token funziona ancora.',
            'name' => 'Dai al token un nome di al massimo 255 caratteri.',
            'network' => 'Un token non può essere limitato a «:network»: non è un indirizzo né una rete.',
            'too_many_networks' => 'Un token può essere limitato al massimo a :limit indirizzi e reti.',
            'refused_by_holder' => ':refusal',
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
