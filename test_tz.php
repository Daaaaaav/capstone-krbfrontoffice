<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$start_at = \Carbon\Carbon::parse('2026-07-15 10:00:00');
$parsed = \Carbon\Carbon::parse($start_at, 'Asia/Jakarta');

echo "original: " . $start_at->toDateTimeString() . "\n";
echo "parsed: " . $parsed->toDateTimeString() . "\n";
