<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\{
    Company,
    Department,
    Role,
    User
};

class AccountSeeder extends Seeder
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

            foreach ($companies as [$companyName, $domain]) {
                echo "\n🌿 Seeding Accounts for {$companyName}...\n";

                $company = Company::firstOrCreate(
                    ['company_name' => $companyName],
                    [
                        'company_address' => 'Jl. Raya ' . $companyName,
                        'company_email' => "info@{$domain}",
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

                User::firstOrCreate(
                    ['email' => "manager@{$domain}"],
                    [
                        'company_id' => $companyId,
                        'department_id' => $depts['Executive']->department_id,
                        'role_id' => $roles['Manager']->role_id,
                        'full_name' => "Manager {$companyName}",
                        'phone_number' => '080000000001',
                        'password' => Hash::make('test123'),
                    ]
                );

                User::firstOrCreate(
                    ['email' => "receptionist@{$domain}"],
                    [
                        'company_id' => $companyId,
                        'department_id' => $depts['Administration']->department_id,
                        'role_id' => $roles['Receptionist']->role_id,
                        'full_name' => "Receptionist {$companyName}",
                        'phone_number' => '087812345678',
                        'password' => Hash::make('receppassword'),
                    ]
                );
            }
        });
    }
}
