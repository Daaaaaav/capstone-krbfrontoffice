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
    Delivery,
    Guestbook,
    BookingRoom,
    IdType,
    VisitorLanyard,
};

/**
 * Database Seeder for KRB System
 * 
 * IMPORTANT: Shared Resource Strategy
 * ===================================
 * 
 * This seeder treats ID Types and Visitor Lanyards as SHARED RESOURCES across companies.
 * 
 * Shared Resource Fallback Logic:
 * 1. First, attempt to retrieve resources associated with the current company
 * 2. If none exist, fallback to any existing resources from other companies
 * 3. If no resources exist anywhere, throw a clear error with actionable guidance
 * 
 * CRITICAL RULES:
 * - The seeder NEVER creates new ID Types or Visitor Lanyards
 * - The seeder NEVER modifies existing resource records
 * - The seeder NEVER changes the company_id ownership of shared resources
 * - The seeder only REFERENCES existing resources by their IDs
 * 
 * Resource Ownership:
 * - ID Types and Visitor Lanyards maintain their original company_id
 * - When a company without its own resources uses shared resources, the original
 *   ownership remains unchanged
 * - Guestbook entries simply reference the resource IDs
 * 
 * Manual Configuration Required:
 * - IT Officers must manually configure ID Types via: IT Officer > Manage ID Types
 * - IT Officers must manually configure Visitor Lanyards via: IT Officer > Manage Visitor Lanyards
 * - These resources should be configured BEFORE running the seeder
 * 
 * Lanyard Availability:
 * - Historical guestbook entries (all checked out) may reuse the same lanyard on different dates
 * - The seeder does NOT modify lanyard status/availability
 * - Lanyard availability is managed by the application's checkout/return logic
 * 
 * Same-Day Visitor Uniqueness:
 * - A visitor name may appear on different dates
 * - A visitor name may NOT appear twice on the same date
 * 
 * @see App\Models\IdType
 * @see App\Models\VisitorLanyard
 * @see App\Models\Guestbook
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AISettingsSeeder::class);

        DB::transaction(function () {
            $now = Carbon::now();

            $companies = [
                ['Kebun Raya Bogor', 'krbogor.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-bogor.png'],
                ['Kebun Raya Bali', 'krbali.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-bali.png'],
                ['Kebun Raya Cibodas', 'krcibodas.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-cibodas.png'],
                ['Kebun Raya Purwodadi', 'krpurwodadi.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-purwodadi.png'],
            ];

            Company::firstOrCreate(
                ['company_id' => 1],
                ['company_name' => 'Default Company']
            );

            foreach ($companies as [$companyName, $domain, $imageUrl]) {
                echo "\n🌿 Seeding {$companyName}...\n";

                $company = Company::firstOrCreate(
                    ['company_name' => $companyName],
                    [
                        'company_address' => 'Jl. Raya ' . $companyName,
                        'company_email' => "info@{$domain}",
                        'image' => $imageUrl,
                    ]
                );

                $companyId = $company->company_id;

                $roles = [];
                foreach (['Manager', 'Receptionist', 'IT Officer'] as $r) {
                    $roles[$r] = Role::firstOrCreate(['name' => $r]);
                }

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

                $users = collect();

                    $customUsers = [
                        [
                            'full_name' => 'Davina Amarina',
                            'email' => 'davina.managerkrb@gmail.com',
                            'phone_number' => '081234567891',
                            'role' => 'Manager',
                            'department' => 'IT',
                        ],
                        [
                            'full_name' => 'Clania Elmymora',
                            'email' => 'clania.receptionist@gmail.com',
                            'phone_number' => '081234567892',
                            'role' => 'Receptionist',
                            'department' => 'IT',
                        ],
                         [
                            'full_name' => 'John IT',
                            'email' => 'itofficerkrb@gmail.com',
                            'phone_number' => '081234567893',
                            'role' => 'IT Officer',
                            'department' => 'IT',
                        ],
                        [
                            'full_name' => 'Madoka Higuchi',
                            'email' => 'davinad828@gmail.com',
                            'phone_number' => '081234567890',
                            'role' => 'Manager',
                            'department' => 'Executive',
                        ],
                        [
                            'full_name' => 'Ginko Momose',
                            'email' => 'scarletstormsubs@gmail.com',
                            'phone_number' => '087812345678',
                            'role' => 'Receptionist',
                            'department' => 'Administration',
                        ],
                        [
                            'full_name' => 'Toru Asakura',
                            'email' => 'thetrashamari@gmail.com',
                            'phone_number' => '087812345678',
                            'role' => 'Receptionist',
                            'department' => 'Administration',
                        ],
                        [
                            'full_name' => 'Izumi Katsuragi',
                            'email' => 'danzakuduro263@gmail.com',
                            'phone_number' => '08000000000',
                            'role' => 'Manager',
                            'department' => 'Executive',
                        ],
                        [
                            'full_name' => 'Setsuna Yuki',
                            'email' => 'experteasesolutionsmail@gmail.com',
                            'phone_number' => '08000000000',
                            'role' => 'Manager',
                            'department' => 'Executive',
                        ],
                            ];

                            foreach ($customUsers as $data) {
                                $user = User::firstOrCreate(
                                    ['email' => $data['email']],
                                    [
                                        'company_id' => $companyId,
                                        'department_id' => $depts[$data['department']]->department_id,
                                        'role_id' => $roles[$data['role']]->role_id,
                                        'full_name' => $data['full_name'],
                                        'phone_number' => $data['phone_number'],
                                        'password' => Hash::make('test123'), 
                                        'is_agent' => 'no',
                                    ]
                                );

                                $users->push($user);

                                if ($data['role'] === 'Receptionist') {
                                    $receptionist = $user;
                                }
                            }

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

                $users = collect([$manager, $receptionist]);
                $agents = collect();

                // Retrieve existing IdTypes and VisitorLanyards before seeding activities
                // These are shared resources across companies - prefer company-specific, fallback to global
                $idTypes = $this->getSharedIdTypes($companyId, $companyName);
                $visitorLanyards = $this->getSharedVisitorLanyards($companyId, $companyName);

                $this->seedAssetsAndActivities($companyId, $companyName, $depts, $roles, collect(), $users, collect(), $receptionist, $now, $idTypes, $visitorLanyards);
            }
        });
    }

    /**
     * Retrieve ID Types as a shared resource.
     * 
     * Shared resource fallback strategy:
     * 1. First, try to retrieve ID Types associated with the current company.
     * 2. If none exist, fallback to any existing ID Types from the database (shared resources).
     * 3. If no ID Types exist anywhere, throw a clear error.
     * 
     * This method does NOT create new ID Types.
     * IT Officers must manually configure ID Types through: IT Officer > Manage ID Types.
     *
     * @param int $companyId
     * @param string $companyName
     * @return \Illuminate\Support\Collection
     * @throws \RuntimeException if no ID Types exist anywhere in the database
     */
    protected function getSharedIdTypes(int $companyId, string $companyName)
    {
        // Step 1: Try company-specific ID Types first
        $idTypes = IdType::where('company_id', $companyId)->get();

        if ($idTypes->isNotEmpty()) {
            echo "  ✅ Using " . $idTypes->count() . " company-specific ID Type(s).\n";
            return $idTypes;
        }

        // Step 2: Fallback to shared ID Types from other companies
        echo "  ⚠️  No company-specific ID Types found.\n";
        $idTypes = IdType::query()->get();

        if ($idTypes->isEmpty()) {
            throw new \RuntimeException(
                "\n❌ No ID Types exist anywhere in the database.\n"
                . "Please manually configure at least one ID Type through:\n"
                . "  IT Officer > Manage ID Types\n"
                . "Example ID Types: KTP, SIM, Passport, Student ID, Employee ID, etc.\n"
            );
        }

        echo "     Using " . $idTypes->count() . " existing shared ID Type(s) from the database.\n";
        return $idTypes;
    }

    /**
     * Retrieve Visitor Lanyards as a shared resource.
     * 
     * Shared resource fallback strategy:
     * 1. First, try to retrieve Visitor Lanyards associated with the current company.
     * 2. If none exist, fallback to any existing Visitor Lanyards from the database (shared resources).
     * 3. If no Visitor Lanyards exist anywhere, throw a clear error.
     * 
     * This method does NOT create new Visitor Lanyards.
     * IT Officers must manually configure Visitor Lanyards through: IT Officer > Manage Visitor Lanyards.
     *
     * @param int $companyId
     * @param string $companyName
     * @return \Illuminate\Support\Collection
     * @throws \RuntimeException if no Visitor Lanyards exist anywhere in the database
     */
    protected function getSharedVisitorLanyards(int $companyId, string $companyName)
    {
        // Step 1: Try company-specific Visitor Lanyards first
        $lanyards = VisitorLanyard::where('company_id', $companyId)->get();

        if ($lanyards->isNotEmpty()) {
            echo "  ✅ Using " . $lanyards->count() . " company-specific Visitor Lanyard(s).\n";
            return $lanyards;
        }

        // Step 2: Fallback to shared Visitor Lanyards from other companies
        echo "  ⚠️  No company-specific Visitor Lanyards found.\n";
        $lanyards = VisitorLanyard::query()->get();

        if ($lanyards->isEmpty()) {
            throw new \RuntimeException(
                "\n❌ No Visitor Lanyards exist anywhere in the database.\n"
                . "Please manually configure Visitor Lanyards through:\n"
                . "  IT Officer > Manage Visitor Lanyards\n"
                . "Example lanyards: Lanyard-001, Lanyard-002, Lanyard-003, etc.\n"
            );
        }

        echo "     Using " . $lanyards->count() . " existing shared Visitor Lanyard(s) from the database.\n";
        return $lanyards;
    }

    protected function seedAssetsAndActivities($companyId, $companyName, $depts, $roles, $admins, $users, $agents, $receptionist, $now, $idTypes, $visitorLanyards)
    {
        mt_srand($companyId * 999);
        $daysBack = 1825; // 5 Years

        $rooms = collect(['Garuda','Merak','Cendrawasih','Aula','Elang'])
            ->map(fn($r) => Room::firstOrCreate(['company_id'=>$companyId,'room_name'=>"Ruang {$r}"]));

        $requirementsList = collect();
        foreach (['Projector & Screen','Whiteboard','Coffee Break','Lunch Set','Sound System'] as $req) {
            $requirementsList->push(Requirement::firstOrCreate(['company_id'=>$companyId,'name'=>$req]));
        }

        foreach ([['S-01','Rak Dokumen'],['S-02','Loker Paket'],['S-03','Gudang ATK']] as [$code,$name]) {
            Storage::firstOrCreate(['company_id'=>$companyId,'code'=>$code],['name'=>$name]);
        }

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

        $guestNames = [
            'Budi Santoso','Siti Rahayu','Ahmad Fauzi','Dewi Lestari','Eko Prasetyo',
            'Fitri Handayani','Gunawan Wibowo','Hana Pertiwi','Irfan Maulana','Joko Susilo',
            'Kartini Wahyu','Lukman Hakim','Maya Sari','Nanda Putra','Oki Setiawan',
            'Putri Anggraini','Rizky Firmansyah','Sari Indah','Teguh Santoso','Umar Bakri',
            'Vina Kusuma','Wahyu Hidayat','Xena Pratiwi','Yudi Nugroho','Zahra Amelia',
            'Arif Rahman','Bella Safitri','Candra Wijaya','Dina Permata','Edi Kurniawan',
        ];

        $instansiList = [
            'PT Maju Bersama','CV Karya Mandiri','Dinas Pertanian Kota Bogor',
            'Universitas Indonesia','Institut Pertanian Bogor','Kementerian LHK',
            'BRIN','Yayasan Hijau Nusantara','PT Agro Lestari','Balai Penelitian Tanaman',
            'Pemerintah Kota Bogor','Sekolah Alam Bogor','PT Wisata Alam Indonesia',
            'Komunitas Pecinta Tanaman','Lembaga Konservasi Nasional',
        ];

        $keperluanList = [
            'Kunjungan penelitian','Rapat koordinasi','Studi banding',
            'Pengambilan data lapangan','Konsultasi teknis','Kunjungan wisata edukasi',
            'Pertemuan kemitraan','Pengiriman dokumen','Survei lokasi',
            'Kunjungan dinas','Seminar dan workshop','Magang mahasiswa',
        ];

        $holidays = [
            '01-01', // New Year
            '02-10', // approximate Imlek
            '03-29', // approximate Nyepi 
            '04-18', // approximate Good Friday 
            '05-01', // Labour Day
            '05-29', // approximate Ascension of Isa Almasih 
            '06-01', // Pancasila Day
            '08-17', // Independence Day
            '12-25', // Christmas
            '12-26', // Post=Christmas
        ];

        // Validate that we have ID Types and Visitor Lanyards before proceeding
        // These are shared resources - they may belong to this company or be borrowed from others
        if ($idTypes->isEmpty()) {
            throw new \RuntimeException(
                "Cannot seed Guestbook entries: No ID Types available. "
                . "Please create ID Types manually before running the seeder."
            );
        }

        if ($visitorLanyards->isEmpty()) {
            throw new \RuntimeException(
                "Cannot seed Guestbook entries: No Visitor Lanyards available. "
                . "Please create Visitor Lanyards manually before running the seeder."
            );
        }

        $guestCounter = 1;
        $seedDays     = 730;
        
        // Track names used on each day to prevent same-day duplicates
        $dailyNameTracker = [];

        for ($dayOffset = $seedDays; $dayOffset >= 0; $dayOffset--) {
            $date    = $now->copy()->subDays($dayOffset)->startOfDay();
            $dow     = $date->dayOfWeek; // 0=Sunday, 6=Saturday
            $mmdd    = $date->format('m-d');
            $isHoliday = in_array($mmdd, $holidays);
            $dateKey = $date->toDateString();

            // Initialize daily tracker for this date
            $dailyNameTracker[$dateKey] = [];

            if ($isHoliday) {
                $count = rand(0, 1);
            } elseif ($dow === 0 || $dow === 6) {
                // Weekend: very few visitors
                $count = rand(0, 3);
            } elseif ($dow === 5) {
                // Friday: slightly lower than other weekdays
                $count = rand(2, 7);
            } else {
                // Mon–Thu: peak days; gentle upward trend over time
                $trendBonus = (int) floor(($seedDays - $dayOffset) / 180); 
                $count = rand(3, 10) + $trendBonus;
            }

            for ($v = 0; $v < $count; $v++) {
                $jamInHour   = rand(8, 14);
                $jamInMin    = rand(0, 59);
                $jamOutHour  = $jamInHour + rand(1, 3);
                $jamOutHour  = min($jamOutHour, 17);

                $entryTime = $date->copy()->setTime($jamInHour, $jamInMin, 0);

                // Select a name that hasn't been used today
                $attempts = 0;
                $maxAttempts = count($guestNames) * 2; // Prevent infinite loop
                do {
                    $selectedName = Arr::random($guestNames);
                    $attempts++;
                    
                    // If we've tried too many times, just use a unique indexed name
                    if ($attempts >= $maxAttempts) {
                        $selectedName = $guestNames[($guestCounter - 1) % count($guestNames)] . ' (' . $v . ')';
                        break;
                    }
                } while (in_array($selectedName, $dailyNameTracker[$dateKey]));

                // Mark this name as used for today
                $dailyNameTracker[$dateKey][] = $selectedName;

                // Select shared resources (may belong to this company or another company)
                // Randomly select an existing ID Type (shared resource)
                $idType = $idTypes->random();
                
                // Randomly select an existing Visitor Lanyard (shared resource)
                // For historical backlog data, we reuse lanyards since all visitors have already checked out
                // The lanyard's company_id ownership is preserved - we only reference it
                $lanyard = $visitorLanyards->random();

                Guestbook::create([
                    'company_id'     => $companyId,
                    'department_id'  => Arr::random($depts)->department_id,
                    'date'           => $date->toDateString(),
                    'jam_in'         => sprintf('%02d:%02d:00', $jamInHour, $jamInMin),
                    'jam_out'        => sprintf('%02d:%02d:00', $jamOutHour, rand(0, 59)),
                    'name'           => $selectedName,
                    'instansi'       => Arr::random($instansiList),
                    'keperluan'      => Arr::random($keperluanList),
                    'petugas_penjaga'=> $receptionist->full_name,
                    'id_type_id'     => $idType->id,
                    'visitor_lanyard_id' => $lanyard->id,
                    'created_at'     => $entryTime,
                    'updated_at'     => $entryTime,
                ]);

                $guestCounter++;
            }
        }

        echo "  ✅ Created " . ($guestCounter - 1) . " guestbook entries over {$seedDays} days using shared ID Types and Visitor Lanyards.\n";

        foreach (range(1, 80) as $i) {
            $booker = $users->random();
            $room = $rooms->random();
            $startDate = $now->copy()->subDays(rand(0, $daysBack));
            $endDate = $startDate->copy()->addHours(rand(1,3));

            $bookingType = Arr::random(['meeting', 'online_meeting']);
            $onlineProvider = null;
            $onlineUrl = null;
            $onlineCode = null;
            $onlinePass = null;

            if ($bookingType === 'online_meeting') {
                $onlineProvider = Arr::random(['zoom', 'google_meet']);
                $onlineUrl = 'https://' . $onlineProvider . '.us/j/' . Str::random(10);
                $onlineCode = Str::random(10);
                $onlinePass = Str::random(8);
            }

            $bookingRoom = BookingRoom::create([
                'room_id'=>$room->room_id,
                'company_id'=>$companyId,
                'user_id'=>$booker->user_id,
                'department_id'=>$booker->department_id,
                'meeting_title'=>"Rapat {$companyName} #{$i}",
                'date'=>$startDate->toDateString(),
                'number_of_attendees'=>rand(3,30),
                'start_time'=>$startDate,
                'end_time'=>$endDate,
                'is_approve'=>1,

                // TYPE AND ONLINE DETAILS
                'booking_type' => $bookingType,
                'online_provider' => $onlineProvider,
                'online_meeting_url' => $onlineUrl,
                'online_meeting_code' => $onlineCode,
                'online_meeting_password' => $onlinePass,

                'status'=>'approved',
                'approved_by' => $receptionist->user_id,

                'requestinformation' => Arr::random(['request', null]),

                'created_at' => $startDate,
                'updated_at' => $startDate,
            ]);

            $randomRequirements = $requirementsList->random(rand(0, 3));
            foreach ($randomRequirements as $req) {
                DB::table('booking_requirements')->insert([
                    'bookingroom_id' => $bookingRoom->bookingroom_id,
                    'requirement_id' => $req->requirement_id,
                    'created_at' => $startDate,
                    'updated_at' => $startDate,
                ]);
            }
        }
        
        if ($vehicles->isNotEmpty()) {
            foreach (range(1, 80) as $i) {
                $user = $users->random();
                $vehicle = $vehicles->random();
                $start = $now->copy()->subDays(rand(0, $daysBack))->hour(rand(8,14));
                $end = $start->copy()->addHours(rand(2,6));

                $purposeType = Arr::random(['dinas', 'operasional', 'antar_jemput', 'lainnya']);
                $status = Arr::random(['pending', 'approved', 'on_progress', 'returned', 'completed', 'rejected', 'cancelled']);

                $booking = VehicleBooking::create([
                    'vehicle_id' => $vehicle->vehicle_id,
                    'company_id' => $companyId,
                    'department_id' => $user->department_id,
                    'user_id' => $user->user_id,
                    'borrower_name' => $user->full_name,
                    'start_at' => $start,
                    'end_at' => $end,
                    'purpose' => "Keperluan " . ucfirst($purposeType) . " #{$i}",
                    'purpose_type' => $purposeType,
                    'destination' => Arr::random(['Bogor','Jakarta','Bali','Purwodadi']),
                    'odd_even_area' => Arr::random(['tidak', 'ganjil', 'genap']),
                    'status' => $status,
                    'terms_agreed' => 1,
                    'created_at' => $start,
                    'updated_at' => $start,
                ]);
            }
        }

    }
}
