<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use App\Services\AI\AIService;
use App\Services\AI\PromptBuilder;

class GroqService
{
    private AIService     $ai;
    private PromptBuilder $builder;
    private string        $tz = 'Asia/Jakarta';

    public function __construct()
    {
        $this->ai      = app(AIService::class);
        $this->builder = app(PromptBuilder::class);
    }

    public function managerChat(string $userMessage): string
    {
        $companyId    = Auth::user()->company_id;
        $context      = $this->builder->buildManagerContext($companyId);
        $systemPrompt = $this->builder->managerSystemPrompt($context);

        $raw = $this->ai->chat($systemPrompt, $userMessage);

        return $this->stripThinkBlocks($raw);
    }

    public function receptionistChat(string $userMessage): string
    {
        return $this->receptionistChatWithIntent($userMessage)['reply'];
    }

    public function receptionistChatWithIntent(string $userMessage): array
    {
        $companyId    = Auth::user()->company_id;
        $context      = $this->builder->buildReceptionistContext($companyId);
        $systemPrompt = $this->builder->receptionistSystemPrompt($context);

        $raw = $this->ai->chat($systemPrompt, $userMessage);

        return $this->parseIntentResponse($raw, $companyId);
    }

    private function parseIntentResponse(string $raw, ?int $companyId = null): array
    {
        $empty = [
            'reply'           => $raw,
            'booking_prefill' => [],
            'vehicle_prefill' => [],
        ];

        $raw = trim($raw);
        $raw = $this->stripThinkBlocks($raw);

        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $raw = trim($raw);

        if (! str_starts_with($raw, '{')) {
            return $empty;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['reply'])) {
            return $empty;
        }

        $reply     = (string) $decoded['reply'];
        $companyId = $companyId ?? Auth::user()?->company_id;

        // ── booking_prefill (room) ────────────────────────────────
        $prefill = $decoded['booking_prefill'] ?? [];
        if (is_array($prefill)) {
            if (empty($prefill['room_id']) && ! empty($prefill['room_name'])) {
                $room = \App\Models\Room::when($companyId, fn($q) => $q->where('company_id', $companyId))
                    ->where('room_name', 'like', '%' . trim($prefill['room_name']) . '%')
                    ->first();
                $prefill['room_id']   = $room?->room_id;
                $prefill['room_name'] = $room?->room_name ?? $prefill['room_name'];
            }
            $prefill['room_id']             = isset($prefill['room_id'])             ? (int) $prefill['room_id']             : null;
            $prefill['number_of_attendees'] = isset($prefill['number_of_attendees']) ? (int) $prefill['number_of_attendees'] : null;
            $prefill['special_notes']       = $prefill['special_notes']   ?? null;
            $prefill['department']          = $prefill['department']      ?? null;
            $prefill['historical_user']     = $prefill['historical_user'] ?? null;

            $validBookingTypes = ['meeting', 'online_meeting'];
            $prefill['booking_type'] = in_array($prefill['booking_type'] ?? '', $validBookingTypes, true)
                ? $prefill['booking_type'] : null;

            $validProviders = ['google_meet', 'zoom'];
            $prefill['online_provider'] = in_array($prefill['online_provider'] ?? '', $validProviders, true)
                ? $prefill['online_provider'] : null;

            if (($prefill['booking_type'] ?? '') === 'online_meeting') {
                $prefill['room_id']   = null;
                $prefill['room_name'] = null;
            }
        }

        $vprefill = $decoded['vehicle_prefill'] ?? [];
        if (is_array($vprefill)) {
            if (empty($vprefill['vehicle_id']) && (! empty($vprefill['vehicle_name']) || ! empty($vprefill['plate_number']))) {
                $vq = \App\Models\Vehicle::when($companyId, fn($q) => $q->where('company_id', $companyId));
                if (! empty($vprefill['vehicle_name'])) {
                    $vq = $vq->where('name', 'like', '%' . trim($vprefill['vehicle_name']) . '%');
                } elseif (! empty($vprefill['plate_number'])) {
                    $vq = $vq->where('plate_number', 'like', '%' . trim($vprefill['plate_number']) . '%');
                }
                $vehicle = $vq->first();
                $vprefill['vehicle_id']   = $vehicle?->vehicle_id;
                $vprefill['vehicle_name'] = $vehicle?->name         ?? $vprefill['vehicle_name']  ?? null;
                $vprefill['plate_number'] = $vehicle?->plate_number ?? $vprefill['plate_number']  ?? null;
            }
            $vprefill['vehicle_id']    = isset($vprefill['vehicle_id'])    ? (int) $vprefill['vehicle_id'] : null;
            $vprefill['borrower_name'] = $vprefill['borrower_name'] ?? null;
            $vprefill['department']    = $vprefill['department']    ?? null;
            $vprefill['date_from']     = $vprefill['date_from']     ?? null;
            $vprefill['date_to']       = $vprefill['date_to']       ?? null;
            $vprefill['start_time']    = $vprefill['start_time']    ?? null;
            $vprefill['end_time']      = $vprefill['end_time']      ?? null;
            $vprefill['purpose']       = $vprefill['purpose']       ?? null;
            $vprefill['destination']   = $vprefill['destination']   ?? null;

            $validTypes = ['dinas', 'operasional', 'antar_jemput', 'lainnya'];
            $vprefill['purpose_type'] = in_array($vprefill['purpose_type'] ?? '', $validTypes, true)
                ? $vprefill['purpose_type'] : null;
        }

        return [
            'reply'           => $reply,
            'booking_prefill' => $prefill  ?? [],
            'vehicle_prefill' => $vprefill ?? [],
        ];
    }

    private function stripThinkBlocks(string $raw): string
    {
        return trim(preg_replace('/<think>.*?<\/think>/si', '', $raw));
    }

    public function trendLabel(int $prev, int $curr): string
    {
        return $this->builder->trendLabel($prev, $curr);
    }
}
