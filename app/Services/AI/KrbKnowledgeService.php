<?php

namespace App\Services\AI;

use App\Models\KrbKnowledgeDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class KrbKnowledgeService
{
    public const NOT_ENOUGH_INFO_EN = "I don't currently have enough approved Kebun Raya Bogor information in my knowledge base to answer that reliably.";
    public const NOT_ENOUGH_INFO_ID = "Saya belum memiliki informasi terverifikasi yang cukup dalam basis pengetahuan Kebun Raya Bogor untuk menjawab hal tersebut secara akurat.";

    /**
     * Search relevant knowledge documents based on keywords and query terms.
     */
    public function search(string $query, ?string $category = null, int $limit = 3): Collection
    {
        $terms = $this->extractSearchTerms($query);
        if (empty($terms)) {
            return collect();
        }

        $queryBuilder = KrbKnowledgeDocument::with('source')
            ->where('is_active', true);

        if ($category) {
            $queryBuilder->where('category', $category);
        }

        $documents = $queryBuilder->get();

        $scored = $documents->map(function (KrbKnowledgeDocument $doc) use ($terms, $query) {
            $score = $this->calculateScore($doc, $terms, $query);
            $doc->relevance_score = $score;
            return $doc;
        })->filter(fn($doc) => $doc->relevance_score > 0)
          ->sortByDesc('relevance_score')
          ->take($limit)
          ->values();

        return $scored;
    }

    /**
     * Build formatted context string from retrieved knowledge.
     */
    public function buildContext(string $query, ?string $category = null, int $limit = 3): string
    {
        $docs = $this->search($query, $category, $limit);

        if ($docs->isEmpty()) {
            return '';
        }

        $lines = ["=== APPROVED KEBUN RAYA BOGOR KNOWLEDGE BASE ==="];

        foreach ($docs as $doc) {
            $sourceName = $doc->source?->name ?? 'Verified KRB Knowledge Source';
            $ref = $doc->source?->source_reference ? " ({$doc->source->source_reference})" : '';
            $lines[] = "[Source: {$sourceName}{$ref} | Category: {$doc->category}]";
            $lines[] = "Title: {$doc->title}";
            $lines[] = "Content:\n" . trim($doc->content);
            $lines[] = "---";
        }

        $lines[] = "INSTRUCTION: Answer using only verified facts from the approved knowledge base above. Do not invent botanical, historical, or facility details.";

        return implode("\n\n", $lines);
    }

    /**
     * Extract normalized tokens for matching.
     */
    private function extractSearchTerms(string $query): array
    {
        $clean = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query));
        $stopWords = [
            'what', 'is', 'the', 'are', 'who', 'when', 'where', 'how', 'why', 'in', 'on', 'at', 'of', 'and', 'or', 'to', 'for', 'about', 'tell', 'me', 'explain', 'show',
            'apa', 'siapa', 'kapan', 'di', 'mana', 'bagaimana', 'kenapa', 'mengapa', 'dari', 'ke', 'pada', 'yang', 'dan', 'atau', 'untuk', 'tentang', 'ceritakan', 'jelaskan', 'tampilkan', 'kebun', 'raya', 'bogor'
        ];

        $tokens = array_filter(explode(' ', $clean), fn($t) => strlen($t) >= 3 && ! in_array($t, $stopWords, true));
        return array_values(array_unique($tokens));
    }

    /**
     * Score a document against query terms and phrases.
     */
    private function calculateScore(KrbKnowledgeDocument $doc, array $terms, string $rawQuery): float
    {
        $score = 0.0;
        $titleLower = mb_strtolower($doc->title);
        $contentLower = mb_strtolower($doc->content);
        $summaryLower = mb_strtolower($doc->summary ?? '');
        $keywords = array_map('mb_strtolower', (array) ($doc->keywords ?? []));
        $rawLower = mb_strtolower($rawQuery);

        // Exact phrase match in title or content
        if (str_contains($titleLower, $rawLower)) {
            $score += 15.0;
        } elseif (str_contains($contentLower, $rawLower)) {
            $score += 10.0;
        }

        // Keyword matches
        foreach ($keywords as $kw) {
            if (str_contains($rawLower, $kw)) {
                $score += 8.0;
            }
        }

        // Term matches
        foreach ($terms as $term) {
            if (str_contains($titleLower, $term)) {
                $score += 5.0;
            }
            if (str_contains($summaryLower, $term)) {
                $score += 3.0;
            }
            if (str_contains($contentLower, $term)) {
                $score += 1.5;
            }
            foreach ($keywords as $kw) {
                if (str_contains($kw, $term)) {
                    $score += 4.0;
                }
            }
        }

        return $score;
    }
}

