<?php

use App\Models\Setting;
use Illuminate\Contracts\Console\Kernel;

$url = $argv[1] ?? '';

if ($url === '' || (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://'))) {
    fwrite(STDERR, 'URL tidak valid: '.$url.PHP_EOL);
    exit(1);
}

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Setting::updateOrCreate(
    ['key' => 'mobile_api_url'],
    ['value' => rtrim($url, '/')]
);

echo 'mobile_api_url = '.Setting::get('mobile_api_url').PHP_EOL;
