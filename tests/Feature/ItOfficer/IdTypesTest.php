<?php

namespace Tests\Feature\ItOfficer;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\IdType;
use App\Models\Guestbook;
use App\Livewire\Pages\ItOfficer\IdTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class IdTypesTest extends TestCase
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
    public function it_officer_can_view_id_types_page()
    {
        $this->actingAs($this->itOfficer);

        $response = $this->get(route('it-officer.id-types'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(IdTypes::class);
    }

    /** @test */
    public function unauthorized_user_cannot_access_id_types_page()
    {
        $this->actingAs($this->receptionist);

        $response = $this->get(route('it-officer.id-types'));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_officer_can_create_id_type()
    {
        $this->actingAs($this->itOfficer);

        Livewire::test(IdTypes::class)
            ->set('id_type_name', 'KTP')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('id_types', [
            'id_type_name' => 'KTP',
            'company_id' => $this->company->company_id,
        ]);
    }

    /** @test */
    public function it_officer_can_edit_id_type()
    {
        $this->actingAs($this->itOfficer);

        $idType = IdType::create([
            'id_type_name' => 'Passport',
            'company_id' => $this->company->company_id,
        ]);

        Livewire::test(IdTypes::class)
            ->call('openEditModal', $idType->id)
            ->set('edit_id_type_name', 'Passport Updated')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('id_types', [
            'id' => $idType->id,
            'id_type_name' => 'Passport Updated',
        ]);
    }

    /** @test */
    public function duplicate_id_type_names_are_rejected_per_company()
    {
        $this->actingAs($this->itOfficer);

        IdType::create([
            'id_type_name' => 'KTP',
            'company_id' => $this->company->company_id,
        ]);

        Livewire::test(IdTypes::class)
            ->set('id_type_name', 'KTP')
            ->call('save')
            ->assertHasErrors(['id_type_name']);
    }

    /** @test */
    public function it_officer_can_delete_unreferenced_id_type()
    {
        $this->actingAs($this->itOfficer);

        $idType = IdType::create([
            'id_type_name' => 'Driver License',
            'company_id' => $this->company->company_id,
        ]);

        Livewire::test(IdTypes::class)
            ->call('delete', $idType->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('id_types', [
            'id' => $idType->id,
        ]);
    }

    /** @test */
    public function cannot_delete_id_type_referenced_by_guestbook()
    {
        $this->actingAs($this->itOfficer);

        $idType = IdType::create([
            'id_type_name' => 'KTP',
            'company_id' => $this->company->company_id,
        ]);

        // Create a guestbook entry using this ID type
        Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Officer',
            'id_type_id' => $idType->id,
            'visitor_count' => 1,
        ]);

        Livewire::test(IdTypes::class)
            ->call('delete', $idType->id)
            ->assertDispatched('toast');

        // ID Type should still exist
        $this->assertDatabaseHas('id_types', [
            'id' => $idType->id,
        ]);
    }

    /** @test */
    public function id_type_name_is_required()
    {
        $this->actingAs($this->itOfficer);

        Livewire::test(IdTypes::class)
            ->set('id_type_name', '')
            ->call('save')
            ->assertHasErrors(['id_type_name' => 'required']);
    }

    /** @test */
    public function search_filters_id_types()
    {
        $this->actingAs($this->itOfficer);

        IdType::create([
            'id_type_name' => 'KTP',
            'company_id' => $this->company->company_id,
        ]);

        IdType::create([
            'id_type_name' => 'Passport',
            'company_id' => $this->company->company_id,
        ]);

        $component = Livewire::test(IdTypes::class)
            ->set('search', 'KTP');

        $idTypes = $component->viewData('idTypes');
        
        $this->assertEquals(1, $idTypes->count());
        $this->assertEquals('KTP', $idTypes->first()->id_type_name);
    }

    /** @test */
    public function only_company_id_types_are_shown()
    {
        $this->actingAs($this->itOfficer);

        // Create another company
        $otherCompany = Company::create([
            'company_name' => 'Other Company',
            'company_address' => 'Other Address',
            'company_email' => 'other@company.com',
        ]);

        // ID Type for current company
        IdType::create([
            'id_type_name' => 'KTP',
            'company_id' => $this->company->company_id,
        ]);

        // ID Type for other company
        IdType::create([
            'id_type_name' => 'Other KTP',
            'company_id' => $otherCompany->company_id,
        ]);

        $component = Livewire::test(IdTypes::class);
        $idTypes = $component->viewData('idTypes');

        $this->assertEquals(1, $idTypes->count());
        $this->assertEquals('KTP', $idTypes->first()->id_type_name);
    }
}
