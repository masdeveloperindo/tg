<?php

return [
    'health_token' => env('TG_HEALTH_TOKEN'),
    'broker_referral_hosts' => array_values(array_filter(array_map(fn ($host) => strtolower(trim($host)), explode(',', (string) env('TG_BROKER_REFERRAL_HOSTS', ''))))),
    'display_timezone' => 'Asia/Jakarta',
    'telegram_username' => env('TG_TELEGRAM_USERNAME'),
    // Sales remain closed until the relevant EA/indicator integration PoC passes.
    'license_sales_enabled' => false,
    'indicator_sales_enabled' => false,
    'license_signing_kid' => env('TG_LICENSE_ED25519_KID'),
    'license_private_key_path' => env('TG_LICENSE_ED25519_PRIVATE_KEY_PATH'),
];
