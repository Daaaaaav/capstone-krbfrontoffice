<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;

class CustomizeSecurityJsonFormatter
{
    public function __invoke($logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter());
        }
    }
}
