<?php

namespace App\Services\AI;

use App\Models\BookingRoom;
use App\Models\VehicleBooking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DirectBookingService
{
    private string $tz = 'Asia/Jakarta';

    public function createRoomBooking(array $payload): array
    {
        $tag = ['stage' => 'booking_validation', 'source' => 'DirectBookingService'];

        Log::info('DirectBookingService: starting room booking validation', array_merge($tag, [
            'payload' => $payload,
        ]));

        $isOnline = ($payload['bookingType'] ?? 'meeting') === 'online_meeting';

        $title      = trim((string) ($payload['title']     ?? ''));
        $date       = trim((string) ($payload['ymd']       ?? ''));
        $startRaw   = trim((string) ($payload['time']      ?? ''));
        $endRaw     = trim((string) ($payload['endTime']   ?? ''));
        $roomId     = $payload['roomId']    ?? null;
        $attendees  = max(1, (int) ($payload['attendees'] ?? 1));
        $notes      = (string) ($payload['notes'] ?? '');
        $dept       = $payload['department']     ?? null;
        $histUser   = $payload['historicalUser'] ?? null;
        $provider   = $payload['onlineProvider'] ?? 'google_meet';

        $validationErrors = [];

        if (strlen($title) < 3) {
            $validationErrors[] = 'meeting_title must be at least 3 characters';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $validationErrors[] = "date is invalid or missing (got: '{$date}')";
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $startRaw)) {
            $validationErrors[] = "start_time is invalid (got: '{$startRaw}')";
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $endRaw)) {
            $validationErrors[] = "end_time is invalid (got: '{$endRaw}')";
        }
        if (! $isOnline && empty($roomId)) {
            $validationErrors[] = 'room_id is required for in-room bookings';
        }

        if ($validationErrors) {
            Log::warning('DirectBookingService: room booking validation failed', array_merge($tag, [
                'errors'  => $validationErrors,
                'payload' => $payload,
            ]));
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'Validation failed: ' . implode('; ', $validationErrors)];
        }

        $now     = Carbon::now($this->tz);
        $startDt = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startRaw}", $this->tz);
        $endDt   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$endRaw}",   $this->tz);

        if ($endDt->lte($startDt)) {
            Log::warning('DirectBookingService: end_time not after start_time', $tag);
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'end_time must be after start_time.'];
        }

        if ($date < $now->toDateString()) {
            Log::warning('DirectBookingService: booking date is in the past', $tag);
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'Cannot book a date that has already passed.'];
        }
        if ($date === $now->toDateString() && $startRaw < $now->format('H:i')) {
            Log::warning('DirectBookingService: start_time is in the past', $tag);
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'Start time cannot be in the past.'];
        }

        if (! $isOnline && $roomId) {
            $overlap = BookingRoom::query()
                ->where('room_id', $roomId)
                ->where('date', $date)
                ->whereIn('status', ['pending', 'approved'])
                ->where('start_time', '<', $endDt)
                ->where('end_time',   '>', $startDt)
                ->exists();

            if ($overlap) {
                Log::warning('DirectBookingService: room slot already taken', array_merge($tag, [
                    'stage'    => 'booking_validation',
                    'room_id'  => $roomId,
                    'date'     => $date,
                    'start'    => $startRaw,
                    'end'      => $endRaw,
                ]));
                return ['ok' => false, 'booking_id' => null,
                        'error' => "That time slot is already taken for this room ({$startRaw}–{$endRaw}). Please choose a different time."];
            }
        }

        Log::info('DirectBookingService: room booking passed validation — creating record', array_merge($tag, [
            'stage'      => 'booking_create_started',
            'room_id'    => $roomId,
            'date'       => $date,
            'start_time' => $startRaw,
            'end_time'   => $endRaw,
            'title'      => $title,
            'is_online'  => $isOnline,
        ]));

        try {
            $bookingId = null;

            DB::transaction(function () use (
                $isOnline, $roomId, $date, $startDt, $endDt,
                $title, $attendees, $notes, $provider, $dept, $histUser,
                &$bookingId
            ) {
                $data = [
                    'company_id'          => Auth::user()->company_id ?? 1,
                    'user_id'             => Auth::id() ?? 1,
                    'department_id'       => Auth::user()->department_id ?? null,
                    'meeting_title'       => $title,
                    'date'                => $date,
                    'number_of_attendees' => $attendees,
                    'start_time'          => $startDt,
                    'end_time'            => $endDt,
                    'special_notes'       => $notes ?: null,
                    'booking_type'        => $isOnline ? 'online_meeting' : 'meeting',
                    'is_approve'          => 0,
                    'status'              => 'pending',
                    'approved_by'         => null,
                ];

                if (! $isOnline) {
                    $data['room_id'] = (int) $roomId;
                } else {
                    $data['online_provider'] = in_array($provider, ['google_meet', 'zoom'], true)
                        ? $provider : 'google_meet';
                }

                $booking   = BookingRoom::create($data);
                $bookingId = $booking->bookingroom_id;
            });

            Log::info('DirectBookingService: room booking created', [
                'stage'      => 'booking_created',
                'booking_id' => $bookingId,
                'room_id'    => $roomId,
                'date'       => $date,
                'title'      => $title,
                'is_online'  => $isOnline,
            ]);

            return ['ok' => true, 'booking_id' => $bookingId, 'error' => null];

        } catch (\Throwable $e) {
            Log::error('DirectBookingService: room booking DB exception', [
                'stage' => 'booking_failed',
                'class' => get_class($e),
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'A database error occurred while saving the booking. Please try again.'];
        }
    }

    public function createVehicleBooking(array $payload): array
    {
        $tag = ['stage' => 'booking_validation', 'source' => 'DirectBookingService'];

        Log::info('DirectBookingService: starting vehicle booking validation', array_merge($tag, [
            'payload' => $payload,
        ]));

        $vehicleId    = $payload['vehicleId']     ?? null;
        $borrowerName = trim((string) ($payload['borrowerName'] ?? ''));
        $dateFrom     = trim((string) ($payload['dateFrom']     ?? ''));
        $dateTo       = trim((string) ($payload['dateTo']       ?? ''));
        $startRaw     = trim((string) ($payload['startTime']    ?? ''));
        $endRaw       = trim((string) ($payload['endTime']      ?? ''));
        $purpose      = trim((string) ($payload['purpose']      ?? ''));
        $destination  = trim((string) ($payload['destination']  ?? ''));
        $purposeType  = $payload['purposeType']  ?? null;
        $dept         = $payload['department']   ?? null;
        $histUser     = $payload['historicalUser'] ?? null;

        $validPurposeTypes = ['dinas', 'operasional', 'antar_jemput', 'lainnya'];
        $validationErrors  = [];

        if (empty($vehicleId)) {
            $validationErrors[] = 'vehicle_id is required';
        }
        if (strlen($borrowerName) < 2) {
            $validationErrors[] = 'borrower_name is required';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $validationErrors[] = "date_from is invalid (got: '{$dateFrom}')";
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $validationErrors[] = "date_to is invalid (got: '{$dateTo}')";
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $startRaw)) {
            $validationErrors[] = "start_time is invalid (got: '{$startRaw}')";
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $endRaw)) {
            $validationErrors[] = "end_time is invalid (got: '{$endRaw}')";
        }
        if (strlen($purpose) < 2) {
            $validationErrors[] = 'purpose is required';
        }
        if (! in_array($purposeType, $validPurposeTypes, true)) {
            $validationErrors[] = "purpose_type must be one of: " . implode(', ', $validPurposeTypes);
        }

        if ($validationErrors) {
            Log::warning('DirectBookingService: vehicle booking validation failed', array_merge($tag, [
                'errors'  => $validationErrors,
                'payload' => $payload,
            ]));
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'Validation failed: ' . implode('; ', $validationErrors)];
        }

        $startAt = Carbon::parse("{$dateFrom} {$startRaw}", $this->tz);
        $endAt   = Carbon::parse("{$dateTo} {$endRaw}",     $this->tz);
        $now     = Carbon::now($this->tz);

        if ($startAt->lt($now)) {
            Log::warning('DirectBookingService: vehicle start_time is in the past', $tag);
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'Start time cannot be in the past.'];
        }

        if ($endAt->lte($startAt)) {
            Log::warning('DirectBookingService: vehicle end_time not after start_time', $tag);
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'end_time must be after start_time.'];
        }

        $blocker = VehicleBooking::findLateReturnBlocker((int) $vehicleId);
        if ($blocker) {
            Log::warning('DirectBookingService: vehicle has unresolved late return', array_merge($tag, [
                'vehicle_id' => $vehicleId,
                'blocker_id' => $blocker->vehiclebooking_id,
            ]));
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'Vehicle unavailable — unresolved late return (Booking #' . $blocker->vehiclebooking_id . ').'];
        }

        $conflict = VehicleBooking::where('vehicle_id', $vehicleId)
            ->whereIn('status', ['pending', 'approved', 'on_progress'])
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where('start_at', '<', $endAt->toDateTimeString())
                  ->whereRaw('DATE_ADD(end_at, INTERVAL 1 HOUR) > ?', [$startAt->toDateTimeString()]);
            })
            ->first();

        if ($conflict) {
            Log::warning('DirectBookingService: vehicle time conflict', array_merge($tag, [
                'vehicle_id'  => $vehicleId,
                'conflict_id' => $conflict->vehiclebooking_id,
            ]));
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'This vehicle is already booked from '
                        . $conflict->start_at->format('H:i') . ' to ' . $conflict->end_at->format('H:i')
                        . '. (1-hour buffer required)'];
        }

        Log::info('DirectBookingService: vehicle booking passed validation — creating record', array_merge($tag, [
            'stage'      => 'booking_create_started',
            'vehicle_id' => $vehicleId,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'start_time' => $startRaw,
            'end_time'   => $endRaw,
            'borrower'   => $borrowerName,
        ]));

        try {
            $bookingId = null;

            DB::transaction(function () use (
                $vehicleId, $borrowerName, $startAt, $endAt,
                $purpose, $destination, $purposeType, $dept,
                &$bookingId
            ) {
                $booking = VehicleBooking::create([
                    'vehicle_id'    => (int) $vehicleId,
                    'company_id'    => Auth::user()->company_id ?? 1,
                    'user_id'       => Auth::id(),
                    'department_id' => Auth::user()->department_id ?? null,
                    'borrower_name' => $borrowerName,
                    'start_at'      => $startAt,
                    'end_at'        => $endAt,
                    'purpose'       => $purpose,
                    'destination'   => $destination ?: null,
                    'odd_even_area' => 'tidak',
                    'purpose_type'  => $purposeType,
                    'terms_agreed'  => 1,
                    'is_approve'    => 0,
                    'status'        => 'pending',
                    'notes'         => null,
                ]);
                $bookingId = $booking->vehiclebooking_id;
            });

            Log::info('DirectBookingService: vehicle booking created', [
                'stage'      => 'booking_created',
                'booking_id' => $bookingId,
                'vehicle_id' => $vehicleId,
                'date_from'  => $dateFrom,
                'borrower'   => $borrowerName,
            ]);

            return ['ok' => true, 'booking_id' => $bookingId, 'error' => null];

        } catch (\Throwable $e) {
            Log::error('DirectBookingService: vehicle booking DB exception', [
                'stage' => 'booking_failed',
                'class' => get_class($e),
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);
            return ['ok' => false, 'booking_id' => null,
                    'error' => 'A database error occurred while saving the vehicle booking. Please try again.'];
        }
    }
}
