<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

class AIAuthException extends RuntimeException {
    # thrown when the AI authentication returns an unrecoverable error
}