<?php

namespace App\Services\AI\Contracts;

interface ContextProviderInterface
{
    public function name(): string;
    public function load(?int $companyId, array $params = []): string;
}
