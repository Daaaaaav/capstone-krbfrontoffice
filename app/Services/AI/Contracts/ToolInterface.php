<?php

namespace App\Services\AI\Contracts;

/**
 * Contract for all AI tools.
 *
 * Tools are thin adapters that bridge AI intent → existing Laravel services.
 * No business logic lives here — every tool delegates to existing Models,
 * Services, and queries that already exist in the application.
 */
interface ToolInterface
{
    /**
     * Machine-readable name used to identify the tool in the AI's function-call response.
     * Use snake_case, e.g. "check_room_availability".
     */
    public function name(): string;

    /**
     * Human-readable description sent to the AI so it knows when to call this tool.
     * Keep it concise but specific.
     */
    public function description(): string;

    /**
     * JSON Schema-style parameter definitions for this tool.
     * Returned as part of the tools manifest sent to the AI.
     *
     * Example:
     * [
     *   'type' => 'object',
     *   'properties' => [
     *     'date' => ['type' => 'string', 'description' => 'Date in YYYY-MM-DD format'],
     *   ],
     *   'required' => ['date'],
     * ]
     */
    public function parameters(): array;

    /**
     * Execute the tool with the arguments extracted by the AI.
     *
     * Must NOT write to the database or trigger bookings — those go through
     * the existing QuickBookModal / QuickVehicleBookModal Livewire flow.
     *
     * @param  array  $arguments  Key-value pairs matching the parameter schema.
     * @return array              Structured result that ToolDispatcher formats into context text.
     */
    public function execute(array $arguments): array;
}
