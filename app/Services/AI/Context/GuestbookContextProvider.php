<?php

namespace App\Services\AI\Context;

use App\Models\Guestbook;
use App\Services\AI\Contracts\ContextProviderInterface;
use App\Services\AI\Enums\ContextDetailLevel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class GuestbookContextProvider implements ContextProviderInterface
{
    private string $tz = 'Asia/Jakarta';

    public function name(): string
    {
        return 'guestbook';
    }

    public function load(?int $companyId, array $params = [], ?ContextDetailLevel $detailLevel = null): string
    {
        if (! $companyId) {
            return '(no guestbook data available: company not specified)';
        }

        $now   = Carbon::now($this->tz);
        $today = $params['date'] ?? $now->toDateString();
        $level = $detailLevel ?? ContextDetailLevel::DETAILED;

        $cacheKey = "ctx_guestbook_{$companyId}_{$today}_{$level->value}";
        return Cache::remember($cacheKey, 60, fn() => $this->build($companyId, $now, $today, $level));
    }

    private function build(int $companyId, Carbon $now, string $today, ContextDetailLevel $level): string
    {
        return match ($level) {
            ContextDetailLevel::MINIMAL => $this->buildMinimal($companyId, $today),
            ContextDetailLevel::NORMAL => $this->buildNormal($companyId, $now, $today),
            ContextDetailLevel::BOOKING => $this->buildNormal($companyId, $now, $today),
            ContextDetailLevel::DETAILED => $this->buildDetailed($companyId, $now, $today),
        };
    }

    private function buildMinimal(int $companyId, string $today): string
    {
        $q = Guestbook::where('company_id', $companyId);
        $todayCount = (clone $q)->whereDate('date', $today)->count();

        return "GUESTBOOK: {$todayCount} today";
    }

    private function buildNormal(int $companyId, Carbon $now, string $today): string
    {
        $q = Guestbook::where('company_id', $companyId);
        $todayCount = (clone $q)->whereDate('date', $today)->count();
        $weekCount  = (clone $q)->where('date', '>=', $now->copy()->startOfWeek()->toDateString())->count();

        return "GUESTBOOK ({$today}): {$todayCount} today | {$weekCount} this week";
    }

    private function buildDetailed(int $companyId, Carbon $now, string $today): string
    {
        $q = Guestbook::where('company_id', $companyId);

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
