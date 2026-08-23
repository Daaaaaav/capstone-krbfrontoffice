<?php

namespace Tests\Unit;

use App\Services\AI\Tools\CalculationTool;
use PHPUnit\Framework\TestCase;

class CalculationToolTest extends TestCase
{
    private CalculationTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new CalculationTool();
    }

    public function test_average_from_values(): void
    {
        $res = $this->tool->execute([
            'operation' => 'average',
            'values'    => [10, 20, 30, 40],
        ]);

        $this->assertTrue($res['success']);
        $this->assertEquals(25.0, $res['result']);
        $this->assertStringContainsString('25', $res['text']);
    }

    public function test_average_from_numerator_and_denominator(): void
    {
        $res = $this->tool->execute([
            'operation'   => 'average',
            'numerator'   => 52,
            'denominator' => 26,
        ]);

        $this->assertTrue($res['success']);
        $this->assertEquals(2.0, $res['result']);
    }

    public function test_percentage_calculation(): void
    {
        $res = $this->tool->execute([
            'operation'   => 'percentage',
            'numerator'   => 15,
            'denominator' => 60,
        ]);

        $this->assertTrue($res['success']);
        $this->assertEquals(25.0, $res['result']);
        $this->assertStringContainsString('25%', $res['text']);
    }

    public function test_division_by_zero_safe(): void
    {
        $res = $this->tool->execute([
            'operation'   => 'divide',
            'numerator'   => 100,
            'denominator' => 0,
        ]);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('zero', $res['text']);
    }

    public function test_growth_rate_calculation(): void
    {
        $res = $this->tool->execute([
            'operation'   => 'growth_rate',
            'numerator'   => 150,
            'denominator' => 100,
        ]);

        $this->assertTrue($res['success']);
        $this->assertEquals(50.0, $res['result']);
        $this->assertStringContainsString('+50%', $res['text']);
    }

    public function test_difference_calculation(): void
    {
        $res = $this->tool->execute([
            'operation'   => 'difference',
            'numerator'   => 120,
            'denominator' => 45,
        ]);

        $this->assertTrue($res['success']);
        $this->assertEquals(75.0, $res['result']);
    }
}

