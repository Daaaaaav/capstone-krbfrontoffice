<?php

namespace App\Services\AI\Contracts;

interface ToolInterface
{
    public function name(): string;
    public function description(): string;
    public function parameters(): array;
    public function execute(array $arguments): array;
}
