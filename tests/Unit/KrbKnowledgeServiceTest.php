<?php

namespace Tests\Unit;

use App\Models\KrbKnowledgeDocument;
use App\Models\KrbKnowledgeSource;
use App\Services\AI\KrbKnowledgeService;
use App\Services\AI\Tools\KrbKnowledgeTool;
use Database\Seeders\KrbKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KrbKnowledgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private KrbKnowledgeService $service;
    private KrbKnowledgeTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(KrbKnowledgeSeeder::class);
        $this->service = app(KrbKnowledgeService::class);
        $this->tool = app(KrbKnowledgeTool::class);
    }

    public function test_retrieves_history_knowledge(): void
    {
        $docs = $this->service->search('When was Kebun Raya Bogor founded and who founded it?');
        $this->assertNotEmpty($docs);

        $first = $docs->first();
        $this->assertEquals('history', $first->category);
        $this->assertStringContainsString('1817', $first->content);
        $this->assertStringContainsString('Reinwardt', $first->content);
    }

    public function test_retrieves_iconic_botanical_knowledge(): void
    {
        $docs = $this->service->search('Tell me about Rafflesia patma and Titan Arum');
        $this->assertNotEmpty($docs);

        $context = $this->service->buildContext('Rafflesia patma');
        $this->assertStringContainsString('Rafflesia patma', $context);
        $this->assertStringContainsString('Amorphophallus titanum', $context);
    }

    public function test_retrieves_landmark_knowledge(): void
    {
        $context = $this->service->buildContext('Where is Griya Anggrek and Danau Gunting?');
        $this->assertStringContainsString('Griya Anggrek', $context);
        $this->assertStringContainsString('Danau Gunting', $context);
    }

    public function test_fallback_when_no_knowledge_matches(): void
    {
        $result = $this->tool->execute([
            'query' => 'What is the secret underground missile base in Mars?',
        ]);

        $this->assertStringContainsString('approved Kebun Raya Bogor information', $result['text']);
    }
}

