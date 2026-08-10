<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\VisitorLanyard;
use App\Models\Guestbook;
use App\Models\GuestbookQrCode;
use App\Livewire\Pages\Receptionist\GuestbookStatus;
use App\Livewire\Pages\Receptionist\GuestbookHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Carbon\Carbon;

class GuestbookCheckoutLanyardTest extends TestCase
{
    use RefreshDatabase;

    protected User $receptionist;
    protected Company $company;
    protected VisitorLanyard $lanyard;

    protected function setUp(): void
    {
        parent::setUp();

        // Create company
        $this->company = Company::create([
            'company_name' => 'Test Company',
            'company_address' => 'Test Address',
            'company_email' => 'test@company.com',
        ]);

        // Create receptionist role and user
        $receptionistRole = Role::create(['name' => 'Receptionist']);

        $this->receptionist = User::create([
            'name' => 'Receptionist',
            'full_name' => 'Receptionist Test',
            'email' => 'receptionist@test.com',
            'password' => bcrypt('password'),
            'role_id' => $receptionistRole->id,
            'company_id' => $this->company->company_id,
        ]);

        // Create a lanyard
        $this->lanyard = VisitorLanyard::create([
            'lanyard_name' => 'Test Lanyard #001',
            'company_id' => $this->company->company_id,
            'status' => 0, // Assigned
        ]);
    }

