<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class LogFailedLogin
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Failed $event): void
    {
        $ip = $this->request->ip() ?? '127.0.0.1';
        $location = $this->request->path();
        
        $key = 'failed_login_attempts_' . $ip;
        
        $attempts = Cache::get($key, 0);
        $attempts++;
        Cache::put($key, $attempts, now()->addMinutes(5));

        if ($attempts >= 5) {
            Log::info("level 12 srcip: {$ip} location: /{$location} -> BRUTE_FORCE_DETECTED");
            \App\Models\WazuhAlert::create(['rule_level' => 12, 'description' => 'BRUTE_FORCE_DETECTED', 'agent_name' => 'laravel-app', 'raw_log' => "srcip: $ip, location: $location"]);
        } else {
            Log::info("level 5 srcip: {$ip} location: /{$location} -> LOGIN_FAILED");
            \App\Models\WazuhAlert::create(['rule_level' => 5, 'description' => 'LOGIN_FAILED', 'agent_name' => 'laravel-app', 'raw_log' => "srcip: $ip, location: $location"]);
        }
    }
}
