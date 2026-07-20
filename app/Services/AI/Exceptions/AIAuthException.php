<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

/**
 * Thrown when the AI provider rejects the request due to authentication failure
 * (HTTP 401 / 403 / invalid API key / invalid model).
 *
 * AIService will NOT retry on this exception — skip to the next provider in
 * the failover chain instead.
 */
class AIAuthException extends RuntimeException {}
