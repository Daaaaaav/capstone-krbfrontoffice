<?php

$directories = [
    __DIR__ . '/app/Livewire/Pages/Manager',
    __DIR__ . '/app/Livewire/Pages/Receptionist'
];

$snippet = "\n        \App\Services\SecurityMonitoringService::logFormSubmit(class_basename(\$this), method_exists(\$this, 'all') ? \$this->all() : []);\n";

foreach ($directories as $dir) {
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $originalContent = $content;

        $pattern = '/(public\s+function\s+(?:save|submit|create|store)[a-zA-Z0-9_]*\s*\([^\)]*\)(?:\s*:\s*[a-zA-Z0-9_\\\\]+)?\s*\{)(?!\s*\\\\App\\\\Services\\\\SecurityMonitoringService::logFormSubmit)/si';

        $replaced = preg_replace_callback($pattern, function($matches) use ($snippet) {
            return $matches[1] . $snippet;
        }, $content);

        if ($replaced !== null && $replaced !== $originalContent) {
            file_put_contents($file, $replaced);
            echo "Updated: " . basename($file) . "\n";
        } elseif ($replaced === null) {
            echo "Regex error on " . basename($file) . ": " . preg_last_error_msg() . "\n";
        }
    }
}

echo "Done.\n";
