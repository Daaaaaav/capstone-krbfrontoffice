<?php
// Quick validation script - run with: php artisan eval
$w = config('services.wazuh');
echo 'url: '      . ($w['url']      ?? 'NULL')         . "\n";
echo 'username: ' . ($w['username'] ?? 'NULL')         . "\n";
echo 'pass_set: ' . (isset($w['password']) && $w['password'] !== '' ? 'YES' : 'EMPTY') . "\n";
echo 'indexer_ip (should be GONE): ' . ($w['indexer_ip'] ?? 'GONE-CORRECT') . "\n";