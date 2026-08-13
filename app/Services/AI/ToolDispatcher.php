<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\ToolInterface;
use Illuminate\Support\Facades\Log;

class ToolDispatcher
{
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
        if (class_exists(\App\Services\AI\Tools\AnnouncementTool::class)) {
            $this->register(app(\App\Services\AI\Tools\AnnouncementTool::class));
        }
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function all(): array
    {
        return $this->tools;
    }

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

    public function dispatch(string $toolName, array $arguments): string
    {
        if (! isset($this->tools[$toolName])) {
            Log::warning('ToolDispatcher: unknown tool requested', [
                'stage'            => 'tool_execution',
                'requested_tool'   => $toolName,
                'registered_tools' => array_keys($this->tools),
            ]);
            return "[Tool '{$toolName}' is not available.]";
        }

        try {
            Log::info('ToolDispatcher: executing tool', [
                'stage'    => 'tool_execution',
                'tool'     => $toolName,
                'class'    => get_class($this->tools[$toolName]),
                'args'     => $arguments,
            ]);

            $result = $this->tools[$toolName]->execute($arguments);

            Log::info('ToolDispatcher: tool executed successfully', [
                'stage'       => 'tool_execution',
                'tool'        => $toolName,
                'result_keys' => array_keys($result),
                'text_chars'  => isset($result['text']) ? strlen($result['text']) : null,
            ]);

            return $this->formatResult($toolName, $result);

        } catch (\Throwable $e) {
            Log::error('ToolDispatcher: tool threw an exception', [
                'stage'  => 'tool_execution',
                'tool'   => $toolName,
                'class'  => get_class($this->tools[$toolName]),
                'args'   => $arguments,
                'exception_class' => get_class($e),
                'error'  => $e->getMessage(),
                'file'   => $e->getFile() . ':' . $e->getLine(),
            ]);
            return "[Tool '{$toolName}' failed: {$e->getMessage()}]";
        }
    }

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

    private function formatResult(string $toolName, array $result): string
    {
        if (empty($result)) {
            return "[{$toolName}: no data found]";
        }

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
