<?php

namespace App\Services\AI\Context;

use App\Services\AI\Contracts\ContextProviderInterface;
use App\Services\AI\Enums\ContextDetailLevel;
use App\Services\AI\KrbKnowledgeService;

class KrbKnowledgeContextProvider implements ContextProviderInterface
{
    public function __construct(private KrbKnowledgeService $knowledgeService) {}

    public function name(): string
    {
        return 'krb_knowledge';
    }

    public function load(?int $companyId, array $params = [], ?ContextDetailLevel $detailLevel = null): string
    {
        $query = (string) ($params['query'] ?? $params['message'] ?? '');
        if (trim($query) === '') {
            return '';
        }

        $category = $params['category'] ?? null;
        $limit = $detailLevel === ContextDetailLevel::DETAILED ? 4 : 2;

        return $this->knowledgeService->buildContext($query, $category, $limit);
    }
}

