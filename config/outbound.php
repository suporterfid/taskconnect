<?php

$appEnv = env('APP_ENV', 'production');

return [
    'connect_timeout' => (int) env('OUTBOUND_CONNECT_TIMEOUT', 5),
    'total_timeout' => (int) env('OUTBOUND_TOTAL_TIMEOUT', 15),
    'response_body_limit' => (int) env('OUTBOUND_RESPONSE_BODY_LIMIT', 65536),
    'endpoint_test_response_body_limit' => (int) env('OUTBOUND_ENDPOINT_TEST_RESPONSE_BODY_LIMIT', 8192),
    'request_body_limit' => (int) env('OUTBOUND_REQUEST_BODY_LIMIT', 65536),
    'redirect_limit' => (int) env('OUTBOUND_REDIRECT_LIMIT', 3),
    'allowed_ports' => array_map('intval', array_filter(explode(',', env('OUTBOUND_ALLOWED_PORTS', '80,443')))),
    'allow_http' => filter_var(env('OUTBOUND_ALLOW_HTTP', false), FILTER_VALIDATE_BOOL),
    'user_agent' => env('OUTBOUND_USER_AGENT', 'OpenHttpScheduler/1.1'),
    'platform_allow_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', env('OUTBOUND_PLATFORM_ALLOW_HOSTS', ''))
    ))),
    'testing_allow_hosts' => in_array($appEnv, ['local', 'testing'], true)
        ? array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', env('OUTBOUND_TESTING_ALLOW_HOSTS', 'receiver'))
        )))
        : [],
    'metadata_hosts' => [
        'metadata.google.internal',
        'metadata.goog',
        'metadata',
    ],
    'metadata_ips' => [
        '169.254.169.254',
        '100.100.100.200',
        'fd00:ec2::254',
    ],
];
