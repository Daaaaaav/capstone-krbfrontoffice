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
}
