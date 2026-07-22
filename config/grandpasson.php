<?php

return [

    /*
    | GrandpaSSOn delegated auth (R8). Disabled by default until #26 scopes land.
    | Dual-mode: SPA/API-key auth remains; inbound GrandpaSSOn is opt-in.
    */

    'outbound_enabled' => filter_var(env('GRANDPASSON_OUTBOUND_ENABLED', false), FILTER_VALIDATE_BOOL),

    'inbound_enabled' => filter_var(env('GRANDPASSON_INBOUND_ENABLED', false), FILTER_VALIDATE_BOOL),

    'base_url' => env('GRANDPASSON_BASE_URL', ''),

    'client_id' => env('GRANDPASSON_CLIENT_ID', ''),

    'client_secret' => env('GRANDPASSON_CLIENT_SECRET', ''),

    'token_url' => env('GRANDPASSON_TOKEN_URL', env('GRANDPASSON_BASE_URL')
        ? rtrim((string) env('GRANDPASSON_BASE_URL'), '/').'/oauth/token'
        : ''),

    'introspect_url' => env('GRANDPASSON_INTROSPECT_URL', env('GRANDPASSON_BASE_URL')
        ? rtrim((string) env('GRANDPASSON_BASE_URL'), '/').'/oauth/introspect'
        : ''),

    'callback_scope' => env('GRANDPASSON_CALLBACK_SCOPE', 'tasks:callback'),

    'write_scope' => env('GRANDPASSON_WRITE_SCOPE', 'tasks:write'),

    'callback_hmac_secret' => env('TC_CALLBACK_HMAC_SECRET', ''),

    'callback_max_skew_seconds' => (int) env('TC_CALLBACK_MAX_SKEW_SECONDS', 300),

    'token_refresh_skew_seconds' => (int) env('GRANDPASSON_TOKEN_REFRESH_SKEW_SECONDS', 60),

];
