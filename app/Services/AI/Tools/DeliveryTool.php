<?php

namespace App\Services\AI\Tools;

use App\Models\Delivery;
use App\Services\AI\Contracts\ToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DeliveryTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_delivery_data';
    }

    public function description(): string
    {
        return 'Retrieve document or package delivery data — pending items, '
             . 'recent deliveries, or delivery statistics. Use when the user '
             . 'asks about packages, documents, or deliveries.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'status' => [
                    'type'        => 'string',
                    'enum'        => ['pending', 'stored', 'delivered', 'all'],
                    'description' => 'Filter by delivery status, or "all" for everything.',
                ],
                'limit' => [
                    'type'        => 'integer',
                    'description' => 'Max number of records to return (default 8, max 20).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $companyId = Auth::user()?->company_id;
        $status    = $arguments['status'] ?? 'all';
        $limit     = min((int) ($arguments['limit'] ?? 8), 20);

        $q = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $q->where('status', $status);
        }

        $items = $q->take($limit)->get();

        if ($items->isEmpty()) {
            return ['text' => 'No delivery records found' . ($status !== 'all' ? " with status '{$status}'" : '') . '.'];
        }

        $lines = ['Recent deliveries:'];
        foreach ($items as $d) {
            $lines[] = sprintf(
                '  [ID:%d] %s | Type:%s | Dir:%s | Status:%s | %s',
                $d->delivery_id,
                $d->item_name ?? '—',
                $d->type      ?? '—',
                $d->direction ?? '—',
                $d->status    ?? '—',
                optional($d->created_at)->format('d M Y') ?? '—'
            );
        }

        $pending = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('status', 'pending')->count();
        $stored  = Delivery::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('status', 'stored')->count();
        $lines[] = "Totals — pending:{$pending} stored:{$stored}";

        return ['text' => implode("\n", $lines)];
    }
}
