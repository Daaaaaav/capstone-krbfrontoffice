<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Contracts\ToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Tool: get_announcements
 *
 * Returns upcoming events, facility notices, or known scheduled closures
 * relevant to the current company. This is a lightweight read-only tool
 * that queries any Announcement / Notice model if it exists, or returns
 * a graceful "no data" response if the model is not present.
 *
 * Designed to be extended: if your app has an Announcement model,
 * wire it in inside execute(). The interface is already declared.
 */
class AnnouncementTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_announcements';
    }

    public function description(): string
    {
        return 'Retrieve active facility announcements, notices, or upcoming closures. '
             . 'Use when the user asks about notices, announcements, or planned events.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'limit' => [
                    'type'        => 'integer',
                    'description' => 'Max number of announcements to return (default 5).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        // If the application has an Announcement model, query it here.
        // For now we return a graceful placeholder so the tool is always callable
        // without causing a class-not-found error.
        $announcementClass = 'App\\Models\\Announcement';

        if (! class_exists($announcementClass)) {
            return ['text' => 'No announcement system is currently configured.'];
        }

        $companyId = Auth::user()?->company_id;
        $limit     = min((int) ($arguments['limit'] ?? 5), 20);
        $today     = Carbon::today('Asia/Jakarta');

        try {
            $items = $announcementClass::when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->where('active', 1)
                ->where(function ($q) use ($today) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', $today);
                })
                ->orderByDesc('created_at')
                ->take($limit)
                ->get();

            if ($items->isEmpty()) {
                return ['text' => 'No active announcements.'];
            }

            $lines = ['Active announcements:'];
            foreach ($items as $a) {
                $lines[] = '  • ' . ($a->title ?? $a->message ?? '—');
            }
            return ['text' => implode("\n", $lines)];
        } catch (\Throwable) {
            return ['text' => 'Announcement data is currently unavailable.'];
        }
    }
}
