<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WazuhSecurityMonitor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Livewire's update endpoint sends signed, CSRF-protected snapshots that
        // legitimately contain PHP class names, SQL-like keywords in labels, and
        // other strings that trigger false positives. The snapshot is signed and
        // cannot be forged, so scanning it provides no security value.
        if ($request->is('livewire/update') || $request->is('livewire/*')) {
            return $next($request);
        }

        $input    = $request->all();
        $ip       = $request->ip() ?? '127.0.0.1';
        $location = $request->path();

        // 1. SQL Injection
        // Require a quote character before the keyword to avoid matching
        // plain-English phrases like "select a room" or "update your profile".
        $sqliPattern = "/['\"`]\s*(?:union[\s\/*]+select|select[\s\/*].+?from|insert[\s\/*]+into|drop[\s\/*]+table|delete[\s\/*]+from|update[\s\/*]+\S+[\s\/*]+set)"
                     . "|\bor\b\s+['\"]?\d+['\"]?\s*=\s*['\"]?\d+"
                     . "|--\s*$/im";
        if ($this->detectPattern($input, $sqliPattern)) {
            Log::info("level 12 srcip: {$ip} location: /{$location} -> SQLI_DETECTED");
            abort(403, 'Forbidden: Malicious activity detected.');
        }

        // 2. XSS
        // Added \s* around = to catch obfuscated variants; kept <script strict with [\s>].
        $xssPattern = '/(<script[\s>]|javascript\s*:|onerror\s*=|onload\s*=|eval\s*\(|document\.cookie)/i';
        if ($this->detectPattern($input, $xssPattern)) {
            Log::info("level 12 srcip: {$ip} location: /{$location} -> XSS_DETECTED");
            abort(403, 'Forbidden: Malicious activity detected.');
        }

        // 3. Command Injection
        // Require a real shell separator (|, ;, &, `) immediately before the command.
        // 'php' and 'id' are excluded: 'php' appears in every class name; 'id' appears
        // in HTML attributes and JSON keys constantly.
        $cmdPattern = '/(\||;|&|`)\s*\b(ls|cat|whoami|pwd|wget|curl|echo|ping|bash|sh)\b/i';
        if ($this->detectPattern($input, $cmdPattern)) {
            Log::info("level 12 srcip: {$ip} location: /{$location} -> COMMAND_INJECTION");
            abort(403, 'Forbidden: Malicious activity detected.');
        }

        // 4. File Upload Attacks
        foreach ($request->allFiles() as $file) {
            $files = is_array($file) ? $file : [$file];
            foreach ($files as $f) {
                if ($this->isFileMalicious($f)) {
                    Log::info("level 12 srcip: {$ip} location: /{$location} -> FILE_UPLOAD_ATTACK");
                    abort(403, 'Forbidden: Malicious file upload detected.');
                }
            }
        }

        return $next($request);
    }

    private function detectPattern(array $input, string $pattern): bool
    {
        $detected = false;
        array_walk_recursive($input, function ($item) use ($pattern, &$detected) {
            if (is_string($item) && preg_match($pattern, $item)) {
                $detected = true;
            }
        });
        return $detected;
    }

    private function isFileMalicious($file): bool
    {
        if (!$file instanceof \Illuminate\Http\UploadedFile) {
            return false;
        }

        $maliciousExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'sh', 'exe', 'bat', 'cmd', 'cgi', 'pl'];

        return in_array(strtolower($file->getClientOriginalExtension()), $maliciousExtensions);
    }
}
