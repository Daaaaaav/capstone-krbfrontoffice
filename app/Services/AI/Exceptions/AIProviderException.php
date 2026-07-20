<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

/**
 * Thrown when the AI provider returns an unrecoverable error
 * (non-2xx, non-429 status, or a malformed response).
 */
class AIProviderException extends RuntimeException {}
