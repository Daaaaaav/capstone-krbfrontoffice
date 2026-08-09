<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\Enums\ContextDetailLevel;

interface ContextProviderInterface
{
    public function name(): string;
    public function load(?int $companyId, array $params = [], ?ContextDetailLevel $detailLevel = null): string;
}
