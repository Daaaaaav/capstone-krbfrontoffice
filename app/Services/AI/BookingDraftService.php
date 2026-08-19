<?php

namespace App\Services\AI;

use App\Models\Room;
use App\Models\Vehicle;
use Carbon\Carbon;

class BookingDraftService
{
    public function emptyDraft(): array
    {
        return [
            'type'    => null,
            'active'  => false,
            'turns'   => 0,
            'room'    => $this->emptyRoomFields(),
            'vehicle' => $this->emptyVehicleFields(),
        ];
    }

    public function startRoomDraft(array $draft): array
    {
        $draft['type']   = 'room';
        $draft['active'] = true;
        return $draft;
    }

    public function startVehicleDraft(array $draft): array
    {
        $draft['type']   = 'vehicle';
        $draft['active'] = true;
        return $draft;
    }

    public function mergePrefill(array $draft, ?array $roomPrefill, ?array $vehiclePrefill): array
    {
        $hasRoomData    = $this->hasAnyField($roomPrefill    ?? []);
        $hasVehicleData = $this->hasAnyField($vehiclePrefill ?? []);

        if ($hasRoomData && $draft['type'] !== 'vehicle') {
            $draft['type']   = 'room';
            $draft['active'] = true;
        } elseif ($hasVehicleData && $draft['type'] !== 'room') {
            $draft['type']   = 'vehicle';
            $draft['active'] = true;
        }

        if ($hasRoomData && is_array($roomPrefill)) {
            foreach ($roomPrefill as $key => $value) {
                if ($value !== null && $value !== '' && array_key_exists($key, $draft['room'])) {
                    $draft['room'][$key] = $value;
                }
            }
        }

        if ($hasVehicleData && is_array($vehiclePrefill)) {
            foreach ($vehiclePrefill as $key => $value) {
                if ($value !== null && $value !== '' && array_key_exists($key, $draft['vehicle'])) {
                    $draft['vehicle'][$key] = $value;
                }
            }
        }

        $draft['turns']++;

        return $draft;
    }

    public function resetDraft(): array
    {
        return $this->emptyDraft();
    }

    public function isRoomDraftComplete(array $draft): bool
    {
        $r = $draft['room'];
        return $draft['type'] === 'room'
            && $draft['active']
            && !empty($r['meeting_title'])
            && !empty($r['date'])
            && !empty($r['start_time'])
            && !empty($r['end_time'])
            && (
                (!empty($r['room_id']) || $r['booking_type'] === 'online_meeting')
            );
    }

    public function isVehicleDraftComplete(array $draft): bool
    {
        $v = $draft['vehicle'];
        return $draft['type'] === 'vehicle'
            && $draft['active']
            && !empty($v['vehicle_id'])
            && !empty($v['borrower_name'])
            && !empty($v['date_from'])
            && !empty($v['date_to'])
            && !empty($v['start_time'])
            && !empty($v['end_time'])
            && !empty($v['purpose'])
            && !empty($v['destination'])
            && !empty($v['purpose_type']);
    }

    public function buildRoomPayload(array $draft): array
    {
        $r = $draft['room'];
        return [
            'roomId'         => $r['room_id']             ?? null,
            'ymd'            => $r['date']                ?? '',
            'time'           => $r['start_time']          ?? '',
            'endTime'        => $r['end_time']            ?? '',
            'title'          => $r['meeting_title']       ?? '',
            'attendees'      => $r['number_of_attendees'] ?? 1,
            'notes'          => $r['special_notes']       ?? '',
            'department'     => $r['department']          ?? null,
            'historicalUser' => $r['historical_user']     ?? null,
            'bookingType'    => $r['booking_type']        ?? 'meeting',
            'onlineProvider' => $r['online_provider']     ?? 'google_meet',
            'mode'           => 'create',
        ];
    }

