<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\ToolInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ToolDispatcher
{
    protected array $tools = [];

    public function __construct()
    {
        $this->register(app(\App\Services\AI\Tools\RoomAvailabilityTool::class));
        $this->register(app(\App\Services\AI\Tools\VehicleAvailabilityTool::class));
        $this->register(app(\App\Services\AI\Tools\AnalyticsTool::class));
        $this->register(app(\App\Services\AI\Tools\GuestbookTool::class));
        $this->register(app(\App\Services\AI\Tools\DeliveryTool::class));
        $this->register(app(\App\Services\AI\Tools\ForecastTool::class));
        $this->register(app(\App\Services\AI\Tools\UserManagementTool::class));
        $this->register(app(\App\Services\AI\Tools\OccupancyTool::class));
        $this->register(app(\App\Services\AI\Tools\CalculationTool::class));
        $this->register(app(\App\Services\AI\Tools\KrbKnowledgeTool::class));
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

        $user = Auth::user();
        if (! $user) {
            Log::warning('ToolDispatcher: unauthenticated tool execution attempted', ['tool' => $toolName]);
            return "[Unauthorized: Authentication required.]";
        }

        $roleName = strtolower($user->role?->name ?? $user->role_name ?? '');

        if (! $this->isToolAuthorizedForRole($toolName, $roleName)) {
            Log::warning('ToolDispatcher: unauthorized role tool execution blocked', [
                'tool'    => $toolName,
                'user_id' => $user->user_id,
                'role'    => $roleName,
            ]);
            return "I can only assist with information and tasks related to your authorized KRB System context.";
        }

        try {
            Log::info('ToolDispatcher: executing tool', [
                'stage'      => 'tool_execution',
                'tool'       => $toolName,
                'class'      => get_class($this->tools[$toolName]),
                'company_id' => $user->company_id,
                'user_id'    => $user->user_id,
                'args'       => $arguments,
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
                'stage'           => 'tool_execution',
                'tool'            => $toolName,
                'class'           => get_class($this->tools[$toolName]),
                'args'            => $arguments,
                'exception_class' => get_class($e),
                'error'           => $e->getMessage(),
                'file'            => $e->getFile() . ':' . $e->getLine(),
            ]);
            return "[Tool '{$toolName}' failed: {$e->getMessage()}]";
        }
    }

    protected function isToolAuthorizedForRole(string $toolName, string $roleName): bool
    {
        $isItOfficer = str_contains($roleName, 'it') || str_contains($roleName, 'officer');
        $isManager = str_contains($roleName, 'manager');
        $isReceptionist = str_contains($roleName, 'receptionist');

        $itOnlyTools = ['manage_user', 'manage_room', 'manage_vehicle', 'manage_storage'];

        if (in_array($toolName, $itOnlyTools, true)) {
            return $isItOfficer;
        }
        
        return $isItOfficer || $isManager || $isReceptionist;
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
