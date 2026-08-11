<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\{
    Company,
    Department,
    Role,
    User,
    Room,
    Storage,
    Vehicle,
};

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AISettingsSeeder::class);

        DB::transaction(function () {
            $companies = [
                ['Kebun Raya Bogor', 'krbogor.id'],
                ['Kebun Raya Bali', 'krbali.id'],
                ['Kebun Raya Cibodas', 'krcibodas.id'],
                ['Kebun Raya Purwodadi', 'krpurwodadi.id'],
            ];

            Company::firstOrCreate(
                ['company_id' => 1],
                ['company_name' => 'Default Company']
            );

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
            ];

            foreach ($companies as [$companyName, $domain]) {
                echo "\n🌿 Seeding {$companyName}...\n";

                $company = Company::firstOrCreate(
                    ['company_name' => $companyName],
                    [
                        'company_address' => 'Jl. Raya ' . $companyName,
                        'company_email' => "info@{$domain}",
                    ]
                );

                $companyId = $company->company_id;

                $roles = [];

                foreach (['Manager', 'Receptionist', 'IT Officer'] as $roleName) {
                    $roles[$roleName] = Role::firstOrCreate([
                        'name' => $roleName,
                    ]);
                }

                $deptNames = [
                    'IT',
                    'Finance',
                    'HRD',
                    'Marketing',
                    'Operations',
                    'General Affairs',
                    'Executive',
                    'Customer Support',
                    'Legal',
                    'Maintenance',
                    'Administration',
                ];

                $depts = [];

                foreach ($deptNames as $departmentName) {
                    $depts[$departmentName] = Department::firstOrCreate([
                        'company_id' => $companyId,
                        'department_name' => $departmentName,
                    ]);
                }

                foreach ($customUsers as $data) {
                    User::firstOrCreate(
                        ['email' => $data['email']],
                        [
                            'company_id' => $companyId,
                            'department_id' => $depts[$data['department']]->department_id,
                            'role_id' => $roles[$data['role']]->role_id,
                            'full_name' => $data['full_name'],
                            'phone_number' => $data['phone_number'],
                            'password' => Hash::make('test123'),
                        ]
                    );
                }

                $this->seedPrimaryData($companyId);

                echo "   ✅ Primary data seeded.\n";
                echo "   👥 Custom users: 3\n";
            }
        });
    }

    protected function seedPrimaryData(int $companyId): void
    {
        foreach ([
            'Garuda',
            'Merak',
            'Cendrawasih',
            'Aula',
            'Elang',
        ] as $roomName) {
            Room::firstOrCreate([
                'company_id' => $companyId,
                'room_name' => "Ruang {$roomName}",
            ]);
        }

        foreach ([
            ['S-01', 'Rak Dokumen'],
            ['S-02', 'Loker Paket'],
            ['S-03', 'Gudang ATK'],
        ] as [$code, $name]) {
            Storage::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $code,
                ],
                [
                    'name' => $name,
                ]
            );
        }

        $vehicles = [
            ['Avanza', 'car', 2022, 'B 1001 KRB'],
            ['Innova', 'car', 2021, 'B 1002 KRB'],
            ['Honda Vario', 'motorcycle', 2023, 'B 1003 KRB'],
            ['Carry PickUp', 'pickup', 2019, 'B 1004 KRB'],
        ];

        foreach ($vehicles as [$name, $category, $year, $plate]) {
            Vehicle::firstOrCreate(
                [
                    'plate_number' => $plate,
                ],
                [
                    'company_id' => $companyId,
                    'name' => $name,
                    'category' => $category,
                    'year' => $year,
                ]
            );
        }
    }
}
