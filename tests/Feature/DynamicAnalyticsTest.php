<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Services\AI\DynamicAnalyticsService;
use App\Services\AI\Tools\AnalyticsTool;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private Vehicle $vehicle;
    private DynamicAnalyticsService $service;
    private AnalyticsTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'company_name' => 'Kebun Raya Bogor',
        ]);

        $dept = Department::create([
            'company_id'      => $this->company->company_id,
            'department_name' => 'Transport',
        ]);

        $role = Role::create(['name' => 'Manager']);

        $this->user = User::create([
            'company_id'    => $this->company->company_id,
            'department_id' => $dept->department_id,
            'role_id'       => $role->role_id,
            'full_name'     => 'Manager Budi',
            'email'         => 'budi.mgr@krbogor.id',
            'phone_number'  => '081234567890',
            'password'      => bcrypt('password'),
            'status'        => 'active',
        ]);

        $this->vehicle = Vehicle::create([
            'company_id'   => $this->company->company_id,
            'name'         => 'Toyota Avanza 01',
            'plate_number' => 'F 1001 KRB',
            'category'     => 'car',
            'year'         => '2023',
            'is_active'    => 1,
        ]);

        $this->service = app(DynamicAnalyticsService::class);
        $this->tool = app(AnalyticsTool::class);
    }

    public function test_sunday_average_vehicle_bookings_in_2026(): void
    {
        // In 2026:
        // Sunday Jan 4, 2026: 2 bookings
        // Sunday Jan 11, 2026: 1 booking
        // Sunday Jan 18, 2026: 1 cancelled booking (should be excluded from active qualifying count)
        // All other Sundays: 0 bookings

        VehicleBooking::create([
            'company_id'    => $this->company->company_id,
            'department_id' => $this->user->department_id,
            'vehicle_id'    => $this->vehicle->vehicle_id,
            'user_id'       => $this->user->user_id,
            'borrower_name' => 'Staff A',
            'start_at'      => Carbon::create(2026, 1, 4, 9, 0, 0, 'Asia/Jakarta'),
            'end_at'        => Carbon::create(2026, 1, 4, 11, 0, 0, 'Asia/Jakarta'),
            'status'        => 'approved',
            'purpose'       => 'Dinas',
            'destination'   => 'Jakarta',
            'terms_agreed'  => 1,
        ]);

        VehicleBooking::create([
            'company_id'    => $this->company->company_id,
            'department_id' => $this->user->department_id,
            'vehicle_id'    => $this->vehicle->vehicle_id,
            'user_id'       => $this->user->user_id,
            'borrower_name' => 'Staff B',
            'start_at'      => Carbon::create(2026, 1, 4, 13, 0, 0, 'Asia/Jakarta'),
            'end_at'        => Carbon::create(2026, 1, 4, 15, 0, 0, 'Asia/Jakarta'),
            'status'        => 'completed',
            'purpose'       => 'Dinas',
            'destination'   => 'Jakarta',
            'terms_agreed'  => 1,
        ]);

        VehicleBooking::create([
            'company_id'    => $this->company->company_id,
            'department_id' => $this->user->department_id,
            'vehicle_id'    => $this->vehicle->vehicle_id,
            'user_id'       => $this->user->user_id,
            'borrower_name' => 'Staff C',
            'start_at'      => Carbon::create(2026, 1, 11, 10, 0, 0, 'Asia/Jakarta'),
            'end_at'        => Carbon::create(2026, 1, 11, 12, 0, 0, 'Asia/Jakarta'),
            'status'        => 'approved',
            'purpose'       => 'Dinas',
            'destination'   => 'Jakarta',
            'terms_agreed'  => 1,
        ]);

        VehicleBooking::create([
            'company_id'    => $this->company->company_id,
            'department_id' => $this->user->department_id,
            'vehicle_id'    => $this->vehicle->vehicle_id,
            'user_id'       => $this->user->user_id,
            'borrower_name' => 'Staff D',
            'start_at'      => Carbon::create(2026, 1, 18, 10, 0, 0, 'Asia/Jakarta'),
            'end_at'        => Carbon::create(2026, 1, 18, 12, 0, 0, 'Asia/Jakarta'),
            'status'        => 'cancelled',
            'purpose'       => 'Dinas',
            'destination'   => 'Jakarta',
            'terms_agreed'  => 1,
        ]);

        // Total qualifying bookings = 3 (2 on Jan 4, 1 on Jan 11).
        // Total Sundays in 2026 = 52.
        // Expected average = 3 / 52 = 0.06

        $res = $this->service->calculateWeekdayAverage(
            $this->company->company_id,
            'vehicle_bookings',
            'Sunday',
            2026,
            true
        );

        $this->assertTrue($res['success']);
        $this->assertEquals(3, $res['total_bookings']);
        $this->assertEquals(52, $res['period_count']);
        $this->assertEquals(50, $res['zero_booking_period_count']);
        $this->assertEquals(round(3 / 52, 2), $res['average']);
        $this->assertStringContainsString('3 qualifying bookings across 52 Sundays', $res['text']);
    }

    public function test_analytics_tool_dynamic_aggregation(): void
    {
        $this->actingAs($this->user);

        $res = $this->tool->execute([
            'entity'   => 'vehicle_bookings',
            'weekday'  => 'Sunday',
            'year'     => 2026,
        ]);

        $this->assertArrayHasKey('text', $res);
        $this->assertStringContainsString('average', strtolower($res['text']));
    }
}

