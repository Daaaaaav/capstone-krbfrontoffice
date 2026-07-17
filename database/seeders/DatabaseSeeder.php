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
    Guestbook,
    BookingRoom,
    Ticket,
    TicketAssignment,
    TicketAttachment,
    TicketComment,
    // TicketHistory
};

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed AI settings first (idempotent — safe to re-run)
        $this->call(AISettingsSeeder::class);

        DB::transaction(function () {
            $now = Carbon::now();

            $companies = [
                ['Kebun Raya Bogor', 'krbogor.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-bogor.png'],
                ['Kebun Raya Bali', 'krbali.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-bali.png'],
                ['Kebun Raya Cibodas', 'krcibodas.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-cibodas.png'],
                ['Kebun Raya Purwodadi', 'krpurwodadi.id', 'https://tiketkebunraya.id/assets/images/kebun-raya-purwodadi.png'],
            ];

            // Default Company
            Company::firstOrCreate(
                ['company_id' => 1],
                ['company_name' => 'Default Company']
            );

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

                $users = collect();

                // === CUSTOM USERS ===
                    $customUsers = [
                         [
                            'full_name' => 'Vani',
                            'email' => 'arvani527@gmail.com',
                            'phone_number' => '081234567891',
                            'role' => 'Receptionist',
                            'department' => 'Administration',
                        ],
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
                            'full_name' => 'Gilang Receptionist',
                            'email' => 'gilangatha999@gmail.com',
                            'phone_number' => '081234567891',
                            'role' => 'Receptionist',
                            'department' => 'Administration',
                        ],
                        [
                            'full_name' => 'Gilang Manager',
                            'email' => 'gilangatha@gmail.com',
                            'phone_number' => '081234567891',
                            'role' => 'Manager',
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
                            'full_name' => 'Izumi Katsuragi 2',
                            'email' => 'izumikatsuragi@gmail.com',
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

                                // Add to system collections
                                $users->push($user);

                                // If receptionist, optionally override default receptionist
                                if ($data['role'] === 'Receptionist') {
                                    $receptionist = $user;
                                }
                            }

                // === CORE USERS ===
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

                // === GENERAL USERS & AGENTS ===
                $users = collect([$manager, $receptionist]);
                $agents = collect();

                $this->seedAssetsAndActivities($companyId, $companyName, $depts, $roles, collect(), $users, collect(), $receptionist, $now);
            }
        });
    }

    protected function seedAssetsAndActivities($companyId, $companyName, $depts, $roles, $admins, $users, $agents, $receptionist, $now)
    {
        mt_srand($companyId * 999);
        $daysBack = 1825; // 5 Years

        $demoImages = [
            'https://res.cloudinary.com/demo/image/upload/sample.jpg',
            'https://res.cloudinary.com/demo/image/upload/dog.jpg',
            'https://res.cloudinary.com/demo/image/upload/cat.jpg',
            'https://res.cloudinary.com/demo/image/upload/girl.jpg',
            'https://res.cloudinary.com/demo/image/upload/car.jpg',
        ];

        // ===== ROOMS & REQUIREMENTS =====
        $rooms = collect(['Garuda','Merak','Cendrawasih','Aula','Elang'])
            ->map(fn($r) => Room::firstOrCreate(['company_id'=>$companyId,'room_name'=>"Ruang {$r}"]));

        $requirementsList = collect();
        foreach (['Projector & Screen','Whiteboard','Coffee Break','Lunch Set','Sound System'] as $req) {
            $requirementsList->push(Requirement::firstOrCreate(['company_id'=>$companyId,'name'=>$req]));
        }

        // ===== STORAGES & VEHICLES =====
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

        // ===== ANNOUNCEMENTS =====
        for ($i = 1; $i <= 30; $i++) {
            $randomDate = $now->copy()->subDays(rand(0, $daysBack));

            Announcement::create([
                'company_id'  => $companyId,
                'description' => "📢 Pengumuman {$companyName} #{$i}",
                'event_at'    => $randomDate->copy()->addDays(rand(2, 10)),
                'created_at'  => $randomDate,
            ]);

            Information::create([
                'company_id'    => $companyId,
                'department_id' => Arr::random($depts)->department_id,
                'description'   => "📰 Info khusus {$companyName} #{$i}",
                'event_at'      => $randomDate->copy()->addDays(rand(1, 5)),
                'created_at'    => $randomDate,
            ]);
        }

        // ===== GUESTBOOK — dense daily visitor data for LSTM training =====
        // Generates realistic visitor patterns across 2 years:
        //   - Weekdays: 3–12 visitors/day  (higher Mon–Thu)
        //   - Weekends: 0–3 visitors/day
        //   - Indonesian public holidays: 0–1 visitors
        //   - Monthly trend: slight growth over time
        // This gives ~1,000+ entries with clear temporal patterns the LSTM can learn.

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

        // Indonesian public holidays (approximate, recurring annually)
        $holidays = [
            '01-01', // New Year
            '02-10', // Imlek (approx)
            '03-29', // Nyepi (approx)
            '04-18', // Good Friday (approx)
            '05-01', // Labour Day
            '05-29', // Ascension (approx)
            '06-01', // Pancasila Day
            '08-17', // Independence Day
            '12-25', // Christmas
            '12-26', // Boxing Day
        ];

        $guestCounter = 1;
        $seedDays     = 730; // 2 years of daily data

        for ($dayOffset = $seedDays; $dayOffset >= 0; $dayOffset--) {
            $date    = $now->copy()->subDays($dayOffset)->startOfDay();
            $dow     = $date->dayOfWeek; // 0=Sun, 6=Sat
            $mmdd    = $date->format('m-d');
            $isHoliday = in_array($mmdd, $holidays);

            // Determine visitor count for this day
            if ($isHoliday) {
                $count = rand(0, 1);
            } elseif ($dow === 0 || $dow === 6) {
                // Weekend — very few visitors
                $count = rand(0, 3);
            } elseif ($dow === 5) {
                // Friday — slightly lower
                $count = rand(2, 7);
            } else {
                // Mon–Thu — peak days
                // Add a gentle upward trend over time
                $trendBonus = (int) floor(($seedDays - $dayOffset) / 180); // +1 every ~6 months
                $count = rand(3, 10) + $trendBonus;
            }

            // Create individual guestbook entries for each visitor that day
            for ($v = 0; $v < $count; $v++) {
                $jamInHour   = rand(8, 14);
                $jamInMin    = rand(0, 59);
                $jamOutHour  = $jamInHour + rand(1, 3);
                $jamOutHour  = min($jamOutHour, 17);

                $entryTime = $date->copy()->setTime($jamInHour, $jamInMin, 0);

                Guestbook::create([
                    'company_id'     => $companyId,
                    'department_id'  => Arr::random($depts)->department_id,
                    'date'           => $date->toDateString(),
                    'jam_in'         => sprintf('%02d:%02d:00', $jamInHour, $jamInMin),
                    'jam_out'        => sprintf('%02d:%02d:00', $jamOutHour, rand(0, 59)),
                    'name'           => $guestNames[($guestCounter - 1) % count($guestNames)],
                    'instansi'       => Arr::random($instansiList),
                    'keperluan'      => Arr::random($keperluanList),
                    'petugas_penjaga'=> $receptionist->full_name,
                    'created_at'     => $entryTime,
                    'updated_at'     => $entryTime,
                ]);

                $guestCounter++;
            }
        }

        echo "  ✅ Created {$guestCounter} guestbook entries over {$seedDays} days.\n";

        // ===== ROOM BOOKINGS (Offline & Online) =====
        foreach (range(1, 80) as $i) {
            $booker = $users->random();
            $room = $rooms->random();
            $startDate = $now->copy()->subDays(rand(0, $daysBack));
            $endDate = $startDate->copy()->addHours(rand(1,3));

            // DECIDE TYPE: Meeting or Online Meeting
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

                // requestinformation applies to both types
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

        // ===== VEHICLE BOOKINGS =====
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

                if (in_array($status, ['on_progress', 'returned', 'completed'])) {
                    VehicleBookingPhoto::create([
                        'vehiclebooking_id' => $booking->vehiclebooking_id,
                        'user_id' => $user->user_id,
                        'photo_type' => 'before',
                        'photo_path' => 'vehicle_photos/demo_sample_before_' . $i . '.jpg',
                        'created_at' => $start,
                    ]);
                }
                if ($status == 'completed') {
                    VehicleBookingPhoto::create([
                        'vehiclebooking_id' => $booking->vehiclebooking_id,
                        'user_id' => $user->user_id,
                        'photo_type' => 'after',
                        'photo_path' => 'vehicle_photos/demo_sample_after_' . $i . '.jpg',
                        'created_at' => $end,
                    ]);
                }
            }
        }

    }
}
