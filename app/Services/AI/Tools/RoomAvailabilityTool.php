<?php

namespace App\Services\AI\Tools;

use App\Models\BookingRoom;
use App\Models\Room;
use App\Services\AI\Contracts\ToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class RoomAvailabilityTool implements ToolInterface
{
    public function name(): string
    {
        return 'check_room_availability';
    }

    public function description(): string
    {
        return 'Check which meeting rooms are available or booked on a specific date, '
             . 'optionally filtered by time range. Use this when the user asks about '
             . 'room availability, free slots, or which room to book.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'date'       => ['type' => 'string',  'description' => 'Date to check, YYYY-MM-DD'],
                'start_time' => ['type' => 'string',  'description' => 'Optional start time HH:MM to filter overlapping bookings'],
                'end_time'   => ['type' => 'string',  'description' => 'Optional end time HH:MM'],
                'room_name'  => ['type' => 'string',  'description' => 'Optional: check a specific room by name'],
            ],
            'required' => ['date'],
        ];
    }

    public function execute(array $arguments): array
    {
        $companyId = Auth::user()?->company_id;
        if (! $companyId) {
            return ['text' => 'Room availability information is currently unavailable.'];
        }

        $date       = $arguments['date']       ?? Carbon::today('Asia/Jakarta')->toDateString();
        $startTime  = $arguments['start_time'] ?? null;
        $endTime    = $arguments['end_time']   ?? null;
        $roomName   = $arguments['room_name']  ?? null;

        $roomQ = Room::where('company_id', $companyId)->orderBy('room_name');
        if ($roomName) {
            $roomQ->where('room_name', 'like', '%' . $roomName . '%');
        }
        $rooms = $roomQ->get(['room_id', 'room_name', 'capacity']);

        if ($rooms->isEmpty()) {
            return ['text' => "No rooms found for your facility."];
        }

        $bookingQ = BookingRoom::where('company_id', $companyId)
            ->whereDate('date', $date)
            ->whereIn('status', ['pending', 'approved'])
            ->with('room');

        if ($startTime && $endTime) {
            $bookingQ->where('start_time', '<', $endTime)
                     ->where('end_time',   '>', $startTime);
        }

        $bookings = $bookingQ->get();
        $occupiedIds = $bookings->pluck('room_id')->unique()->toArray();

        $lines   = ["Room availability on {$date}" . ($startTime ? " ({$startTime}–{$endTime})" : '') . ':'];
        $free    = 0;
        $busy    = 0;

        foreach ($rooms as $room) {
            $occupied = in_array($room->room_id, $occupiedIds);
            $status   = $occupied ? 'OCCUPIED' : 'FREE';
            if ($occupied) {
                $busy++;
                $roomBookings = $bookings->where('room_id', $room->room_id);
                $slots = $roomBookings->map(fn($b) =>
                    substr($b->start_time ?? '', 0, 5) . '–' . substr($b->end_time ?? '', 0, 5)
                    . ' (' . ($b->meeting_title ?? '—') . ')'
                )->implode(', ');
                $lines[] = "  [RoomID:{$room->room_id}] {$room->room_name} (cap:{$room->capacity}) — {$status}: {$slots}";
            } else {
                $free++;
                $lines[] = "  [RoomID:{$room->room_id}] {$room->room_name} (cap:{$room->capacity}) — {$status}";
            }
        }

        $lines[] = "Summary: {$free} free, {$busy} occupied.";

        return ['text' => implode("\n", $lines)];
    }
}