    public function buildVehiclePayload(array $draft): array
    {
        $v = $draft['vehicle'];
        return [
            'vehicleId'     => $v['vehicle_id']    ?? null,
            'borrowerName'  => $v['borrower_name'] ?? '',
            'dateFrom'      => $v['date_from']     ?? '',
            'dateTo'        => $v['date_to']       ?? '',
            'startTime'     => $v['start_time']    ?? '',
            'endTime'       => $v['end_time']      ?? '',
            'purpose'       => $v['purpose']       ?? '',
            'destination'   => $v['destination']   ?? '',
            'purposeType'   => $v['purpose_type']  ?? null,
            'department'    => $v['department']    ?? null,
            'historicalUser'=> $v['borrower_name'] ?? null,
            'mode'          => 'create',
        ];
    }

    public function buildDraftContext(array $draft): string
    {
        if (!$draft['active']) {
            return '';
        }

        if ($draft['type'] === 'room') {
            return $this->roomDraftSummary($draft['room']);
        }

        if ($draft['type'] === 'vehicle') {
            return $this->vehicleDraftSummary($draft['vehicle']);
        }

        return '';
    }

    public function resolveDraftDates(array $draft, string $tz = 'Asia/Jakarta'): array
    {
        $now = Carbon::now($tz);

        $dateFields = [
            ['type' => 'room',    'field' => 'date'],
            ['type' => 'vehicle', 'field' => 'date_from'],
            ['type' => 'vehicle', 'field' => 'date_to'],
        ];

        foreach ($dateFields as $entry) {
            $t = $entry['type'];
            $f = $entry['field'];
            $val = $draft[$t][$f] ?? null;
            if ($val && !$this->looksLikeDate($val)) {
                $resolved = $this->parseNaturalDate($val, $now);
                if ($resolved) {
                    $draft[$t][$f] = $resolved;
                }
            }
        }

        $timeFields = [
            ['type' => 'room',    'field' => 'start_time'],
            ['type' => 'room',    'field' => 'end_time'],
            ['type' => 'vehicle', 'field' => 'start_time'],
            ['type' => 'vehicle', 'field' => 'end_time'],
        ];

        foreach ($timeFields as $entry) {
            $t = $entry['type'];
            $f = $entry['field'];
            $val = $draft[$t][$f] ?? null;
            if ($val && !$this->looksLikeTime($val)) {
                $resolved = $this->parseNaturalTime($val, $now);
                if ($resolved) {
                    $draft[$t][$f] = $resolved;
                }
            }
        }

        return $draft;
    }

    public function resolveRoomId(array $draft, ?int $companyId): array
    {
        if (empty($draft['room']['room_id']) && !empty($draft['room']['room_name'])) {
            $room = Room::when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->where('room_name', 'like', '%' . trim($draft['room']['room_name']) . '%')
                ->first();
            $draft['room']['room_id']   = $room?->room_id;
            $draft['room']['room_name'] = $room?->room_name ?? $draft['room']['room_name'];
        }
        return $draft;
    }

    public function resolveVehicleId(array $draft, ?int $companyId): array
    {
        if (empty($draft['vehicle']['vehicle_id'])) {
            $name  = $draft['vehicle']['vehicle_name']  ?? null;
            $plate = $draft['vehicle']['plate_number']  ?? null;

            if ($name || $plate) {
                $q = Vehicle::when($companyId, fn($q) => $q->where('company_id', $companyId));
                if ($name)  $q->where('name',         'like', '%' . trim($name)  . '%');
                if ($plate) $q->orWhere('plate_number', 'like', '%' . trim($plate) . '%');
                $vehicle = $q->first();

                $draft['vehicle']['vehicle_id']   = $vehicle?->vehicle_id;
                $draft['vehicle']['vehicle_name'] = $vehicle?->name         ?? $name;
                $draft['vehicle']['plate_number'] = $vehicle?->plate_number ?? $plate;
            }
        }
        return $draft;
    }

    private function emptyRoomFields(): array
    {
        return [
            'meeting_title'        => null,
            'room_id'              => null,
            'room_name'            => null,
            'booking_type'         => null,
            'online_provider'      => null,
            'department'           => null,
            'historical_user'      => null,
            'date'                 => null,
            'number_of_attendees'  => null,
            'start_time'           => null,
            'end_time'             => null,
            'special_notes'        => null,
        ];
    }

