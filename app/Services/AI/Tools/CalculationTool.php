<?php

namespace App\Services\AI\Tools;

use App\Services\AI\Contracts\ToolInterface;
use App\Services\AI\Enums\ChatDataSource;

class CalculationTool implements ToolInterface
{
    public function name(): string
    {
        return 'calculate';
    }

    public function description(): string
    {
        return 'Perform safe deterministic arithmetic calculations (average, percentage, ratio, difference, growth_rate, sum, divide, multiply) from verified numeric data.';
    }

    public function parameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'operation' => [
                    'type'        => 'string',
                    'enum'        => ['average', 'percentage', 'ratio', 'difference', 'growth_rate', 'sum', 'divide', 'multiply'],
                    'description' => 'The calculation operation to perform.',
                ],
                'values' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'number'],
                    'description' => 'List of numeric values (for sum or average).',
                ],
                'numerator' => [
                    'type'        => 'number',
                    'description' => 'Numerator or primary operand (for divide, percentage, ratio, difference, growth_rate).',
                ],
                'denominator' => [
                    'type'        => 'number',
                    'description' => 'Denominator or baseline operand.',
                ],
                'precision' => [
                    'type'        => 'integer',
                    'description' => 'Decimal precision (default 2).',
                ],
                'sources' => [
                    'type'        => 'array',
                    'description' => 'Optional upstream data source provenance to preserve.',
                ],
            ],
            'required' => ['operation'],
        ];
    }

    public function execute(array $arguments): array
    {
        $operation = strtolower((string) ($arguments['operation'] ?? ''));
        $precision = max(0, min(6, (int) ($arguments['precision'] ?? 2)));
        $values = array_map('floatval', (array) ($arguments['values'] ?? []));
        $num = isset($arguments['numerator']) ? (float) $arguments['numerator'] : 0.0;
        $den = isset($arguments['denominator']) ? (float) $arguments['denominator'] : 0.0;
        $sources = (array) ($arguments['sources'] ?? []);

        $sourceTag = ! empty($sources) ? "\n\n" . ChatDataSource::formatSourcesTag($sources) : '';

        switch ($operation) {
            case 'average':
            case 'mean':
                if (! empty($values)) {
                    $cnt = count($values);
                    $sum = array_sum($values);
                    $res = round($sum / $cnt, $precision);
                    return [
                        'success'   => true,
                        'operation' => 'average',
                        'count'     => $cnt,
                        'sum'       => $sum,
                        'result'    => $res,
                        'sources'   => $sources,
                        'text'      => "Average of {$cnt} values: {$res} (sum: {$sum}).{$sourceTag}",
                    ];
                }
                if ($den == 0) {
                    return [
                        'success' => false,
                        'error'   => 'Division by zero is not permitted.',
                        'sources' => $sources,
                        'text'    => 'Calculation error: Denominator cannot be zero.',
                    ];
                }
                $res = round($num / $den, $precision);
                return [
                    'success'     => true,
                    'operation'   => 'average',
                    'numerator'   => $num,
                    'denominator' => $den,
                    'result'      => $res,
                    'sources'     => $sources,
                    'text'        => "Average: {$res} ({$num} / {$den}).{$sourceTag}",
                ];

            case 'percentage':
                if ($den == 0) {
                    return [
                        'success' => false,
                        'error'   => 'Division by zero is not permitted.',
                        'sources' => $sources,
                        'text'    => 'Calculation error: Denominator cannot be zero.',
                    ];
                }
                $res = round(($num / $den) * 100, $precision);
                return [
                    'success'     => true,
                    'operation'   => 'percentage',
                    'numerator'   => $num,
                    'denominator' => $den,
                    'result'      => $res,
                    'sources'     => $sources,
                    'text'        => "Percentage: {$res}% ({$num} out of {$den}).{$sourceTag}",
                ];

            case 'ratio':
            case 'divide':
                if ($den == 0) {
                    return [
                        'success' => false,
                        'error'   => 'Division by zero is not permitted.',
                        'sources' => $sources,
                        'text'    => 'Calculation error: Division by zero.',
                    ];
                }
                $res = round($num / $den, $precision);
                return [
                    'success'     => true,
                    'operation'   => $operation,
                    'numerator'   => $num,
                    'denominator' => $den,
                    'result'      => $res,
                    'sources'     => $sources,
                    'text'        => "Result: {$res} ({$num} ÷ {$den}).{$sourceTag}",
                ];

            case 'difference':
                $res = round($num - $den, $precision);
                return [
                    'success'    => true,
                    'operation'  => 'difference',
                    'minuend'    => $num,
                    'subtrahend' => $den,
                    'result'     => $res,
                    'sources'    => $sources,
                    'text'       => "Difference: {$res} ({$num} - {$den}).{$sourceTag}",
                ];

            case 'growth_rate':
                if ($den == 0) {
                    $res = $num > 0 ? 100.0 : 0.0;
                } else {
                    $res = round((($num - $den) / $den) * 100, $precision);
                }
                $sign = $res >= 0 ? '+' : '';
                return [
                    'success'   => true,
                    'operation' => 'growth_rate',
                    'current'   => $num,
                    'previous'  => $den,
                    'result'    => $res,
                    'sources'   => $sources,
                    'text'      => "Growth rate: {$sign}{$res}% (from {$den} to {$num}).{$sourceTag}",
                ];

            case 'sum':
                $sum = array_sum($values);
                if (empty($values) && ($num != 0 || $den != 0)) {
                    $sum = $num + $den;
                }
                $res = round($sum, $precision);
                return [
                    'success'   => true,
                    'operation' => 'sum',
                    'result'    => $res,
                    'sources'   => $sources,
                    'text'      => "Total sum: {$res}.{$sourceTag}",
                ];

            case 'multiply':
                $res = round($num * $den, $precision);
                return [
                    'success'   => true,
                    'operation' => 'multiply',
                    'result'    => $res,
                    'sources'   => $sources,
                    'text'      => "Product: {$res} ({$num} × {$den}).{$sourceTag}",
                ];

            default:
                return [
                    'success' => false,
                    'error'   => "Unsupported operation: '{$operation}'.",
                    'sources' => $sources,
                    'text'    => "Unsupported calculation operation.",
                ];
        }
    }
}

