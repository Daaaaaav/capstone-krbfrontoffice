<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

class AIRateLimitException extends RuntimeException {
    # Thrown when the AI provider returns HTTP 429 (rate limit exceeded).
}
