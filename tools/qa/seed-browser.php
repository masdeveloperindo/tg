<?php

use Symfony\Component\Process\Process;

// Invoked only by the guarded local QA matrix. Existing seed.php checks actual MySQL tg_test.
$cases = ['feedback' => 'seed.php', 'layout' => 'seed.php', 'products' => 'seed.php', 'catalog' => 'seed-catalog.php', 'checkout' => 'seed-checkout.php', 'fulfillment' => 'seed-fulfillment.php', 'licenses' => 'seed-license-operations.php', 'rebates' => 'seed-rebates.php', 'content' => 'seed-content.php'];
$name = $argv[1] ?? '';
if (! isset($cases[$name])) {
    exit(2);
}
require dirname(__DIR__, 2).'/tests/Browser/'.$cases[$name];
$image = imagecreatetruecolor(240, 120);
imagefill($image, 0, 0, imagecolorallocate($image, 20, 66, 48));
imagestring($image, 5, 40, 50, 'SYNTHETIC', imagecolorallocate($image, 220, 255, 100));
imagepng($image, storage_path('framework/testing/qa-image.png'));
imagedestroy($image);

if ($name === 'feedback') {
    $render = new Process([PHP_BINARY, base_path('tests/Browser/render-feedback.php')], base_path());
    $render->mustRun();
    file_put_contents(storage_path('framework/testing/qa-feedback.html'), $render->getOutput());
}
