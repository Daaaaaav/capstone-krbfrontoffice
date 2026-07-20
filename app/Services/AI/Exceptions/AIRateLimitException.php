<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

/**
 * Thrown when the AI provider returns HTTP 429 (rate limit exceeded).
 * The caller can catch this and return a user-friendly message without retrying.
 */
class AIRateLimitException extends RuntimeException {}
