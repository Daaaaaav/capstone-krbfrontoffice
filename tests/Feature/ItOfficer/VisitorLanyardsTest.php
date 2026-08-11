<?php

namespace Tests\Feature\ItOfficer;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\VisitorLanyard;
use App\Models\Guestbook;
use App\Livewire\Pages\ItOfficer\VisitorLanyards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class VisitorLanyardsTest extends TestCase
{
    use RefreshDatabase;

    protected User $itOfficer;
    protected User $manager;
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
        $managerRole = Role::create(['name' => 'Manager']);

        // Create IT Officer user
        $this->itOfficer = User::create([
            'name' => 'IT Officer',
            'full_name' => 'IT Officer Test',
            'email' => 'it@test.com',
            'password' => bcrypt('password'),
            'role_id' => $itRole->id,
            'company_id' => $this->company->company_id,
        ]);

        // Create Manager user
        $this->manager = User::create([
            'name' => 'Manager',
            'full_name' => 'Manager Test',
            'email' => 'manager@test.com',
            'password' => bcrypt('password'),
            'role_id' => $managerRole->id,
            'company_id' => $this->company->company_id,
        ]);
    }

    /** @test */
    public function it_officer_can_view_visitor_lanyards_page()
    {
        $this->actingAs($this->itOfficer);

        $response = $this->get(route('it-officer.visitor-lanyards'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(VisitorLanyards::class);
    }

    /** @test */
    public function unauthorized_user_cannot_access_visitor_lanyards_page()
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('it-officer.visitor-lanyards'));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_officer_can_create_visitor_lanyard()
    {
        $this->actingAs($this->itOfficer);

        Livewire::test(VisitorLanyards::class)
            ->set('lanyard_name', 'Lanyard #001')
            ->set('status', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('visitor_lanyards', [
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);
    }

    /** @test */
    public function it_officer_can_edit_visitor_lanyard()
    {
        $this->actingAs($this->itOfficer);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Blue Lanyard',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        Livewire::test(VisitorLanyards::class)
            ->call('openEditModal', $lanyard->id)
            ->set('edit_lanyard_name', 'Green Lanyard')
            ->set('edit_status', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('visitor_lanyards', [
            'id' => $lanyard->id,
            'lanyard_name' => 'Green Lanyard',
            'status' => 0,
        ]);
    }

    /** @test */
    public function duplicate_lanyard_names_are_rejected_per_company()
    {
        $this->actingAs($this->itOfficer);

        VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        Livewire::test(VisitorLanyards::class)
            ->set('lanyard_name', 'Lanyard #001')
            ->call('save')
            ->assertHasErrors(['lanyard_name']);
    }

    /** @test */
    public function it_officer_can_toggle_lanyard_status()
    {
        $this->actingAs($this->itOfficer);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        Livewire::test(VisitorLanyards::class)
            ->call('toggleStatus', $lanyard->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('visitor_lanyards', [
            'id' => $lanyard->id,
            'status' => 0,
        ]);
    }

    /** @test */
    public function it_officer_can_delete_unreferenced_lanyard()
    {
        $this->actingAs($this->itOfficer);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        Livewire::test(VisitorLanyards::class)
            ->call('delete', $lanyard->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('visitor_lanyards', [
            'id' => $lanyard->id,
        ]);
    }

    /** @test */
    public function cannot_delete_lanyard_assigned_to_active_visitor()
    {
        $this->actingAs($this->itOfficer);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 0,
        ]);

        // Create an active guestbook entry (no checkout time)
        Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null, // Still active
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Officer',
            'visitor_lanyard_id' => $lanyard->id,
            'visitor_count' => 1,
        ]);

        Livewire::test(VisitorLanyards::class)
            ->call('delete', $lanyard->id)
            ->assertDispatched('toast');

        // Lanyard should still exist
        $this->assertDatabaseHas('visitor_lanyards', [
            'id' => $lanyard->id,
        ]);
    }

    /** @test */
    public function cannot_delete_lanyard_with_historical_references()
    {
        $this->actingAs($this->itOfficer);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        // Create a checked-out guestbook entry (historical reference)
        Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now()->subDays(1),
            'jam_in' => '09:00',
            'jam_out' => '17:00', // Checked out
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Officer',
            'visitor_lanyard_id' => $lanyard->id,
            'visitor_count' => 1,
        ]);

        Livewire::test(VisitorLanyards::class)
            ->call('delete', $lanyard->id)
            ->assertDispatched('toast');

        // Lanyard should still exist due to historical reference
        $this->assertDatabaseHas('visitor_lanyards', [
            'id' => $lanyard->id,
        ]);
    }

    /** @test */
    public function lanyard_name_is_required()
    {
        $this->actingAs($this->itOfficer);

        Livewire::test(VisitorLanyards::class)
            ->set('lanyard_name', '')
            ->call('save')
            ->assertHasErrors(['lanyard_name' => 'required']);
    }

    /** @test */
    public function search_filters_lanyards()
    {
        $this->actingAs($this->itOfficer);

        VisitorLanyard::create([
            'lanyard_name' => 'Red Lanyard',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        VisitorLanyard::create([
            'lanyard_name' => 'Blue Lanyard',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        $component = Livewire::test(VisitorLanyards::class)
            ->set('search', 'Red');

        $lanyards = $component->viewData('lanyards');
        
        $this->assertEquals(1, $lanyards->count());
        $this->assertEquals('Red Lanyard', $lanyards->first()->lanyard_name);
    }

    /** @test */
    public function only_company_lanyards_are_shown()
    {
        $this->actingAs($this->itOfficer);

        // Create another company
        $otherCompany = Company::create([
            'company_name' => 'Other Company',
            'company_address' => 'Other Address',
            'company_email' => 'other@company.com',
        ]);

        // Lanyard for current company
        VisitorLanyard::create([
            'lanyard_name' => 'Company A Lanyard',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        // Lanyard for other company
        VisitorLanyard::create([
            'lanyard_name' => 'Company B Lanyard',
            'company_id' => $otherCompany->company_id,
            'status' => 1,
        ]);

        $component = Livewire::test(VisitorLanyards::class);
        $lanyards = $component->viewData('lanyards');

        $this->assertEquals(1, $lanyards->count());
        $this->assertEquals('Company A Lanyard', $lanyards->first()->lanyard_name);
    }

    // ========================================
    // LIFECYCLE TESTS
    // ========================================

    /** @test */
    public function lanyard_becomes_unavailable_when_assigned_to_visitor()
    {
        $this->actingAs($this->manager);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 1, // Available
        ]);

        // Simulate assignment through GuestbookForm
        Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null,
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Manager',
            'visitor_lanyard_id' => $lanyard->id,
            'visitor_count' => 1,
        ]);

        // Mark lanyard as unavailable (as done in GuestbookForm)
        $lanyard->update(['status' => 0]);

        $this->assertDatabaseHas('visitor_lanyards', [
            'id' => $lanyard->id,
            'status' => 0,
        ]);

        // Verify isCurrentlyAssigned returns true
        $this->assertTrue($lanyard->fresh()->isCurrentlyAssigned());
    }

    /** @test */
    public function lanyard_becomes_available_after_visitor_checkout()
    {
        $this->actingAs($this->manager);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 0, // Currently assigned
        ]);

        $guestbook = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null, // Active visitor
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Manager',
            'visitor_lanyard_id' => $lanyard->id,
            'visitor_count' => 1,
            'qr_status' => 'ongoing',
        ]);

        // Simulate checkout
        $guestbook->update([
            'jam_out' => now()->format('H:i'),
            'qr_status' => 'completed',
        ]);

        // Return lanyard to available status (as done in fixed checkout methods)
        if ($guestbook->visitor_lanyard_id) {
            $lanyardToReturn = VisitorLanyard::find($guestbook->visitor_lanyard_id);
            if ($lanyardToReturn) {
                $lanyardToReturn->update(['status' => 1]);
            }
        }

        $this->assertDatabaseHas('visitor_lanyards', [
            'id' => $lanyard->id,
            'status' => 1, // Now available
        ]);

        // Verify isCurrentlyAssigned returns false
        $this->assertFalse($lanyard->fresh()->isCurrentlyAssigned());
    }

    /** @test */
    public function historical_guestbook_record_preserves_lanyard_reference_after_checkout()
    {
        $this->actingAs($this->manager);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 0,
        ]);

        $guestbook = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null,
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Manager',
            'visitor_lanyard_id' => $lanyard->id,
            'visitor_count' => 1,
        ]);

        // Checkout
        $guestbook->update(['jam_out' => now()->format('H:i')]);
        $lanyard->update(['status' => 1]);

        // Historical record must still reference the lanyard
        $this->assertDatabaseHas('guestbooks', [
            'guestbook_id' => $guestbook->guestbook_id,
            'visitor_lanyard_id' => $lanyard->id,
            'jam_out' => $guestbook->jam_out,
        ]);

        // But lanyard is now available
        $this->assertEquals(1, $lanyard->fresh()->status);
    }

    /** @test */
    public function available_lanyards_appear_in_guestbook_form_dropdown()
    {
        $this->actingAs($this->manager);

        $availableLanyard = VisitorLanyard::create([
            'lanyard_name' => 'Available Lanyard',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        $unavailableLanyard = VisitorLanyard::create([
            'lanyard_name' => 'Assigned Lanyard',
            'company_id' => $this->company->company_id,
            'status' => 0,
        ]);

        // Query as done in GuestbookForm mount
        $availableLanyards = VisitorLanyard::where('company_id', $this->company->company_id)
            ->where('status', 1)
            ->orderBy('lanyard_name')
            ->get();

        $this->assertCount(1, $availableLanyards);
        $this->assertEquals('Available Lanyard', $availableLanyards->first()->lanyard_name);
    }

    /** @test */
    public function returned_lanyard_becomes_selectable_in_dropdown_again()
    {
        $this->actingAs($this->manager);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 0, // Currently assigned
        ]);

        // Before checkout - not in dropdown
        $beforeCheckout = VisitorLanyard::where('company_id', $this->company->company_id)
            ->where('status', 1)
            ->get();
        $this->assertCount(0, $beforeCheckout);

        // Checkout happens - lanyard returned
        $lanyard->update(['status' => 1]);

        // After checkout - appears in dropdown
        $afterCheckout = VisitorLanyard::where('company_id', $this->company->company_id)
            ->where('status', 1)
            ->get();
        $this->assertCount(1, $afterCheckout);
        $this->assertEquals('Lanyard #001', $afterCheckout->first()->lanyard_name);
    }

    /** @test */
    public function lanyard_assigned_to_active_visitor_remains_unavailable()
    {
        $this->actingAs($this->manager);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 0,
        ]);

        $activeGuest = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null, // Still active
            'name' => 'Active Guest',
            'email' => 'active@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Manager',
            'visitor_lanyard_id' => $lanyard->id,
            'visitor_count' => 1,
        ]);

        // Verify it's correctly unavailable
        $this->assertEquals(0, $lanyard->status);
        $this->assertTrue($lanyard->isCurrentlyAssigned());

        // Should not appear in available list
        $available = VisitorLanyard::where('company_id', $this->company->company_id)
            ->where('status', 1)
            ->get();
        $this->assertCount(0, $available);
    }

    /** @test */
    public function cannot_assign_same_lanyard_to_multiple_active_visitors()
    {
        $this->actingAs($this->manager);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        // First assignment
        Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null,
            'name' => 'Guest 1',
            'email' => 'guest1@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Manager',
            'visitor_lanyard_id' => $lanyard->id,
            'visitor_count' => 1,
        ]);

        $lanyard->update(['status' => 0]);

        // Verify lanyard is not available for second assignment
        $this->assertEquals(0, $lanyard->fresh()->status);
        $this->assertTrue($lanyard->fresh()->isCurrentlyAssigned());

        // Lanyard should not appear in available dropdown
        $availableLanyards = VisitorLanyard::where('company_id', $this->company->company_id)
            ->where('status', 1)
            ->get();

        $this->assertFalse($availableLanyards->contains('id', $lanyard->id));
    }

    /** @test */
    public function it_officer_management_page_displays_correct_availability_status()
    {
        $this->actingAs($this->itOfficer);

        $availableLanyard = VisitorLanyard::create([
            'lanyard_name' => 'Available',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        $unavailableLanyard = VisitorLanyard::create([
            'lanyard_name' => 'Unavailable',
            'company_id' => $this->company->company_id,
            'status' => 0,
        ]);

        $component = Livewire::test(VisitorLanyards::class);
        $lanyards = $component->viewData('lanyards');

        $available = $lanyards->firstWhere('id', $availableLanyard->id);
        $unavailable = $lanyards->firstWhere('id', $unavailableLanyard->id);

        $this->assertEquals(1, $available->status);
        $this->assertEquals(0, $unavailable->status);
    }

    /** @test */
    public function complete_lifecycle_flow_from_assignment_to_checkout()
    {
        $this->actingAs($this->manager);

        // Step 1: Create available lanyard
        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 1,
        ]);

        $this->assertEquals(1, $lanyard->status);
        $this->assertFalse($lanyard->isCurrentlyAssigned());

        // Step 2: Assign to visitor
        $guestbook = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null,
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Manager',
            'visitor_lanyard_id' => $lanyard->id,
            'visitor_count' => 1,
            'qr_status' => 'ongoing',
        ]);

        $lanyard->update(['status' => 0]);

        $this->assertEquals(0, $lanyard->fresh()->status);
        $this->assertTrue($lanyard->fresh()->isCurrentlyAssigned());

        // Step 3: Visitor checks out
        $guestbook->update([
            'jam_out' => now()->format('H:i'),
            'qr_status' => 'completed',
        ]);

        $returnedLanyard = VisitorLanyard::find($guestbook->visitor_lanyard_id);
        $returnedLanyard->update(['status' => 1]);

        // Step 4: Verify final state
        $this->assertEquals(1, $lanyard->fresh()->status);
        $this->assertFalse($lanyard->fresh()->isCurrentlyAssigned());

        // Historical record preserved
        $this->assertDatabaseHas('guestbooks', [
            'guestbook_id' => $guestbook->guestbook_id,
            'visitor_lanyard_id' => $lanyard->id,
        ]);

        // Lanyard available for new assignment
        $availableForReuse = VisitorLanyard::where('id', $lanyard->id)
            ->where('status', 1)
            ->exists();
        $this->assertTrue($availableForReuse);
    }

    /** @test */
    public function cannot_toggle_lanyard_to_available_while_assigned_to_active_visitor()
    {
        $this->actingAs($this->itOfficer);

        $lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 0,
        ]);

        // Create active visitor assignment
        Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null,
            'name' => 'Active Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Officer',
            'visitor_lanyard_id' => $lanyard->id,
            'visitor_count' => 1,
        ]);

        // Try to toggle status - should be prevented
        Livewire::test(VisitorLanyards::class)
            ->call('toggleStatus', $lanyard->id)
            ->assertDispatched('toast');

        // Status should remain unavailable
        $this->assertDatabaseHas('visitor_lanyards', [
            'id' => $lanyard->id,
            'status' => 0,
        ]);
    }
}
