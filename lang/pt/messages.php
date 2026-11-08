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
        'minted' => 'O token foi criado.',
        'revoke' => 'Revogar',
        'revoke_named' => 'Revogar :name',
        'confirm_revoke' => 'Revogar este token? Os pedidos que o usam deixam de funcionar.',
        'revoked' => 'O token foi revogado.',
        'not_revoked' => 'Nenhum token foi revogado.',
        'none' => 'Ainda não há tokens.',
        'prefix' => 'Prefixo',
        'expires' => 'Expira',
        'last_used' => 'Último uso',
        'never' => 'Nunca',
        // How a date and a moment read on the screen, in the reader's language. German writes
        // "Uhr" after a clock time.
        'formats' => [
            'date' => 'L',
            'moment' => 'L LT',
        ],
        'quickstart' => 'Início rápido',
        'quickstart_endpoint' => 'Envia um pedido com o token para',
        'quickstart_limits' => 'Cada token faz até :token pedidos por minuto, e uma conta até :user com todos os seus tokens.',
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Escolhe pelo menos uma capacidade.',
            'wildcard' => 'Um token não pode ter todas as capacidades ao mesmo tempo.',
            'unknown' => 'Uma das capacidades não é oferecida.',
            'reserved' => 'Uma das capacidades não é oferecida aqui.',
            'malformed' => 'Uma das capacidades não é oferecida.',
            'not_tokenable' => 'Não é possível criar tokens para esta conta.',
            'password_not_confirmed' => 'Confirma a tua palavra-passe antes de criar um token.',
            'too_fast' => 'Os tokens estão a ser criados depressa demais. Tenta de novo daqui a pouco.',
            'limit_reached' => 'Tens tantos tokens quantos os permitidos. Revoga um primeiro.',
            'lifetime' => 'Esta duração não é oferecida.',
            'name' => 'Dá ao token um nome com 255 caracteres no máximo.',
        ],
    ],
];