    /** @test */
    public function guestbook_status_checkout_returns_lanyard()
    {
        $this->actingAs($this->receptionist);

        $guestbook = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null,
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Receptionist',
            'visitor_lanyard_id' => $this->lanyard->id,
            'visitor_count' => 1,
            'qr_status' => 'ongoing',
        ]);

        // Create QR codes
        $qrToken = GuestbookQrCode::generateTokenBatch(1);
        GuestbookQrCode::create([
            'guestbook_id' => $guestbook->guestbook_id,
            'qr_token' => $qrToken[0],
            'visitor_number' => 1,
        ]);

        // Verify lanyard is unavailable before checkout
        $this->assertEquals(0, $this->lanyard->fresh()->status);

        // Perform checkout
        Livewire::test(GuestbookStatus::class)
            ->call('checkOutNow', $guestbook->guestbook_id)
            ->assertDispatched('toast');

        // Verify guestbook is checked out
        $this->assertDatabaseHas('guestbooks', [
            'guestbook_id' => $guestbook->guestbook_id,
            'qr_status' => 'completed',
        ]);
        $this->assertNotNull($guestbook->fresh()->jam_out);

        // Verify lanyard is now available
        $this->assertEquals(1, $this->lanyard->fresh()->status);

        // Verify historical reference is preserved
        $this->assertDatabaseHas('guestbooks', [
            'guestbook_id' => $guestbook->guestbook_id,
            'visitor_lanyard_id' => $this->lanyard->id,
        ]);
    }

    /** @test */
    public function guestbook_history_set_jam_keluar_now_returns_lanyard()
    {
        $this->actingAs($this->receptionist);

        $guestbook = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now()->subDays(1),
            'jam_in' => '09:00',
            'jam_out' => null,
            'name' => 'Historical Guest',
            'email' => 'historical@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Receptionist',
            'visitor_lanyard_id' => $this->lanyard->id,
            'visitor_count' => 1,
        ]);

        // Verify lanyard is unavailable
        $this->assertEquals(0, $this->lanyard->fresh()->status);

        // Use setJamKeluarNow
        Livewire::test(GuestbookHistory::class)
            ->call('setJamKeluarNow', $guestbook->guestbook_id)
            ->assertDispatched('toast');

        // Verify checkout
        $this->assertNotNull($guestbook->fresh()->jam_out);

        // Verify lanyard is available
        $this->assertEquals(1, $this->lanyard->fresh()->status);
    }

    /** @test */
    public function guestbook_history_save_edit_with_jam_out_returns_lanyard()
    {
        $this->actingAs($this->receptionist);

        $guestbook = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null, // Active
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'phone_number' => '12345',
            'instansi' => 'Test Company',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Receptionist',
            'visitor_lanyard_id' => $this->lanyard->id,
            'visitor_count' => 1,
        ]);

        // Create QR codes
        $qrToken = GuestbookQrCode::generateTokenBatch(1);
        GuestbookQrCode::create([
            'guestbook_id' => $guestbook->guestbook_id,
            'qr_token' => $qrToken[0],
            'visitor_number' => 1,
        ]);

        // Verify lanyard is unavailable
        $this->assertEquals(0, $this->lanyard->fresh()->status);

        // Edit and add jam_out
        Livewire::test(GuestbookHistory::class)
            ->call('openEdit', $guestbook->guestbook_id)
            ->set('edit', [
                'date' => $guestbook->date->format('Y-m-d'),
                'jam_in' => $guestbook->jam_in,
                'jam_out' => '17:00', // Setting checkout time
                'name' => $guestbook->name,
                'email' => $guestbook->email,
                'phone_number' => $guestbook->phone_number,
                'instansi' => $guestbook->instansi,
                'keperluan' => $guestbook->keperluan,
                'petugas_penjaga' => $guestbook->petugas_penjaga,
                'visitor_count' => $guestbook->visitor_count,
                'department_id' => $guestbook->department_id,
                'user_id' => $guestbook->user_id,
            ])
            ->call('saveEdit')
            ->assertDispatched('toast');

        // Verify jam_out is set
        $this->assertEquals('17:00', $guestbook->fresh()->jam_out);

        // Verify lanyard is now available
        $this->assertEquals(1, $this->lanyard->fresh()->status);
    }

    /** @test */
    public function guestbook_history_save_edit_clearing_jam_out_makes_lanyard_unavailable()
    {
        $this->actingAs($this->receptionist);

        $guestbook = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => '17:00', // Already checked out
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'phone_number' => '12345',
            'instansi' => 'Test Company',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Receptionist',
            'visitor_lanyard_id' => $this->lanyard->id,
            'visitor_count' => 1,
        ]);

        // Create QR codes
        $qrToken = GuestbookQrCode::generateTokenBatch(1);
        GuestbookQrCode::create([
            'guestbook_id' => $guestbook->guestbook_id,
            'qr_token' => $qrToken[0],
            'visitor_number' => 1,
        ]);

        // Manually set lanyard as available (post-checkout)
        $this->lanyard->update(['status' => 1]);
        $this->assertEquals(1, $this->lanyard->fresh()->status);

        // Edit and clear jam_out (re-opening the visit)
        Livewire::test(GuestbookHistory::class)
            ->call('openEdit', $guestbook->guestbook_id)
            ->set('edit', [
                'date' => $guestbook->date->format('Y-m-d'),
                'jam_in' => $guestbook->jam_in,
                'jam_out' => '', // Clearing checkout time
                'name' => $guestbook->name,
                'email' => $guestbook->email,
                'phone_number' => $guestbook->phone_number,
                'instansi' => $guestbook->instansi,
                'keperluan' => $guestbook->keperluan,
                'petugas_penjaga' => $guestbook->petugas_penjaga,
                'visitor_count' => $guestbook->visitor_count,
                'department_id' => $guestbook->department_id,
                'user_id' => $guestbook->user_id,
            ])
            ->call('saveEdit')
            ->assertDispatched('toast');

        // Verify jam_out is cleared
        $this->assertNull($guestbook->fresh()->jam_out);

        // Verify lanyard is now unavailable again
        $this->assertEquals(0, $this->lanyard->fresh()->status);
    }

    /** @test */
    public function guestbook_scan_controller_checkout_returns_lanyard()
    {
        $guestbook = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null,
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Receptionist',
            'visitor_lanyard_id' => $this->lanyard->id,
            'visitor_count' => 1,
            'qr_status' => 'ongoing',
        ]);

        // Create a single QR code
        $qrToken = GuestbookQrCode::generateTokenBatch(1);
        $qrCode = GuestbookQrCode::create([
            'guestbook_id' => $guestbook->guestbook_id,
            'qr_token' => $qrToken[0],
            'visitor_number' => 1,
        ]);

        // Verify lanyard is unavailable
        $this->assertEquals(0, $this->lanyard->fresh()->status);

        // Simulate QR scan checkout via controller endpoint
        $response = $this->postJson('/api/guestbook/checkout-scan', [
            'qr_content' => 'GUESTBOOK-CHECKOUT:' . $qrCode->qr_token,
            'guestbook_id' => $guestbook->guestbook_id,
        ]);

        $response->assertJson([
            'success' => true,
            'all_done' => true,
        ]);

        // Verify guestbook is completed
        $this->assertDatabaseHas('guestbooks', [
            'guestbook_id' => $guestbook->guestbook_id,
            'qr_status' => 'completed',
        ]);
        $this->assertNotNull($guestbook->fresh()->jam_out);

        // Verify lanyard is now available
        $this->assertEquals(1, $this->lanyard->fresh()->status);
    }

    /** @test */
    public function checkout_without_lanyard_doesnt_cause_errors()
    {
        $this->actingAs($this->receptionist);

        // Create guestbook without lanyard
        $guestbook = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null,
            'name' => 'Test Guest',
            'email' => 'guest@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Receptionist',
            'visitor_lanyard_id' => null, // No lanyard
            'visitor_count' => 1,
            'qr_status' => 'ongoing',
        ]);

        // Create QR codes
        $qrToken = GuestbookQrCode::generateTokenBatch(1);
        GuestbookQrCode::create([
            'guestbook_id' => $guestbook->guestbook_id,
            'qr_token' => $qrToken[0],
            'visitor_number' => 1,
        ]);

        // Perform checkout - should not throw error
        Livewire::test(GuestbookStatus::class)
            ->call('checkOutNow', $guestbook->guestbook_id)
            ->assertDispatched('toast');

        // Verify checkout succeeded
        $this->assertNotNull($guestbook->fresh()->jam_out);
    }

    /** @test */
    public function multiple_checkouts_with_same_lanyard_work_correctly()
    {
        $this->actingAs($this->receptionist);

        // First visitor
        $guestbook1 = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '09:00',
            'jam_out' => null,
            'name' => 'Guest 1',
            'email' => 'guest1@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Receptionist',
            'visitor_lanyard_id' => $this->lanyard->id,
            'visitor_count' => 1,
            'qr_status' => 'ongoing',
        ]);

        GuestbookQrCode::create([
            'guestbook_id' => $guestbook1->guestbook_id,
            'qr_token' => 'token1',
            'visitor_number' => 1,
        ]);

        $this->assertEquals(0, $this->lanyard->fresh()->status);

        // Checkout first visitor
        Livewire::test(GuestbookStatus::class)
            ->call('checkOutNow', $guestbook1->guestbook_id);

        $this->assertEquals(1, $this->lanyard->fresh()->status);

        // Second visitor gets same lanyard
        $guestbook2 = Guestbook::create([
            'company_id' => $this->company->company_id,
            'date' => now(),
            'jam_in' => '14:00',
            'jam_out' => null,
            'name' => 'Guest 2',
            'email' => 'guest2@test.com',
            'keperluan' => 'Meeting',
            'petugas_penjaga' => 'Receptionist',
            'visitor_lanyard_id' => $this->lanyard->id,
            'visitor_count' => 1,
            'qr_status' => 'ongoing',
        ]);

        GuestbookQrCode::create([
            'guestbook_id' => $guestbook2->guestbook_id,
            'qr_token' => 'token2',
            'visitor_number' => 1,
        ]);

        // Mark unavailable for second visitor
        $this->lanyard->update(['status' => 0]);
        $this->assertEquals(0, $this->lanyard->fresh()->status);

        // Checkout second visitor
        Livewire::test(GuestbookStatus::class)
            ->call('checkOutNow', $guestbook2->guestbook_id);

        $this->assertEquals(1, $this->lanyard->fresh()->status);

        // Both historical records preserved
        $this->assertDatabaseHas('guestbooks', [
            'guestbook_id' => $guestbook1->guestbook_id,
            'visitor_lanyard_id' => $this->lanyard->id,
        ]);
        $this->assertDatabaseHas('guestbooks', [
            'guestbook_id' => $guestbook2->guestbook_id,
            'visitor_lanyard_id' => $this->lanyard->id,
        ]);
    }
}
