<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\ToolInterface;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches AI tool/function calls to the correct ToolInterface implementation.
 *
 * The dispatcher:
 *  1. Holds a registry of all available tools.
 *  2. Produces the "tools" manifest array to send to the AI provider.
 *  3. Executes a named tool with AI-extracted arguments.
 *  4. Returns a formatted result string ready to inject into the next prompt turn.
 *
 * Tools themselves contain zero business logic — they delegate to existing
 * Models, Services, and queries. See app/Services/AI/Tools/*.php.
 */
class ToolDispatcher
{
    /** @var array<string, ToolInterface> name → tool */
    private array $tools = [];

    public function __construct()
    {
        $this->register(app(\App\Services\AI\Tools\RoomAvailabilityTool::class));
        $this->register(app(\App\Services\AI\Tools\VehicleAvailabilityTool::class));
        $this->register(app(\App\Services\AI\Tools\AnalyticsTool::class));
        $this->register(app(\App\Services\AI\Tools\GuestbookTool::class));
        $this->register(app(\App\Services\AI\Tools\DeliveryTool::class));
        $this->register(app(\App\Services\AI\Tools\ForecastTool::class));
        $this->register(app(\App\Services\AI\Tools\OccupancyTool::class));
        $this->register(app(\App\Services\AI\Tools\AnnouncementTool::class));
    }

    // ──────────────────────────────────────────────────────────
    // Registry
    // ──────────────────────────────────────────────────────────

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    /** Return all registered tools (for manifest building). */
    public function all(): array
    {
        return $this->tools;
    }

    // ──────────────────────────────────────────────────────────
    // Manifest — sent to AI provider
    // ──────────────────────────────────────────────────────────

    /**
     * Build the OpenAI-compatible "tools" array for the chat completion request.
     * Only include a subset of tool names when $only is non-empty.
     *
     * @param  string[]  $only  Optional whitelist of tool names to include.
     */
    public function manifest(array $only = []): array
    {
        $manifest = [];
        foreach ($this->tools as $name => $tool) {
            if (! empty($only) && ! in_array($name, $only, true)) {
                continue;
            }
            $manifest[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $tool->name(),
                    'description' => $tool->description(),
                    'parameters'  => $tool->parameters(),
                ],
            ];
        }
        return $manifest;
    }

    // ──────────────────────────────────────────────────────────
    // Execution
    // ──────────────────────────────────────────────────────────

    /**
     * Execute a named tool and return its plain-text result for prompt injection.
     *
     * @param  string  $toolName   Matches ToolInterface::name().
     * @param  array   $arguments  Key-value pairs from the AI's function_call.
     * @return string              Formatted result string, or an error description.
     */
    public function dispatch(string $toolName, array $arguments): string
    {
        if (! isset($this->tools[$toolName])) {
            Log::warning("ToolDispatcher: unknown tool '{$toolName}'");
            return "[Tool '{$toolName}' is not available.]";
        }

        try {
            Log::info("ToolDispatcher: executing '{$toolName}'", ['args' => $arguments]);

            $result = $this->tools[$toolName]->execute($arguments);

            Log::info("ToolDispatcher: '{$toolName}' returned", ['keys' => array_keys($result)]);

            return $this->formatResult($toolName, $result);

        } catch (\Throwable $e) {
            Log::error("ToolDispatcher: '{$toolName}' threw an exception", [
                'error' => $e->getMessage(),
                'args'  => $arguments,
            ]);
            return "[Tool '{$toolName}' failed: {$e->getMessage()}]";
        }
    }

    /**
     * Parse an AI response body for tool_calls and dispatch each one.
     * Returns an array of [tool_name => result_string] for all calls found.
     *
     * Works with the standard OpenAI tool-call response format:
     *   choices[0].message.tool_calls[].function.{name, arguments}
     */
    public function parseAndDispatch(array $responseBody): array
    {
        $results    = [];
        $toolCalls  = $responseBody['choices'][0]['message']['tool_calls'] ?? [];

        foreach ($toolCalls as $call) {
            $name      = $call['function']['name']      ?? '';
            $argsJson  = $call['function']['arguments'] ?? '{}';
            $arguments = is_string($argsJson) ? (json_decode($argsJson, true) ?? []) : (array) $argsJson;

            if ($name) {
                $results[$name] = $this->dispatch($name, $arguments);
            }
        }

        return $results;
    }

    // ──────────────────────────────────────────────────────────
    // Formatting
    // ──────────────────────────────────────────────────────────

    private function formatResult(string $toolName, array $result): string
    {
        if (empty($result)) {
            return "[{$toolName}: no data found]";
        }

        // Each tool returns a 'text' key with pre-formatted output,
        // or we fall back to a compact key: value dump.
        if (isset($result['text'])) {
            return $result['text'];
        }

        $lines = ["[{$toolName} result]"];
        foreach ($result as $key => $value) {
            $lines[] = "  {$key}: " . (is_array($value) ? json_encode($value) : $value);
        }
        return implode("\n", $lines);
    }
}
