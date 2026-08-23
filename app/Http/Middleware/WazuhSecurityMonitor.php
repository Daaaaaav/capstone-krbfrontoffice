<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WazuhSecurityMonitor
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('livewire/update') || $request->is('livewire/*') || $request->is('health')) {
            return $next($request);
        }

        $input    = $request->all();
        $ip       = $request->ip() ?? '127.0.0.1';
        $location = $request->path();

        $sqliPattern = "/['\"`]\s*(?:union[\s\/*]+select|select[\s\/*].+?from|insert[\s\/*]+into|drop[\s\/*]+table|delete[\s\/*]+from|update[\s\/*]+\S+[\s\/*]+set)"
                     . "|\bor\b\s+['\"]?\d+['\"]?\s*=\s*['\"]?\d+"
                     . "|--\s*$/im";
        if ($this->detectPattern($input, $sqliPattern)) {
            // Write to the dedicated 'security' channel so the Wazuh agent
            // logcollector picks it up from security-YYYY-MM-DD.log.
            // The old Log::info() wrote to laravel.log (wrong file) and
            // therefore never reached the Wazuh pipeline.
            Log::channel('security')->warning('SQLI_DETECTED', [
                'ip'       => $ip,
                'route'    => $location,
                'method'   => $request->method(),
            ]);
            abort(403, 'Forbidden: Malicious activity detected.');
        }

        $xssPattern = '/(<script[\s>]|javascript\s*:|onerror\s*=|onload\s*=|eval\s*\(|document\.cookie)/i';
        if ($this->detectPattern($input, $xssPattern)) {
            Log::channel('security')->warning('XSS_DETECTED', [
                'ip'     => $ip,
                'route'  => $location,
                'method' => $request->method(),
            ]);
            abort(403, 'Forbidden: Malicious activity detected.');
        }

        $cmdPattern = '/(\||;|&|`)\s*\b(ls|cat|whoami|pwd|wget|curl|echo|ping|bash|sh)\b/i';
        if ($this->detectPattern($input, $cmdPattern)) {
            Log::channel('security')->warning('COMMAND_INJECTION_DETECTED', [
                'ip'     => $ip,
                'route'  => $location,
                'method' => $request->method(),
            ]);
            abort(403, 'Forbidden: Malicious activity detected.');
        }

        foreach ($request->allFiles() as $file) {
            $files = is_array($file) ? $file : [$file];
            foreach ($files as $f) {
                if ($this->isFileMalicious($f)) {
                    Log::channel('security')->warning('WEB_ATTACK_DETECTED', [
                        'ip'     => $ip,
                        'route'  => $location,
                        'method' => $request->method(),
                        'detail' => 'malicious_file_upload',
                    ]);
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
