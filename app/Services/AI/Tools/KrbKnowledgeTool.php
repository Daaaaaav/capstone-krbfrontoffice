<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Contracts\ToolInterface;
use App\Services\AI\KrbKnowledgeService;

class KrbKnowledgeTool implements ToolInterface
{
    public function __construct(private KrbKnowledgeService $knowledgeService) {}

    public function name(): string
    {
        return 'get_krb_knowledge';
    }

    public function description(): string
    {
        return 'Retrieve verified information from the approved Kebun Raya Bogor knowledge base about history, '
             . 'botanical collections (e.g. Rafflesia, Titan Arum, orchids, palms), landmarks (Griya Anggrek, Danau Gunting, Jembatan Merah), '
             . 'BRIN conservation research, operating hours, and visitor facilities.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'query' => [
                    'type'        => 'string',
                    'description' => 'The topic or question about Kebun Raya Bogor (e.g. "sejarah kebun raya", "Rafflesia patma", "Griya Anggrek", "jam buka").',
                ],
                'category' => [
                    'type'        => 'string',
                    'enum'        => ['history', 'collections', 'botany', 'conservation', 'research', 'education', 'tourism', 'facilities', 'services', 'landmarks', 'all'],
                    'description' => 'Optional category filter for knowledge retrieval.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments): array
    {
        $query = (string) ($arguments['query'] ?? '');
        $category = ($arguments['category'] ?? 'all') === 'all' ? null : $arguments['category'];

        if (trim($query) === '') {
            return ['text' => KrbKnowledgeService::NOT_ENOUGH_INFO_EN];
        }

        $context = $this->knowledgeService->buildContext($query, $category);

        if ($context === '') {
            return ['text' => KrbKnowledgeService::NOT_ENOUGH_INFO_EN];
        }

        return ['text' => $context];
    }
}

