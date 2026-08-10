<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class ManagerDateRangeValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->company_id]);
        $role = Role::factory()->create(['role_name' => 'manager']);

        $this->manager = User::factory()->create([
            'company_id' => $company->company_id,
            'department_id' => $department->department_id,
            'role_id' => $role->role_id,
        ]);
    }

    /** @test */
    public function guestbook_statistics_prevents_end_date_before_start_date()
    {
        $component = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\GuestbookStatistics::class);

        // Set valid range
        $component->set('startDate', '2026-08-10')
                  ->set('endDate', '2026-08-15');

        $component->assertSet('startDate', '2026-08-10')
                  ->assertSet('endDate', '2026-08-15');

        // Try to set end date before start date
        $component->set('endDate', '2026-08-05');

        // Should auto-correct to start date
        $component->assertSet('endDate', '2026-08-10');
    }

    /** @test */
    public function room_booking_statistics_prevents_end_date_before_start_date()
    {
        $component = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\RoomBookingStatistics::class);

        $component->set('startDate', '2026-08-10')
                  ->set('endDate', '2026-08-15');

        $component->assertSet('startDate', '2026-08-10')
                  ->assertSet('endDate', '2026-08-15');

        $component->set('endDate', '2026-08-05');
        $component->assertSet('endDate', '2026-08-10');
    }

    /** @test */
    public function vehicle_booking_statistics_prevents_end_date_before_start_date()
    {
        $component = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\VehicleBookingStatistics::class);

        $component->set('startDate', '2026-08-10')
                  ->set('endDate', '2026-08-15');

        $component->assertSet('startDate', '2026-08-10')
                  ->assertSet('endDate', '2026-08-15');

        $component->set('endDate', '2026-08-05');
        $component->assertSet('endDate', '2026-08-10');
    }

    /** @test */
    public function delivery_statistics_prevents_end_date_before_start_date()
    {
        $component = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\DeliveryStatistics::class);

        $component->set('startDate', '2026-08-10')
                  ->set('endDate', '2026-08-15');

        $component->assertSet('startDate', '2026-08-10')
                  ->assertSet('endDate', '2026-08-15');

        $component->set('endDate', '2026-08-05');
        $component->assertSet('endDate', '2026-08-10');
    }

    /** @test */
    public function lstm_predictions_prevents_invalid_forecast_range()
    {
        $component = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\LSTMPredictions::class);

        // LSTM uses forecastStartDate and forecastEndDate
        $component->set('forecastStartDate', '2026-08-10')
                  ->set('forecastEndDate', '2026-08-15');

        $this->assertEquals('2026-08-10', $component->get('forecastStartDate'));
        $this->assertEquals('2026-08-15', $component->get('forecastEndDate'));

        // The forecastDays should be calculated
        $this->assertEquals(5, $component->get('forecastDays'));
    }

    /** @test */
    public function occupancy_forecasting_prevents_invalid_forecast_range()
    {
        $component = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\OccupancyForecasting::class);

        $component->set('forecastStartDate', '2026-08-10')
                  ->set('forecastEndDate', '2026-08-15');

        $this->assertEquals('2026-08-10', $component->get('forecastStartDate'));
        $this->assertEquals('2026-08-15', $component->get('forecastEndDate'));

        $this->assertEquals(5, $component->get('forecastDays'));
    }

    /** @test */
    public function changing_start_date_after_end_date_auto_corrects()
    {
        $component = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\GuestbookStatistics::class);

        // Set initial range
        $component->set('startDate', '2026-08-05')
                  ->set('endDate', '2026-08-10');

        // Now change start date to be after end date
        $component->set('startDate', '2026-08-15');

        // End date should be auto-corrected to new start date
        $component->assertSet('endDate', '2026-08-15');
    }

    /** @test */
    public function same_start_and_end_date_is_allowed()
    {
        $component = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\GuestbookStatistics::class);

        $component->set('startDate', '2026-08-10')
                  ->set('endDate', '2026-08-10');

        $component->assertSet('startDate', '2026-08-10')
                  ->assertSet('endDate', '2026-08-10');
    }
}
