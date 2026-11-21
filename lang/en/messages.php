<?php

declare(strict_types=1);

return [
    // The detail of the problem document a throttled request is answered with.
    'throttled' => 'Too many requests. Another one can be sent once the time in the Retry-After header has passed.',
    // The self-service screen of a user's tokens.
    'screen' => [
        'heading' => 'API tokens',
        'intro' => 'A token can do what its abilities name and never more than your account.',
        'name' => 'Name',
        'abilities' => 'Abilities',
        'lifetime' => 'Lifetime',
        'days' => ':count day|:count days',
        'no_expiry' => 'No expiry',
        'mint' => 'Create token',
        'copy_now' => 'Copy the token now. It is shown only this once.',
        // The label of the field that shows a new token once. WireKit names the copy button beside it after it.
        'new_token' => 'Token',
        'minted' => 'The token was created.',
        'revoke' => 'Revoke',
        'revoke_named' => 'Revoke :name',
        'confirm_revoke' => 'Revoke this token? Requests that use it stop working.',
        'revoked' => 'The token was revoked.',
        'not_revoked' => 'No token was revoked.',
        'rotate' => 'Rotate',
        'rotate_named' => 'Rotate :name',
        'confirm_rotate' => 'Replace this token with a new one? It keeps working for :overlap, so whatever uses it has time to take the new one.',
        'overlap' => 'After a rotation, the old token keeps working for',
        'hours' => ':count hour|:count hours',
        'rotated' => 'A new token replaces it. The old one keeps working until the time shown beside it.',
        'not_rotated' => 'No token was replaced.',
        'replaced_until' => 'Replaced, works until :date',
        'none' => 'No tokens yet.',
        'prefix' => 'Prefix',
        'expires' => 'Expires',
        'last_used' => 'Last used',
        'recent' => 'Recent requests',
        'never' => 'Never',
        'not_recorded' => 'Not recorded',
        'networks' => 'Networks',
        'networks_hint' => 'Optional. One address or network a line, such as 203.0.113.7 or 10.0.0.0/8. The token then works from these alone.',
        'only_from' => 'Only from :networks',
        // How a date and a moment read on the screen and in the mails, in the reader's language, the
        // month as a word so that no reader takes the day for the month. German writes "Uhr" after a
        // clock time.
        'formats' => [
            'date' => 'll',
            'moment' => 'll LT',
        ],
        'quickstart' => 'Quickstart',
        'quickstart_endpoint' => 'Send a request with the token to',
        'quickstart_limits' => 'Each token makes up to :token requests a minute, and an account up to :user over all of its tokens.',
        'quickstart_token_limit' => 'Each token makes up to :token requests a minute.',
        'quickstart_user_limit' => 'An account makes up to :user requests a minute over all of its tokens.',
        // The parts of the screen, each with its name and one sentence on what it is for, as the WireKit view heads the
        // card each part stands in. The quickstart takes its name from `quickstart` above.
        'parts' => [
            'mint' => 'New token',
            'mint_description' => 'A token with the abilities, the lifetime and the networks you choose.',
            'tokens' => 'Tokens',
            'tokens_description' => 'Every token, the newest first, with its abilities, its expiry and its last use.',
            'quickstart_description' => 'Where a program sends its requests with a token, and how many it may send.',
        ],
        // Asking for the password on the screen itself, where it was not confirmed a moment ago.
        'confirm_intro' => 'Confirm your password before you create a token.',
        'password' => 'Password',
        'confirm' => 'Confirm password',
        'confirmed' => 'Your password is confirmed.',
        'not_confirmed' => 'That is not your password.',
        'confirm_too_fast' => 'Too many wrong passwords. Try again in a minute.',
        // The lines that speak of the account, for a screen that manages the tokens of another holder,
        // a workspace for instance. An application names its holder by publishing these lines.
        'holder' => [
            'intro' => 'A token can do what its abilities name and never more than the holder it belongs to.',
            'none' => 'No tokens yet.',
            'quickstart_limits' => 'Each token makes up to :token requests a minute, and the tokens of this holder up to :user together.',
            'quickstart_user_limit' => 'The tokens of this holder make up to :user requests a minute together.',
            'refused' => [
                'not_tokenable' => 'Tokens cannot be created for this holder.',
                'limit_reached' => 'This holder has as many tokens as allowed. Revoke one first.',
            ],
        ],
        // Why a token was not minted, by the reason of the refusal.
        'refused' => [
            'none' => 'Choose at least one ability.',
            'wildcard' => 'A token cannot carry every ability at once.',
            'unknown' => 'One of the abilities is not offered.',
            'reserved' => 'One of the abilities is not offered here.',
            'holder' => 'One of the abilities is not offered for this holder.',
            'malformed' => 'One of the abilities is not offered.',
            'not_tokenable' => 'Tokens cannot be created for this account.',
            'not_allowed' => 'You may not manage the tokens of this holder.',
            'password_not_confirmed' => 'Confirm your password before you create a token.',
            'too_fast' => 'Tokens are being created too fast. Try again in a moment.',
            'limit_reached' => 'You hold as many tokens as allowed. Revoke one first.',
            'lifetime' => 'This lifetime is not offered.',
            'overlap' => 'Choose from the list how long the old token keeps working.',
            'name' => 'Give the token a name of at most 255 characters.',
            'network' => 'A token cannot be limited to ":network": it is no address and no network.',
            'too_many_networks' => 'A token can be limited to at most :limit addresses and networks.',
        ],
    ],
    // The mails to the holder of a token before it expires and once it has gone unused.
    'alerts' => [
        'prefix' => 'The token starts with :prefix.',
        'revoke' => 'If it is no longer needed, revoke it on the page with your API tokens.',
        'open' => 'Open your API tokens',
        'expiring' => [
            'subject' => 'Your API token ":name" expires on :date',
            'line' => 'Your API token ":name" expires on :date. Requests that use it stop working then, so create a new one before that if it is still needed.',
        ],
        'inactive' => [
            'subject_used' => 'Your API token ":name" has not been used since :date',
            'line_used' => 'Your API token ":name" was last used on :date.',
            'subject_never' => 'Your API token ":name" has never been used',
            'line_never' => 'Your API token ":name" was created on :date and has never been used.',
            'risk' => 'A token nobody uses still works for whoever holds it.',
        ],
    ],
];
