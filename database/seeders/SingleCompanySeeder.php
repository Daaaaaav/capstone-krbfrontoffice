<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\{
    Company,
    Department,
    Role,
    User,
    Room,
    Requirement,
    Storage,
    Vehicle,
    VehicleBooking,
    VehicleBookingPhoto, 
    Delivery,
    Announcement,
    Information,
    Guestbook // BookingRoom and Ticket models removed
};

class SingleCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Start a database transaction for data integrity
        DB::transaction(function () {
            // Get current time for timestamps
            $now = Carbon::now();

            // Define the three requested companies
            $companies = [
                ['Kebun Raya Cibodas', 'krcibodas.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-cibodas.png'],
                ['Kebun Raya Bali', 'krbali.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-bali.png'],
                ['Kebun Raya Purwodadi', 'krpurwodadi.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-purwodadi.png'],
            ];

            // Default Company (often ID 1) - ensure it exists
            Company::firstOrCreate(
                ['company_id' => 1],
                ['company_name' => 'Default Company']
            );

            // Loop through each company definition
            foreach ($companies as [$companyName, $domain, $imageUrl]) {
                echo "\n🌿 Seeding {$companyName}...\n";

                // === COMPANY CREATION ===
                $company = Company::firstOrCreate(
                    ['company_name' => $companyName],
                    [
                        'company_address' => 'Jl. Raya ' . $companyName,
                        'company_email' => "info@{$domain}",
                        'image' => $imageUrl,
                    ]
                );

                $companyId = $company->company_id;

                // === ROLES ===
                $roles = [];
                foreach (['Manager', 'Receptionist'] as $r) {
                    $roles[$r] = Role::firstOrCreate(['name' => $r]);
                }

                // === DEPARTMENTS ===
                $deptNames = [
                    'IT','Finance','HRD','Marketing','Operations',
                    'General Affairs','Executive',
                    'Customer Support','Legal','Maintenance','Administration'
                ];
                $depts = [];
                foreach ($deptNames as $d) {
                    $depts[$d] = Department::firstOrCreate([
                        'company_id' => $companyId,
                        'department_name' => $d,
                    ]);
                }

                // === CORE USERS (Manager & Receptionist) ===
                $manager = User::firstOrCreate(
                    ['email' => "manager@{$domain}"],
                    [
                        'company_id' => $companyId,
                        'department_id' => $depts['Executive']->department_id,
                        'role_id' => $roles['Manager']->role_id,
                        'full_name' => "Manager {$companyName}",
                        'phone_number' => '08000000000',
                        'password' => Hash::make('superpassword'),
                        'is_agent' => 'no', 
                    ]
                );
                echo "  ✅ Manager User: {$manager->email} (managerpassword)\n";

                $receptionist = User::firstOrCreate(
                    ['email' => "receptionist@{$domain}"],
                    [
                        'company_id' => $companyId,
                        'department_id' => $depts['Administration']->department_id,
                        'role_id' => $roles['Receptionist']->role_id,
                        'full_name' => "Receptionist {$companyName}",
                        'phone_number' => '087812345678',
                        'password' => Hash::make('receppassword'),
                        'is_agent' => 'no',
                    ]
                );
                echo "  ✅ Receptionist User: {$receptionist->email} (receppassword)\n";


                // === GENERAL USERS ===
                $users = collect([$manager, $receptionist]);
                $agents = collect();

                echo "  ✅ Seeded core users\n";

                // Memanggil fungsi untuk data aset dan aktivitas spesifik perusahaan
                $this->seedAssetsAndActivities($companyId, $companyName, $depts, $roles, collect(), $users, collect(), $receptionist, $now);
            }
        });
    }

    // --- Helper Functions ---

    /**
     * Helper function to seed asset and activity data for a specific company.
     * Ticket and Booking Room logic removed.
     */
    protected function seedAssetsAndActivities($companyId, $companyName, $depts, $roles, $admins, $users, $agents, $receptionist, $now)
    {
        // Set random seed based on company ID for consistent demo data per company
        mt_srand($companyId * 999); 
        $daysBack = 1825; // 5 Years

        // ===== ROOMS & REQUIREMENTS (Data is seeded but no booking logic remains) =====
        $rooms = collect(['Garuda','Merak','Cendrawasih','Aula','Elang'])
            ->map(fn($r) => Room::firstOrCreate(['company_id'=>$companyId,'room_name'=>"Ruang {$r}"]));
        echo "  ✅ Seeded Rooms\n";

        $requirementsList = collect();
        foreach (['Projector & Screen','Whiteboard','Coffee Break','Lunch Set','Sound System'] as $req) {
            $requirementsList->push(Requirement::firstOrCreate(['company_id'=>$companyId,'name'=>$req]));
        }
        echo "  ✅ Seeded Requirements\n";

        // ===== STORAGES & VEHICLES =====
        foreach ([['S-01','Rak Dokumen'],['S-02','Loker Paket'],['S-03','Gudang ATK']] as [$code,$name]) {
            Storage::firstOrCreate(['company_id'=>$companyId,'code'=>$code],['name'=>$name]);
        }
        echo "  ✅ Seeded Storages\n";

        $vehicles = collect();
        foreach ([
            ['Avanza','car',2022],['Innova','car',2021],['Honda Vario','motorcycle',2023],['Carry PickUp','pickup',2019]
        ] as [$name,$type,$year]) {
            $plate = 'B ' . rand(1000,9999) . ' ' . Str::upper(Str::random(3));
            $vehicles->push(Vehicle::firstOrCreate(
                ['plate_number'=>$plate],
                ['company_id'=>$companyId,'name'=>$name,'category'=>$type,'year'=>$year]
            ));
        }
        echo "  ✅ Seeded Vehicles\n";

        // ===== DELIVERIES =====
        for ($i=1; $i<=50; $i++) {
            Delivery::create([
                'company_id'=>$companyId,
                'receptionist_id'=>$receptionist->user_id,
                'item_name'=>"Paket {$companyName} #{$i}",
                'type'=>Arr::random(['package','document','invoice','etc']),
                'nama_pengirim'=>Arr::random(['JNE','TIKI','SiCepat','Pos Indonesia']),
                'nama_penerima'=>$users->random()->full_name,
                'status'=>Arr::random(['pending','stored','taken','delivered']),
                'direction' => Arr::random(['taken', 'deliver']),
                'pengiriman'=>$now->copy()->subDays(rand(0, $daysBack)), 
            ]);
        }
        echo "  ✅ Seeded Deliveries\n";

        // ===== ANNOUNCEMENTS/GUESTBOOK =====
        for ($i=1; $i<=30; $i++) {
            $randomDate = $now->copy()->subDays(rand(0, $daysBack)); 

            Announcement::create([
                'company_id'=>$companyId,
                'description'=>"📢 Pengumuman {$companyName} #{$i}",
                'event_at'=>$randomDate->copy()->addDays(rand(2,10)),
                'created_at'=>$randomDate,
            ]);

            Information::create([
                'company_id'=>$companyId,
                'department_id'=>Arr::random($depts)->department_id,
                'description'=>"📰 Info khusus {$companyName} #{$i}",
                'event_at'=>$randomDate->copy()->addDays(rand(1,5)),
                'created_at'=>$randomDate,
            ]);

            Guestbook::create([
                'company_id'=>$companyId,
                'department_id'=>Arr::random($depts)->department_id,
                'date'=>$randomDate->toDateString(),
                'jam_in'=>sprintf("%02d:%02d:00", rand(8,10), rand(0,59)),
                'jam_out'=>sprintf("%02d:%02d:00", rand(14,17), rand(0,59)),
                'name'=>"Tamu #{$i}",
                'instansi'=>"Instansi {$i}",
                'keperluan'=>"Meeting",
                'petugas_penjaga'=>$receptionist->full_name,
                'created_at'=>$randomDate,
            ]);
        }
        echo "  ✅ Seeded Announcements, Information, and Guestbooks\n";

        // ===== VEHICLE BOOKINGS =====
        // The 4 vehicles are: index 0 & 1 = "clean" (never late_return),
        // index 2 & 3 = "overdue" (may have late_return bookings).
        if ($vehicles->isNotEmpty()) {
            $cleanVehicleIds  = $vehicles->take(2)->pluck('vehicle_id')->toArray();
            $overdueVehicleIds = $vehicles->slice(2)->pluck('vehicle_id')->toArray();

            // Statuses allowed for clean vehicles (no late_return)
            $cleanStatuses   = ['pending', 'approved', 'on_progress', 'returned', 'completed', 'rejected'];
            // Statuses for overdue vehicles (late_return included)
            $overdueStatuses = ['pending', 'approved', 'on_progress', 'returned', 'completed', 'rejected', 'late_return'];

            foreach (range(1, 80) as $i) {
                $user    = $users->random();
                $vehicle = $vehicles->random();
                $start   = $now->copy()->subDays(rand(0, $daysBack))->hour(rand(8,14));
                $end     = $start->copy()->addHours(rand(2,6));

                $purposeType = Arr::random(['dinas', 'operasional', 'antar_jemput', 'lainnya']);

                // Pick status pool based on whether this vehicle is "clean"
                if (in_array($vehicle->vehicle_id, $cleanVehicleIds)) {
                    $status = Arr::random($cleanStatuses);
                } else {
                    $status = Arr::random($overdueStatuses);
                }

                $booking = VehicleBooking::create([
                    'vehicle_id'   => $vehicle->vehicle_id,
                    'company_id'   => $companyId,
                    'department_id'=> $user->department_id,
                    'user_id'      => $user->user_id,
                    'borrower_name'=> $user->full_name,
                    'start_at'     => $start,
                    'end_at'       => $end,
                    'purpose'      => "Keperluan " . ucfirst($purposeType) . " #{$i}",
                    'purpose_type' => $purposeType,
                    'destination'  => Arr::random(['Bogor','Jakarta','Bali','Purwodadi']),
                    'odd_even_area'=> Arr::random(['tidak', 'ganjil', 'genap']),
                    'status'       => $status,
                    'terms_agreed' => 1,
                    'created_at'   => $start,
                    'updated_at'   => $start,
                ]);

                if (in_array($status, ['on_progress', 'returned', 'completed', 'late_return'])) {
                    VehicleBookingPhoto::create([
                        'vehiclebooking_id' => $booking->vehiclebooking_id,
                        'user_id'           => $user->user_id,
                        'photo_type'        => 'before',
                        'photo_path'        => 'vehicle_photos/demo_sample_before_' . $i . '.jpg',
                        'created_at'        => $start,
                    ]);
                }
                if ($status === 'completed') {
                    VehicleBookingPhoto::create([
                        'vehiclebooking_id' => $booking->vehiclebooking_id,
                        'user_id'           => $user->user_id,
                        'photo_type'        => 'after',
                        'photo_path'        => 'vehicle_photos/demo_sample_after_' . $i . '.jpg',
                        'created_at'        => $end,
                    ]);
                }
            }
        }
        echo "  ✅ Seeded Vehicle Bookings\n";
    }
}