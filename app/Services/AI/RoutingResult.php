<?php

namespace App\Services\AI;

use App\Services\AI\Enums\ContextDetailLevel;

class RoutingResult
{
    public function __construct(
        public readonly bool $isBookingIntent,
        public readonly array $domains,
        public readonly array $providerDetailLevels,
        public readonly string $assembledContext
    ) {}

    public static function create(
        bool $isBookingIntent,
        array $domains,
        array $providerDetailLevels,
        string $assembledContext
    ): self {
        return new self($isBookingIntent, $domains, $providerDetailLevels, $assembledContext);
    }
}
