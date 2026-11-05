<?php

use Tests\Support\CheckoutRecords;

// Existing seed verifies e2e + actual MySQL8.4 tg_test before reset. All values below are synthetic.
require __DIR__.'/seed.php';
CheckoutRecords::configure();
CheckoutRecords::product(['slug' => 'synthetic-checkout-ea', 'description' => '<p>Synthetic checkout fixture. No real payments.</p>']);
echo "Synthetic checkout fixtures created only in tg_test.\n";
