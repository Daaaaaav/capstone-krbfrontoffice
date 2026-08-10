<?php

namespace Tests\Feature\ItOfficer;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\Requirement;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Livewire\Pages\ItOfficer\Requirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class RequirementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $itOfficer;
    protected User $receptionist;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Create company
        $this->company = Company::create([
            'company_name' => 'Test Company',
            'company_address' => 'Test Address',
            'company_email' => 'test@company.com',
        ]);

        // Create roles
        $itRole = Role::create(['name' => 'IT Officer']);
        $receptionistRole = Role::create(['name' => 'Receptionist']);

        // Create IT Officer user
        $this->itOfficer = User::create([
            'name' => 'IT Officer',
            'full_name' => 'IT Officer Test',
            'email' => 'it@test.com',
            'password' => bcrypt('password'),
            'role_id' => $itRole->id,
            'company_id' => $this->company->company_id,
        ]);

        // Create Receptionist user
        $this->receptionist = User::create([
            'name' => 'Receptionist',
            'full_name' => 'Receptionist Test',
            'email' => 'receptionist@test.com',
            'password' => bcrypt('password'),
            'role_id' => $receptionistRole->id,
            'company_id' => $this->company->company_id,
        ]);
    }

    /** @test */
    public function it_officer_can_view_requirements_page()
    {
        $this->actingAs($this->itOfficer);

        $response = $this->get(route('it-officer.requirements'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(Requirements::class);
    }

    /** @test */
    public function unauthorized_user_cannot_access_requirements_page()
    {
        $this->actingAs($this->receptionist);

        $response = $this->get(route('it-officer.requirements'));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_officer_can_create_requirement()
    {
        $this->actingAs($this->itOfficer);

        Livewire::test(Requirements::class)
            ->set('name', 'Projector')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('requirements', [
            'name' => 'Projector',
            'company_id' => $this->company->company_id,
        ]);
    }

    /** @test */
    public function it_officer_can_edit_requirement()
    {
        $this->actingAs($this->itOfficer);

        $requirement = Requirement::create([
            'name' => 'Whiteboard',
            'company_id' => $this->company->company_id,
        ]);

        Livewire::test(Requirements::class)
            ->call('openEditModal', $requirement->requirement_id)
            ->set('edit_name', 'Smart Whiteboard')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('requirements', [
            'requirement_id' => $requirement->requirement_id,
            'name' => 'Smart Whiteboard',
        ]);
    }

    /** @test */
    public function duplicate_requirement_names_are_rejected_per_company()
    {
        $this->actingAs($this->itOfficer);

        Requirement::create([
            'name' => 'Projector',
            'company_id' => $this->company->company_id,
        ]);

        Livewire::test(Requirements::class)
            ->set('name', 'Projector')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    /** @test */
    public function it_officer_can_delete_unreferenced_requirement()
    {
        $this->actingAs($this->itOfficer);

        $requirement = Requirement::create([
            'name' => 'WiFi Access',
            'company_id' => $this->company->company_id,
        ]);

        Livewire::test(Requirements::class)
            ->call('delete', $requirement->requirement_id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('requirements', [
            'requirement_id' => $requirement->requirement_id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function cannot_delete_requirement_referenced_by_booking()
    {
        $this->actingAs($this->itOfficer);

        $requirement = Requirement::create([
            'name' => 'Projector',
            'company_id' => $this->company->company_id,
        ]);

        // Create a room
        $room = Room::create([
            'room_name' => 'Meeting Room A',
            'company_id' => $this->company->company_id,
        ]);

        // Create a booking room
        $booking = BookingRoom::create([
            'company_id' => $this->company->company_id,
            'user_id' => $this->receptionist->user_id,
            'room_id' => $room->room_id,
            'date' => now()->addDays(1),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'purpose' => 'Team Meeting',
            'participant_count' => 10,
            'booking_type' => 'onsite',
            'status' => 'pending',
        ]);

        // Attach requirement to booking
        $booking->requirements()->attach($requirement->requirement_id);

        Livewire::test(Requirements::class)
            ->call('delete', $requirement->requirement_id)
            ->assertDispatched('toast');

        // Requirement should still exist
        $this->assertDatabaseHas('requirements', [
            'requirement_id' => $requirement->requirement_id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function requirement_name_is_required()
    {
        $this->actingAs($this->itOfficer);

        Livewire::test(Requirements::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    /** @test */
    public function requirement_name_cannot_exceed_max_length()
    {
        $this->actingAs($this->itOfficer);

        $longName = str_repeat('A', 256);

        Livewire::test(Requirements::class)
            ->set('name', $longName)
            ->call('save')
            ->assertHasErrors(['name']);
    }

    /** @test */
    public function search_filters_requirements()
    {
        $this->actingAs($this->itOfficer);

        Requirement::create([
            'name' => 'Projector',
            'company_id' => $this->company->company_id,
        ]);

        Requirement::create([
            'name' => 'Whiteboard',
            'company_id' => $this->company->company_id,
        ]);

        $component = Livewire::test(Requirements::class)
            ->set('search', 'Projector');

        $requirements = $component->viewData('requirements');
        
        $this->assertEquals(1, $requirements->count());
        $this->assertEquals('Projector', $requirements->first()->name);
    }

    /** @test */
    public function only_company_requirements_are_shown()
    {
        $this->actingAs($this->itOfficer);

        // Create another company
        $otherCompany = Company::create([
            'company_name' => 'Other Company',
            'company_address' => 'Other Address',
            'company_email' => 'other@company.com',
        ]);

        // Requirement for current company
        Requirement::create([
            'name' => 'Company A Projector',
            'company_id' => $this->company->company_id,
        ]);

        // Requirement for other company
        Requirement::create([
            'name' => 'Company B Projector',
            'company_id' => $otherCompany->company_id,
        ]);

        $component = Livewire::test(Requirements::class);
        $requirements = $component->viewData('requirements');

        $this->assertEquals(1, $requirements->count());
        $this->assertEquals('Company A Projector', $requirements->first()->name);
    }

    /** @test */
    public function soft_deleted_requirements_are_not_shown()
    {
        $this->actingAs($this->itOfficer);

        $activeRequirement = Requirement::create([
            'name' => 'Active Projector',
            'company_id' => $this->company->company_id,
        ]);

        $deletedRequirement = Requirement::create([
            'name' => 'Deleted Projector',
            'company_id' => $this->company->company_id,
        ]);
        $deletedRequirement->delete();

        $component = Livewire::test(Requirements::class);
        $requirements = $component->viewData('requirements');

        $this->assertEquals(1, $requirements->count());
        $this->assertEquals('Active Projector', $requirements->first()->name);
    }

    /** @test */
    public function requirements_show_booking_count()
    {
        $this->actingAs($this->itOfficer);

        $requirement = Requirement::create([
            'name' => 'Projector',
            'company_id' => $this->company->company_id,
        ]);

        $room = Room::create([
            'room_name' => 'Meeting Room A',
            'company_id' => $this->company->company_id,
        ]);

        // Create two bookings with this requirement
        for ($i = 0; $i < 2; $i++) {
            $booking = BookingRoom::create([
                'company_id' => $this->company->company_id,
                'user_id' => $this->receptionist->user_id,
                'room_id' => $room->room_id,
                'date' => now()->addDays($i + 1),
                'start_time' => '09:00',
                'end_time' => '11:00',
                'purpose' => 'Meeting ' . $i,
                'participant_count' => 10,
                'booking_type' => 'onsite',
                'status' => 'pending',
            ]);
            $booking->requirements()->attach($requirement->requirement_id);
        }

        $component = Livewire::test(Requirements::class);
        $requirements = $component->viewData('requirements');

        $this->assertEquals(2, $requirements->first()->booking_rooms_count);
    }
}
