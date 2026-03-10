<?php

$allowedOrigins = trim((string) env('CORS_ALLOWED_ORIGINS', '*'));

if ($allowedOrigins === '*') {
    $allowedOrigins = ['*'];
} else {
    $allowedOrigins = array_values(array_filter(array_map(
        static fn ($origin) => trim((string) $origin),
        explode(',', $allowedOrigins)
    )));
}

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    // Allow custom header X-Api-Key and standard headers
    'allowed_headers' => ['*'],

    // Useful when downloading files via XHR/fetch
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,
];