    private function emptyVehicleFields(): array
    {
        return [
            'vehicle_id'    => null,
            'vehicle_name'  => null,
            'plate_number'  => null,
            'borrower_name' => null,
            'department'    => null,
            'date_from'     => null,
            'date_to'       => null,
            'start_time'    => null,
            'end_time'      => null,
            'purpose'       => null,
            'destination'   => null,
            'purpose_type'  => null,
        ];
    }

    private function hasAnyField(array $fields): bool
    {
        foreach ($fields as $v) {
            if ($v !== null && $v !== '') {
                return true;
            }
        }
        return false;
    }

    private function roomDraftSummary(array $r): string
    {
        $lines = ['Type: Room Booking'];
        if ($r['meeting_title'])       $lines[] = "Title: {$r['meeting_title']}";
        if ($r['room_name'])           $lines[] = "Room: {$r['room_name']} (ID:{$r['room_id']})";
        if ($r['booking_type'])        $lines[] = "Booking type: {$r['booking_type']}" . ($r['online_provider'] ? " ({$r['online_provider']})" : '');
        if ($r['date'])                $lines[] = "Date: {$r['date']}";
        if ($r['start_time'])          $lines[] = "Start: {$r['start_time']}";
        if ($r['end_time'])            $lines[] = "End: {$r['end_time']}";
        if ($r['number_of_attendees']) $lines[] = "Attendees: {$r['number_of_attendees']}";
        if ($r['department'])          $lines[] = "Department: {$r['department']}";
        if ($r['special_notes'])       $lines[] = "Notes: {$r['special_notes']}";

        $missing = $this->missingRoomFields($r);
        if ($missing) {
            $lines[] = 'STILL NEEDED: ' . implode(', ', $missing);
        }

        return implode("\n", $lines);
    }

    private function vehicleDraftSummary(array $v): string
    {
        $lines = ['Type: Vehicle Booking'];
        if ($v['vehicle_name'])  $lines[] = "Vehicle: {$v['vehicle_name']} (ID:{$v['vehicle_id']})";
        if ($v['borrower_name']) $lines[] = "Borrower: {$v['borrower_name']}";
        if ($v['date_from'])     $lines[] = "From: {$v['date_from']}";
        if ($v['date_to'])       $lines[] = "To: {$v['date_to']}";
        if ($v['start_time'])    $lines[] = "Start: {$v['start_time']}";
        if ($v['end_time'])      $lines[] = "End: {$v['end_time']}";
        if ($v['purpose'])       $lines[] = "Purpose: {$v['purpose']}";
        if ($v['destination'])   $lines[] = "Destination: {$v['destination']}";
        if ($v['purpose_type'])  $lines[] = "Purpose type: {$v['purpose_type']}";
        if ($v['department'])    $lines[] = "Department: {$v['department']}";

        $missing = $this->missingVehicleFields($v);
        if ($missing) {
            $lines[] = 'STILL NEEDED: ' . implode(', ', $missing);
        }

        return implode("\n", $lines);
    }

    private function missingRoomFields(array $r): array
    {
        $missing = [];
        if (empty($r['meeting_title']))  $missing[] = 'meeting_title';
        if (empty($r['date']))           $missing[] = 'date';
        if (empty($r['start_time']))     $missing[] = 'start_time';
        if (empty($r['end_time']))       $missing[] = 'end_time';
        $isOnline = ($r['booking_type'] ?? '') === 'online_meeting';
        if (!$isOnline && empty($r['room_id'])) $missing[] = 'room (which room?)';
        return $missing;
    }

    private function missingVehicleFields(array $v): array
    {
        $missing = [];
        if (empty($v['vehicle_id']))    $missing[] = 'vehicle';
        if (empty($v['borrower_name'])) $missing[] = 'borrower_name';
        if (empty($v['date_from']))     $missing[] = 'date_from';
        if (empty($v['date_to']))       $missing[] = 'date_to';
        if (empty($v['start_time']))    $missing[] = 'start_time';
        if (empty($v['end_time']))      $missing[] = 'end_time';
        if (empty($v['purpose']))       $missing[] = 'purpose';
        if (empty($v['destination']))   $missing[] = 'destination';
        if (empty($v['purpose_type']))  $missing[] = 'purpose_type';
        return $missing;
    }

