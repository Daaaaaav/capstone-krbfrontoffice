<?php

namespace App\Services\AI\Contracts;

/**
 * Contract for RAG-style context providers.
 *
 * Each provider is responsible for ONE domain (rooms, vehicles, analytics, etc.)
 * and returns only the minimum data needed to answer a question in that domain.
 *
 * PromptBuilder no longer loads everything — it assembles only the providers
 * that the ContextRouter determined are relevant to the current user message.
 */
interface ContextProviderInterface
{
    /**
     * Machine-readable identifier, e.g. "rooms", "vehicles", "analytics".
     */
    public function name(): string;

    /**
     * Load and return a compact, pre-formatted context string.
     *
     * @param  int|null  $companyId   Tenant scope — pass Auth user's company_id.
     * @param  array     $params      Optional parameters from ContextRouter
     *                                (e.g. ['date' => '2026-07-21', 'period' => 'this_week']).
     * @return string    Ready-to-embed context block, or empty string if no data.
     */
    public function load(?int $companyId, array $params = []): string;
}
