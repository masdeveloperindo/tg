<?php

// Render synthetic components for keyboard/upload browser tests; no route or DB mutation.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Blade;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
echo Blade::render(<<<'BLADE'
<x-ui.modal id="fixture" title="Confirmation fixture" description="Synthetic keyboard check" x-on:modal-cancel="$el.close()"><input aria-label="Fixture input"><button type="button">Cancel fixture</button></x-ui.modal>
<x-ui.upload-status id="upload-fixture"><input type="file" aria-label="Synthetic file"></x-ui.upload-status>
<x-ui.toast message="Synthetic success" id="toast-fixture" />
BLADE);
