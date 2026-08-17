<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WazuhAlert extends Model
{
    public function getSeverityLabelAttribute(): string
    {
        return match (true) {
            $this->rule_level <= 3  => 'Low',
            $this->rule_level <= 7  => 'Medium',
            $this->rule_level <= 11 => 'High',
            default                 => 'Critical',
        };
    }

    /**
     * Get Tailwind CSS badge style classes for UI rendering.
     */
    public function getSeverityBadgeClassAttribute(): string
    {
        return match (true) {
            $this->rule_level <= 3  => 'bg-green-100 text-green-800 border-green-300',
            $this->rule_level <= 7  => 'bg-yellow-100 text-yellow-800 border-yellow-300',
            $this->rule_level <= 11 => 'bg-orange-100 text-orange-800 border-orange-300',
            default                 => 'bg-red-100 text-red-800 border-red-300 font-bold',
        };
    }
}
