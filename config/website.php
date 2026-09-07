<?php

return [
    'platform_domain' => env('WEBSITE_PLATFORM_DOMAIN'),
    'dns_target' => env('WEBSITE_DNS_TARGET'),
    'dns_target_type' => env('WEBSITE_DNS_TARGET_TYPE', 'A'),
    'dns_ttl' => (int) env('WEBSITE_DNS_TTL', 300),
    'dns_www_target' => env('WEBSITE_DNS_WWW_TARGET', '@'),
    'preview_url_ttl_minutes' => (int) env('WEBSITE_PREVIEW_URL_TTL_MINUTES', 120),
    'resolver_cache_ttl_seconds' => (int) env('WEBSITE_RESOLVER_CACHE_TTL_SECONDS', 300),
    'public_rate_limit' => env('WEBSITE_PUBLIC_RATE_LIMIT', '60,1'),
    'domain_search_tlds' => array_filter(array_map('trim', explode(',', (string) env('WEBSITE_DOMAIN_SEARCH_TLDS', 'com,net,org,clinic,care,health,law,pro')))),
    'domain_markup_percent' => (float) env('WEBSITE_DOMAIN_MARKUP_PERCENT', 0),
    'domain_verification_retry_seconds' => (int) env('WEBSITE_DOMAIN_VERIFICATION_RETRY_SECONDS', 600),
    'domain_verification_max_attempts' => (int) env('WEBSITE_DOMAIN_VERIFICATION_MAX_ATTEMPTS', 12),
    'domain_pricing_cache_seconds' => (int) env('WEBSITE_DOMAIN_PRICING_CACHE_SECONDS', 21600),
    'domain_auto_renew_days_before' => (int) env('WEBSITE_DOMAIN_AUTO_RENEW_DAYS_BEFORE', 14),
    'domain_expiration_reminder_days' => array_values(array_filter(array_map(
        static fn ($value) => (int) trim((string) $value),
        explode(',', (string) env('WEBSITE_DOMAIN_EXPIRATION_REMINDER_DAYS', '30,14,7,3,1'))
    ), static fn (int $day) => $day > 0)),
    'ssl' => [
        'enabled' => (bool) env('WEBSITE_SSL_ENABLED', false),
        'driver' => env('WEBSITE_SSL_DRIVER', 'certbot'),
        'certbot_bin' => env('WEBSITE_SSL_CERTBOT_BIN', 'certbot'),
        'email' => env('WEBSITE_SSL_EMAIL'),
        'webroot' => env('WEBSITE_SSL_WEBROOT', '/var/www/certbot'),
        'live_path' => env('WEBSITE_SSL_LIVE_PATH', '/etc/letsencrypt/live'),
        'include_www' => (bool) env('WEBSITE_SSL_INCLUDE_WWW', true),
        'reload_command' => env('WEBSITE_SSL_RELOAD_COMMAND', 'systemctl reload nginx'),
        'command_timeout' => (int) env('WEBSITE_SSL_COMMAND_TIMEOUT', 180),
        'renew_days_before' => (int) env('WEBSITE_SSL_RENEW_DAYS_BEFORE', 30),
    ],
];
