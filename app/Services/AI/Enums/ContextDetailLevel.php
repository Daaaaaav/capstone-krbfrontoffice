<?php

namespace App\Services\AI\Enums;

enum ContextDetailLevel: string
{
    case MINIMAL = 'minimal';
    case NORMAL = 'normal';
    case BOOKING = 'booking';
    case DETAILED = 'detailed';
}
