<?php

namespace App\Services\AI\Context;

use App\Models\Delivery;
use App\Services\AI\Contracts\ContextProviderInterface;
use App\Services\AI\Enums\ContextDetailLevel;
use Illuminate\Support\Facades\Cache;

class DeliveryContextProvider implements ContextProviderInterface
{
    private string $tz = 'Asia/Jakarta';

    public function name(): string
    {
        return 'deliveries';
    }

    public function load(?int $companyId, array $params = [], ?ContextDetailLevel $detailLevel = null): string
    {
        $level = $detailLevel ?? ContextDetailLevel::DETAILED;
        $cacheKey = "ctx_deliveries_{$companyId}_{$level->value}";
        return Cache::remember($cacheKey, 90, fn() => $this->build($companyId, $level));
    }

    private function build(?int $companyId, ContextDetailLevel $level): string
    {
        return match ($level) {
            ContextDetailLevel::MINIMAL => $this->buildMinimal($companyId),
            ContextDetailLevel::NORMAL => $this->buildNormal($companyId),
            ContextDetailLevel::BOOKING => $this->buildNormal($companyId),
            ContextDetailLevel::DETAILED => $this->buildDetailed($companyId),
        };
    }

    private function buildMinimal(?int $companyId): string
    {
        $q = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId));
        $pending = (clone $q)->where('status', 'pending')->count();
        $stored  = (clone $q)->where('status', 'stored')->count();

        return "DELIVERIES: pending:{$pending} stored:{$stored}";
    }

    private function buildNormal(?int $companyId): string
    {
        return $this->buildMinimal($companyId);
    }

    private function buildDetailed(?int $companyId): string
    {
        $q = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId));

        $pending = (clone $q)->where('status', 'pending')->count();
        $stored  = (clone $q)->where('status', 'stored')->count();

        $recent = (clone $q)->orderByDesc('created_at')->take(8)->get()
            ->map(fn($d) => sprintf(
                '  [ID:%d] %s | %s | %s | %s | %s',
                $d->delivery_id, $d->item_name ?? '—',
                $d->type ?? '—', $d->direction ?? '—',
                $d->status ?? '—',
                optional($d->created_at)->format('d M Y') ?? '—'
            ))->join("\n") ?: '  (none)';

        return <<<BLOCK
        DELIVERIES — pending:{$pending} stored:{$stored}
        Recent (≤8):
        {$recent}
        BLOCK;
    }
}
