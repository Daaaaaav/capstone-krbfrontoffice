<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Models\PriorityVehicleBooking as PriorityVehicleBookingModel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class PriorityVehicleBookingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $manager;
    protected User $receptionist;
    protected Vehicle $vehicle;
    protected Company $company;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(
            ['company_id' => 1],
            ['company_name' => 'Test Company']
        );
        $this->department = Department::firstOrCreate(
            ['department_id' => 1],
            ['company_id' => $this->company->company_id, 'department_name' => 'Test Department']
        );
        
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $receptionistRole = Role::firstOrCreate(['name' => 'receptionist']);

        $this->manager = User::create([
            'full_name' => 'Test Manager',
            'email' => 'manager_' . uniqid() . '@example.com',
            'phone_number' => '081234567890',
            'password' => Hash::make('password'),
            'company_id' => $this->company->company_id,
            'department_id' => $this->department->department_id,
            'role_id' => $managerRole->role_id,
        ]);

        $this->receptionist = User::create([
            'full_name' => 'Test Receptionist',
            'email' => 'receptionist_' . uniqid() . '@example.com',
            'phone_number' => '081234567891',
            'password' => Hash::make('password'),
            'company_id' => $this->company->company_id,
            'department_id' => $this->department->department_id,
            'role_id' => $receptionistRole->role_id,
        ]);

        $this->vehicle = Vehicle::create([
            'company_id' => $this->company->company_id,
            'name' => 'Operational Car 1',
            'category' => 'mobil_dinas',
            'plate_number' => 'B 1234 CD',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function manager_can_submit_priority_vehicle_booking_without_conflict()
    {
        $today = now('Asia/Jakarta')->toDateString();

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\PriorityVehicleBooking::class)
            ->set('vehicle_id', $this->vehicle->vehicle_id)
            ->set('department_id', $this->department->department_id)
            ->set('borrower_name', 'Test Borrower')
            ->set('date_from', $today)
            ->set('date_to', $today)
            ->set('start_time', '10:00')
            ->set('end_time', '12:00')
            ->set('purpose_type', 'dinas')
            ->set('purpose', 'Official Site Visit')
            ->set('destination', 'Branch Office')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('priority_vehicle_bookings', [
            'vehicle_id' => $this->vehicle->vehicle_id,
            'manager_id' => $this->manager->user_id,
            'borrower_name' => 'Test Borrower',
            'purpose' => 'Official Site Visit',
            'status' => PriorityVehicleBookingModel::STATUS_PENDING_RECEIPT,
        ]);
    }

    /** @test */
    public function priority_booking_can_override_pending_regular_vehicle_booking()
    {
        $futureDate = now('Asia/Jakarta')->addDays(2)->toDateString();
        $startAtFuture = Carbon::parse($futureDate . ' 10:00:00', 'Asia/Jakarta');
        $endAtFuture = Carbon::parse($futureDate . ' 12:00:00', 'Asia/Jakarta');

        $pendingBookingFuture = VehicleBooking::create([
            'company_id' => $this->company->company_id,
            'vehicle_id' => $this->vehicle->vehicle_id,
            'department_id' => $this->department->department_id,
            'user_id' => $this->receptionist->user_id,
            'borrower_name' => 'Future Regular User',
            'start_at' => $startAtFuture,
            'end_at' => $endAtFuture,
            'purpose' => 'Future Trip',
            'destination' => 'City Center',
            'purpose_type' => 'dinas',
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\PriorityVehicleBooking::class)
            ->set('vehicle_id', $this->vehicle->vehicle_id)
            ->set('department_id', $this->department->department_id)
            ->set('borrower_name', 'Priority Manager')
            ->set('date_from', $futureDate)
            ->set('date_to', $futureDate)
            ->set('start_time', '10:00')
            ->set('end_time', '12:00')
            ->set('purpose_type', 'dinas')
            ->set('purpose', 'Urgent VIP Trip')
            ->set('destination', 'Airport')
            ->call('save')
            ->assertSet('showConflictModal', true)
            ->call('confirmWithCancellation')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicle_bookings', [
            'vehiclebooking_id' => $pendingBookingFuture->vehiclebooking_id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('priority_vehicle_bookings', [
            'vehicle_id' => $this->vehicle->vehicle_id,
            'manager_id' => $this->manager->user_id,
            'borrower_name' => 'Priority Manager',
            'cancels_booking_id' => $pendingBookingFuture->vehiclebooking_id,
            'status' => PriorityVehicleBookingModel::STATUS_APPROVED,
        ]);
    }

    /** @test */
    public function priority_booking_fails_when_start_time_is_after_end_time()
    {
        $today = now('Asia/Jakarta')->toDateString();

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\PriorityVehicleBooking::class)
            ->set('vehicle_id', $this->vehicle->vehicle_id)
            ->set('department_id', $this->department->department_id)
            ->set('borrower_name', 'Test Borrower')
            ->set('date_from', $today)
            ->set('date_to', $today)
            ->set('start_time', '14:00')
            ->set('end_time', '10:00')
            ->set('purpose_type', 'dinas')
            ->set('purpose', 'Invalid Time Trip')
            ->call('save')
            ->assertDispatched('toast', function ($name, $params) {
                return ($params['title'] ?? '') === 'Invalid Booking Time';
            });
    }

    /** @test */
    public function priority_booking_disallows_booking_when_vehicle_is_already_approved()
    {
        $futureDate = now('Asia/Jakarta')->addDays(3)->toDateString();
        $startAtFuture = Carbon::parse($futureDate . ' 10:00:00', 'Asia/Jakarta');
        $endAtFuture = Carbon::parse($futureDate . ' 12:00:00', 'Asia/Jakarta');

        VehicleBooking::create([
            'company_id' => $this->company->company_id,
            'vehicle_id' => $this->vehicle->vehicle_id,
            'department_id' => $this->department->department_id,
            'user_id' => $this->receptionist->user_id,
            'borrower_name' => 'Approved User',
            'start_at' => $startAtFuture,
            'end_at' => $endAtFuture,
            'purpose' => 'Approved Trip',
            'destination' => 'City Center',
            'purpose_type' => 'dinas',
            'status' => 'approved',
        ]);

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Pages\Manager\PriorityVehicleBooking::class)
            ->set('vehicle_id', $this->vehicle->vehicle_id)
            ->set('department_id', $this->department->department_id)
            ->set('borrower_name', 'Priority Manager')
            ->set('date_from', $futureDate)
            ->set('date_to', $futureDate)
            ->set('start_time', '10:00')
            ->set('end_time', '12:00')
            ->set('purpose_type', 'dinas')
            ->set('purpose', 'VIP Trip')
            ->call('save')
            ->assertDispatched('toast', function ($name, $params) {
                return ($params['title'] ?? '') === 'Vehicle Unavailable';
            });
    }

    /** @test */
    public function normal_receptionist_vehicle_booking_continues_to_work()
    {
        $futureDate = now('Asia/Jakarta')->addDays(4)->toDateString();

        Livewire::actingAs($this->receptionist)
            ->test(\App\Livewire\Pages\Receptionist\Bookingvehicle::class)
            ->set('department_id', $this->department->department_id)
            ->set('borrower_name', 'Regular Borrower')
            ->set('vehicle_id', $this->vehicle->vehicle_id)
            ->set('date_from', $futureDate)
            ->set('date_to', $futureDate)
            ->set('start_time', '09:00')
            ->set('end_time', '11:00')
            ->set('purpose', 'Normal Site Visit')
            ->set('purpose_type', 'dinas')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicle_bookings', [
            'vehicle_id' => $this->vehicle->vehicle_id,
            'borrower_name' => 'Regular Borrower',
            'purpose' => 'Normal Site Visit',
            'status' => 'pending',
        ]);
    }
}
