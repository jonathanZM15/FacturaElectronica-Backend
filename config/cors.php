<?php

$frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');
$parts = parse_url($frontend);
$origin = isset($parts['scheme'], $parts['host'])
    ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : '';

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        [$origin], array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))
    )))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'Idempotency-Key'],
    'exposed_headers' => ['Content-Disposition', 'Retry-After'],
    'max_age' => 600,
    'supports_credentials' => false,
];
