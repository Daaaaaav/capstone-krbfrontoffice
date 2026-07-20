<?php

namespace App\Services\AI\Context;

use App\Models\Guestbook;
use App\Services\AI\Contracts\ContextProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Loads guestbook context: today's visitors and recent entry summary.
 * Called only when the ContextRouter detects a guestbook/visitor query.
 */
class GuestbookContextProvider implements ContextProviderInterface
{
    private string $tz = 'Asia/Jakarta';

    public function name(): string
    {
        return 'guestbook';
    }

    public function load(?int $companyId, array $params = []): string
    {
        $now   = Carbon::now($this->tz);
        $today = $params['date'] ?? $now->toDateString();

        $cacheKey = "ctx_guestbook_{$companyId}_{$today}";
        return Cache::remember($cacheKey, 60, fn() => $this->build($companyId, $now, $today));
    }

    private function build(?int $companyId, Carbon $now, string $today): string
    {
        $q = Guestbook::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $todayCount = (clone $q)->whereDate('date', $today)->count();
        $weekCount  = (clone $q)->where('date', '>=', $now->copy()->startOfWeek()->toDateString())->count();

        $todayEntries = (clone $q)->whereDate('date', $today)
            ->orderByDesc('jam_in')->take(8)->get()
            ->map(fn($g) => sprintf(
                '  %s | Purpose:%s | In:%s Out:%s',
                $g->name ?? '—', $g->keperluan ?? '—',
                optional($g->jam_in)->format('H:i') ?? '—',
                optional($g->jam_out)->format('H:i') ?? '(in-house)'
            ))->join("\n") ?: '  (none today)';

        return <<<BLOCK
        GUESTBOOK — {$today}:
        Today's visitor count: {$todayCount} | This week: {$weekCount}
        {$todayEntries}
        BLOCK;
    }
}
