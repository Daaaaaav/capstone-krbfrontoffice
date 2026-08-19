<?php

namespace App\Services\AI\Tools;

use App\Models\Guestbook;
use App\Services\AI\Contracts\ToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class GuestbookTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_guestbook_data';
    }

    public function description(): string
    {
        return 'Retrieve visitor (guestbook) data — recent visitors, visitor counts, '
             . 'or visitor statistics for a given date or period. Use when the user '
             . 'asks about guests, visitors, or the guestbook.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'date'  => ['type' => 'string', 'description' => 'Specific date YYYY-MM-DD, or omit for today'],
                'limit' => ['type' => 'integer','description' => 'Max number of recent entries to return (default 8)'],
                'mode'  => [
                    'type'   => 'string',
                    'enum'   => ['recent', 'count', 'today'],
                    'description' => '"recent" for latest entries, "count" for statistics, "today" for today\'s visitors',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $companyId = Auth::user()?->company_id;
        if (! $companyId) {
            return ['text' => 'Guestbook data is currently unavailable.'];
        }

        $date      = $arguments['date']  ?? Carbon::today('Asia/Jakarta')->toDateString();
        $limit     = min((int) ($arguments['limit'] ?? 8), 20);
        $mode      = $arguments['mode']  ?? 'today';

        $q = Guestbook::where('company_id', $companyId);

        if ($mode === 'count') {
            $today     = Carbon::today('Asia/Jakarta')->toDateString();
            $weekStart = Carbon::now('Asia/Jakarta')->startOfWeek()->toDateString();
            $monthStart= Carbon::now('Asia/Jakarta')->startOfMonth()->toDateString();

            $todayCount = (clone $q)->whereDate('date', $today)->count();
            $weekCount  = (clone $q)->where('date', '>=', $weekStart)->count();
            $monthCount = (clone $q)->where('date', '>=', $monthStart)->count();

            return ['text' => "Visitor counts — today:{$todayCount} | this week:{$weekCount} | this month:{$monthCount}"];
        }

        if ($mode === 'today') {
            $entries = (clone $q)->whereDate('date', $date)->orderByDesc('jam_in')->take($limit)->get();
        } else {
            $entries = (clone $q)->orderByDesc('created_at')->take($limit)->get();
        }

        if ($entries->isEmpty()) {
            return ['text' => "No visitor entries found for {$date} in your facility."];
        }

        $lines = ["Visitors on {$date}:"];
        foreach ($entries as $g) {
            $in  = optional($g->jam_in)->format('H:i')  ?? '—';
            $out = optional($g->jam_out)->format('H:i') ?? '—';
            $lines[] = "  {$g->name} | Purpose: {$g->keperluan} | In: {$in} Out: {$out}";
        }

        return ['text' => implode("\n", $lines)];
    }
}
