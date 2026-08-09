<?php

namespace App\Services\AI;

use App\Models\AISettings;
use Carbon\Carbon;

class DecisionEngine
{
    public function evaluate($booking): array
    {
        $urgencyHours  = AISettings::get('urgency_hours', 24);
        $longDuration  = AISettings::get('long_duration_hours', 4);
        $highThreshold = AISettings::get('risk_high_threshold', 70);
        $medThreshold  = AISettings::get('risk_med_threshold', 40);

        $score   = 0;
        $reasons = [];

        if ($this->hasConflict($booking)) {
            $score   += 40;
            $reasons[] = 'Schedule conflict detected';
        }

        $hours = Carbon::now()->diffInHours($booking->start_time);
        if ($hours < (int) $urgencyHours) {
            $score   += 20;
            $reasons[] = "High urgency booking (within {$urgencyHours}h)";
        }

        if ($booking->duration_hours > (int) $longDuration) {
            $score   += 20;
            $reasons[] = "Long duration booking (>{$longDuration}h)";
        }
        
        $priorityManager = (float) AISettings::get('priority_manager', 1.0);
        $priorityDefault = (float) AISettings::get('priority_default', 0.5);

        $priority = match ($booking->user->role ?? 'user') {
            'Manager' => $priorityManager,
            default   => $priorityDefault,
        };

        $score += $priority * 20;

        return [
            'score'   => $score,
            'label'   => $this->label($score, (int) $highThreshold, (int) $medThreshold),
            'reasons' => $reasons,
        ];
    }

    private function hasConflict($booking): bool
    {
        return $booking->conflict ?? false;
    }

    private function label(float $score, int $high, int $med): string
    {
        return match (true) {
            $score >= $high => 'High Risk',
            $score >= $med  => 'Moderate Risk',
            default         => 'Low Risk',
        };
    }
}