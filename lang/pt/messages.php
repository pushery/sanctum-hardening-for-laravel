<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Demasiados pedidos. Pode ser enviado outro quando tiver passado o tempo do cabeçalho Retry-After.',
    // The self-service screen of a user's tokens.
    'screen' => [
        'heading' => 'Tokens de API',
        'intro' => 'Um token pode o que as suas capacidades nomeiam e nunca mais do que a tua conta.',
        'name' => 'Nome',
        'abilities' => 'Capacidades',
        'lifetime' => 'Duração',
        'days' => ':count dia|:count dias',
        'no_expiry' => 'Sem expiração',
        'mint' => 'Criar token',
        'copy_now' => 'Copia o token agora. Só é mostrado desta vez.',
        // The label of the field that shows a new token once. WireKit names the copy button beside it after it.
        'new_token' => 'Token',
        'minted' => 'O token foi criado.',
        'revoke' => 'Revogar',
        'revoke_named' => 'Revogar :name',
        'confirm_revoke' => 'Revogar este token? Os pedidos que o usam deixam de funcionar.',
        'revoked' => 'O token foi revogado.',
        'not_revoked' => 'Nenhum token foi revogado.',
        'rotate' => 'Renovar',
        'rotate_named' => 'Renovar :name',
        'confirm_rotate' => 'Substituir este token por um novo? Continua a funcionar durante :overlap, para que o que o usa possa passar para o novo.',
        'overlap' => 'Depois de renovado, o token antigo continua a funcionar durante',
        'hours' => ':count hora|:count horas',
        'rotated' => 'Um novo token substitui-o. O antigo funciona até à hora indicada ao lado.',
        'not_rotated' => 'Nenhum token foi substituído.',
        'replaced_until' => 'Substituído, funciona até :date',
        'none' => 'Ainda não há tokens.',
        'prefix' => 'Prefixo',
        'expires' => 'Expira',
        'last_used' => 'Último uso',
        'recent' => 'Pedidos recentes',
        'never' => 'Nunca',
        'not_recorded' => 'Não registado',
        'networks' => 'Redes',
        'networks_hint' => 'Opcional. Um endereço ou uma rede por linha, por exemplo 203.0.113.7 ou 10.0.0.0/8. O token passa então a funcionar só a partir daí.',
        'only_from' => 'Só a partir de :networks',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'll',
            'moment' => 'll LT',
        ],
        'quickstart' => 'Início rápido',
        'quickstart_endpoint' => 'Envia um pedido com o token para',
        'quickstart_limits' => 'Cada token faz até :token pedidos por minuto, e uma conta até :user com todos os seus tokens.',
        'quickstart_token_limit' => 'Cada token faz até :token pedidos por minuto.',
        'quickstart_user_limit' => 'Uma conta faz até :user pedidos por minuto com todos os seus tokens.',
        // The parts of the screen, each with its name and one sentence on what it is for, as the WireKit view heads the
        // card each part stands in. The quickstart takes its name from `quickstart` above.
        'parts' => [
            'mint' => 'Novo token',
            'mint_description' => 'Um token com as capacidades, a duração e as redes que escolheres.',
            'tokens' => 'Tokens',
            'tokens_description' => 'Cada token, o mais recente primeiro, com as suas capacidades, a sua validade e o seu último uso.',
            'quickstart_description' => 'Para onde um programa envia os seus pedidos com um token, e quantos pode enviar.',
        ],
        // Asking for the password on the screen itself, where it was not confirmed a moment ago.
        'confirm_intro' => 'Confirma a tua palavra-passe antes de criar um token.',
        'password' => 'Palavra-passe',
        'confirm' => 'Confirmar palavra-passe',
        'confirmed' => 'A tua palavra-passe está confirmada.',
        'not_confirmed' => 'Essa não é a tua palavra-passe.',
        'confirm_too_fast' => 'Demasiadas palavras-passe erradas. Tenta de novo daqui a um minuto.',
        // The lines that speak of the account, for a screen that manages the tokens of another holder,
        // a workspace for instance. An application names its holder by publishing these lines.
        'holder' => [
            'intro' => 'Um token pode o que as suas capacidades nomeiam e nunca mais do que o titular a quem pertence.',
            'none' => 'Ainda não há tokens.',
            'quickstart_limits' => 'Cada token faz até :token pedidos por minuto, e os tokens deste titular até :user em conjunto.',
            'quickstart_user_limit' => 'Os tokens deste titular fazem até :user pedidos por minuto em conjunto.',
            'refused' => [
                'not_tokenable' => 'Não é possível criar tokens para este titular.',
                'limit_reached' => 'Este titular tem tantos tokens quantos os permitidos. Revoga um primeiro.',
            ],
        ],
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Escolhe pelo menos uma capacidade.',
            'wildcard' => 'Um token não pode ter todas as capacidades ao mesmo tempo.',
            'unknown' => 'Uma das capacidades não é oferecida.',
            'reserved' => 'Uma das capacidades não é oferecida aqui.',
            'holder' => 'Uma das capacidades não é oferecida para este titular.',
            'malformed' => 'Uma das capacidades não é oferecida.',
            'not_tokenable' => 'Não é possível criar tokens para esta conta.',
            'not_allowed' => 'Não podes gerir os tokens deste titular.',
            'password_not_confirmed' => 'Confirma a tua palavra-passe antes de criar um token.',
            'too_fast' => 'Os tokens estão a ser criados depressa demais. Tenta de novo daqui a pouco.',
            'limit_reached' => 'Tens tantos tokens quantos os permitidos. Revoga um primeiro.',
            'lifetime' => 'Esta duração não é oferecida.',
            'overlap' => 'Escolhe da lista durante quanto tempo o token antigo continua a funcionar.',
            'name' => 'Dá ao token um nome com 255 caracteres no máximo.',
            'network' => 'Um token não pode ser limitado a «:network»: não é um endereço nem uma rede.',
            'too_many_networks' => 'Um token pode ser limitado a :limit endereços e redes no máximo.',
        ],
    ],
    // The mails to the holder of a token before it expires and once it has gone unused.
    'alerts' => [
        'prefix' => 'O token começa por :prefix.',
        'revoke' => 'Se já não precisas dele, revoga-o na página dos teus tokens de API.',
        'open' => 'Abrir os teus tokens de API',
        'expiring' => [
            'subject' => 'O teu token de API «:name» expira a :date',
            'line' => 'O teu token de API «:name» expira a :date. Os pedidos que o usam deixam então de funcionar, por isso cria um novo antes, se ainda precisares dele.',
        ],
        'inactive' => [
            'subject_used' => 'O teu token de API «:name» não é usado desde :date',
            'line_used' => 'O teu token de API «:name» foi usado pela última vez a :date.',
            'subject_never' => 'O teu token de API «:name» nunca foi usado',
            'line_never' => 'O teu token de API «:name» foi criado a :date e nunca foi usado.',
            'risk' => 'Um token que ninguém usa continua a funcionar para quem o tiver.',
        ],
    ],
];
