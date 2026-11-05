<?php

return [
    'display_timezone' => 'Asia/Jakarta',
    'telegram_username' => env('TG_TELEGRAM_USERNAME'),
    // Sales remain closed until the relevant EA/indicator integration PoC passes.
    'license_sales_enabled' => false,
    'indicator_sales_enabled' => false,
    'license_private_key_path' => env('TG_LICENSE_ED25519_PRIVATE_KEY_PATH'),
];