    private function looksLikeDate(string $val): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $val);
    }

    private function looksLikeTime(string $val): bool
    {
        return (bool) preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $val);
    }

    private function parseNaturalDate(string $val, Carbon $now): ?string
    {
        $val = strtolower(trim($val));

        $map = [
            'today'         => fn() => $now->copy()->toDateString(),
            'tomorrow'      => fn() => $now->copy()->addDay()->toDateString(),
            'yesterday'     => fn() => $now->copy()->subDay()->toDateString(),
            'next monday'   => fn() => $now->copy()->next('Monday')->toDateString(),
            'next tuesday'  => fn() => $now->copy()->next('Tuesday')->toDateString(),
            'next wednesday'=> fn() => $now->copy()->next('Wednesday')->toDateString(),
            'next thursday' => fn() => $now->copy()->next('Thursday')->toDateString(),
            'next friday'   => fn() => $now->copy()->next('Friday')->toDateString(),
            'next saturday' => fn() => $now->copy()->next('Saturday')->toDateString(),
            'next sunday'   => fn() => $now->copy()->next('Sunday')->toDateString(),
            'monday'        => fn() => $now->copy()->next('Monday')->toDateString(),
            'tuesday'       => fn() => $now->copy()->next('Tuesday')->toDateString(),
            'wednesday'     => fn() => $now->copy()->next('Wednesday')->toDateString(),
            'thursday'      => fn() => $now->copy()->next('Thursday')->toDateString(),
            'friday'        => fn() => $now->copy()->next('Friday')->toDateString(),
            'saturday'      => fn() => $now->copy()->next('Saturday')->toDateString(),
            'sunday'        => fn() => $now->copy()->next('Sunday')->toDateString(),
        ];

        foreach ($map as $pattern => $resolver) {
            if (str_contains($val, $pattern)) {
                return ($resolver)();
            }
        }

        try {
            $parsed = Carbon::parse($val, $now->timezone);
            if ($parsed->year > 2000) {
                return $parsed->toDateString();
            }
        } catch (\Throwable) {
            // unparseable would return null
        }

        return null;
    }

    private function parseNaturalTime(string $val, Carbon $now): ?string
    {
        $val = strtolower(trim($val));

        if (preg_match('/half past (\w+)/', $val, $m)) {
            $h = $this->wordToHour($m[1]);
            if ($h !== null) return sprintf('%02d:30', $h);
        }
        if (preg_match('/quarter past (\w+)/', $val, $m)) {
            $h = $this->wordToHour($m[1]);
            if ($h !== null) return sprintf('%02d:15', $h);
        }
        if (preg_match('/quarter to (\w+)/', $val, $m)) {
            $h = $this->wordToHour($m[1]);
            if ($h !== null) return sprintf('%02d:45', ($h - 1 + 24) % 24);
        }
        if (preg_match('/^(\d{1,2})\s*(am|pm)$/', $val, $m)) {
            $h = (int) $m[1];
            if ($m[2] === 'pm' && $h !== 12) $h += 12;
            if ($m[2] === 'am' && $h === 12) $h = 0;
            return sprintf('%02d:00', $h);
        }
        if (preg_match('/^(\d{1,2}):(\d{2})\s*(am|pm)$/', $val, $m)) {
            $h = (int) $m[1];
            $min = $m[2];
            if ($m[3] === 'pm' && $h !== 12) $h += 12;
            if ($m[3] === 'am' && $h === 12) $h = 0;
            return sprintf('%02d:%s', $h, $min);
        }
        return null;
    }

    private function wordToHour(string $word): ?int
    {
        $map = [
            'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4,
            'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8,
            'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12,
        ];
        return $map[strtolower($word)] ?? null;
    }
}