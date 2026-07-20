<?php

namespace App\Services\AI\Context;

use App\Models\BookingRoom;
use App\Models\Room;
use App\Services\AI\Contracts\ContextProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Loads room-related context: available rooms, today's bookings,
 * pending approvals, and recent bookings.
 *
 * Called only when the ContextRouter detects room-booking intent.
 * Replaces the "always load everything" approach in PromptBuilder.
 */
class RoomContextProvider implements ContextProviderInterface
{
    private string $tz = 'Asia/Jakarta';

    public function name(): string
    {
        return 'rooms';
    }

    public function load(?int $companyId, array $params = []): string
    {
        $now   = Carbon::now($this->tz);
        $today = $params['date'] ?? $now->toDateString();

        $cacheKey = "ctx_rooms_{$companyId}_{$today}";
        return Cache::remember($cacheKey, 90, fn() => $this->build($companyId, $now, $today));
    }

    private function build(?int $companyId, Carbon $now, string $today): string
    {
        // Available rooms (always needed for booking intent)
        $availableRooms = Room::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->orderBy('room_name')
            ->get(['room_id', 'room_name', 'capacity'])
            ->map(fn($r) => sprintf('  [RoomID:%d] %s | Cap:%s', $r->room_id, $r->room_name ?? '—', $r->capacity ?? '—'))
            ->join("\n") ?: '  (none)';

        // Today's meetings (cap 8)
        $todayRooms = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['room', 'department'])
            ->whereDate('date', $today)
            ->orderBy('start_time')
            ->take(8)->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | Room:%s | Dept:%s | %s–%s | %s',
                $b->bookingroom_id, $b->meeting_title ?? '—',
                $b->room?->room_name ?? '—', $b->department?->name ?? '—',
                substr($b->start_time ?? '—', 0, 5), substr($b->end_time ?? '—', 0, 5),
                ucfirst($b->status ?? '—')
            ))->join("\n") ?: '  (none)';

        // Pending approvals (cap 5)
        $pending = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['room', 'department'])
            ->where('status', 'pending')
            ->orderBy('date')->orderBy('start_time')
            ->take(5)->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | Room:%s | %s %s–%s | Dept:%s',
                $b->bookingroom_id, $b->meeting_title ?? '—',
                $b->room?->room_name ?? '—',
                $b->date?->format('d M Y') ?? '—',
                substr($b->start_time ?? '—', 0, 5), substr($b->end_time ?? '—', 0, 5),
                $b->department?->name ?? '—'
            ))->join("\n") ?: '  (none)';

        // Recent bookings last 7 days (cap 6)
        $recent = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['room'])
            ->whereBetween('date', [$now->copy()->subDays(6)->toDateString(), $today])
            ->orderByDesc('date')->take(6)->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | %s | Room:%s | %s | %s',
                $b->bookingroom_id, $b->meeting_title ?? '—',
                $b->booking_type === 'online_meeting' ? 'Online(' . ($b->online_provider ?? '—') . ')' : 'In-Room',
                $b->room?->room_name ?? '—',
                $b->date?->format('d M Y') ?? '—',
                ucfirst($b->status ?? '—')
            ))->join("\n") ?: '  (none)';

        // Recent online meetings last 30 days (cap 5)
        $online = BookingRoom::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->with(['department'])->where('booking_type', 'online_meeting')
            ->whereBetween('date', [$now->copy()->subDays(29)->toDateString(), $today])
            ->orderByDesc('date')->take(5)->get()
            ->map(fn($b) => sprintf(
                '  [ID:%d] %s | %s | %s %s–%s | %s',
                $b->bookingroom_id, $b->meeting_title ?? '—',
                ucfirst(str_replace('_', ' ', $b->online_provider ?? '—')),
                $b->date?->format('d M Y') ?? '—',
                substr($b->start_time ?? '—', 0, 5), substr($b->end_time ?? '—', 0, 5),
                ucfirst($b->status ?? '—')
            ))->join("\n") ?: '  (none)';

        return <<<BLOCK
        AVAILABLE ROOMS:
        {$availableRooms}

        TODAY'S MEETINGS ({$today}):
        {$todayRooms}

        PENDING APPROVALS (≤5):
        {$pending}

        RECENT ROOM BOOKINGS (last 7 days, ≤6):
        {$recent}

        RECENT ONLINE MEETINGS (last 30 days, ≤5):
        {$online}
        BLOCK;
    }
}
