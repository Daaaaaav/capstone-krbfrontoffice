<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Contracts\ToolInterface;
use App\Services\AI\LSTMClient;
use Illuminate\Support\Facades\Log;

class ForecastTool implements ToolInterface
{
    public function __construct(private LSTMClient $lstm) {}

    public function name(): string
    {
        return 'get_forecast';
    }

    public function description(): string
    {
        return 'Get LSTM-based occupancy or booking volume forecasts for the next 1–3 weeks. '
             . 'Use when the user asks about predictions, forecasts, or expected demand.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'weeks' => [
                    'type'        => 'integer',
                    'description' => 'Number of weeks to forecast ahead (1, 2, or 3).',
                    'minimum'     => 1,
                    'maximum'     => 3,
                ],
                'module' => [
                    'type'        => 'string',
                    'enum'        => ['rooms', 'vehicles'],
                    'description' => 'Which module to forecast.',
                ],
            ],
            'required' => ['weeks', 'module'],
        ];
    }

    public function execute(array $arguments): array
    {
        $weeks  = max(1, min(3, (int) ($arguments['weeks']  ?? 1)));
        $module = $arguments['module'] ?? 'rooms';

        try {
            $result = $this->lstm->predict($module, $weeks);
            $lines  = ["Forecast ({$module}, {$weeks} week(s)):"];
            foreach ($result as $week => $value) {
                $lines[] = "  Week {$week}: {$value}";
            }
            return ['text' => implode("\n", $lines)];
        } catch (\Throwable $e) {
            Log::warning('ForecastTool: LSTM unavailable', ['error' => $e->getMessage()]);
            return ['text' => 'Forecast data is currently unavailable (LSTM service offline).'];
        }
    }
}
