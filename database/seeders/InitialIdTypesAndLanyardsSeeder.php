<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\IdType;
use App\Models\VisitorLanyard;

/**
 * Initial seeder for ID Types and Visitor Lanyards.
 * 
 * This seeder should be run BEFORE the DatabaseSeeder to create
 * the required ID Types and Visitor Lanyards for each company.
 * 
 * Usage:
 *   php artisan db:seed --class=InitialIdTypesAndLanyardsSeeder
 * 
 * Or run both in sequence:
 *   php artisan db:seed --class=InitialIdTypesAndLanyardsSeeder
 *   php artisan db:seed
 */
class InitialIdTypesAndLanyardsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "🔧 Seeding Initial ID Types and Visitor Lanyards...\n\n";

        // Get all companies except the default company (ID 1)
        $companies = Company::where('company_id', '!=', 1)->get();

        if ($companies->isEmpty()) {
            echo "⚠️  No companies found (excluding default). Please seed companies first.\n";
            return;
        }

        foreach ($companies as $company) {
            echo "📍 Seeding for {$company->company_name} (ID: {$company->company_id})...\n";
            
            $this->seedIdTypes($company->company_id, $company->company_name);
            $this->seedVisitorLanyards($company->company_id, $company->company_name);
            
            echo "\n";
        }

        echo "✅ Initial ID Types and Visitor Lanyards seeding completed!\n";
    }

    /**
     * Seed ID Types for a company.
     *
     * @param int $companyId
     * @param string $companyName
     */
    protected function seedIdTypes($companyId, $companyName): void
    {
        $idTypeNames = [
            'KTP (Indonesian ID Card)',
            'SIM (Driver License)',
            'Kartu Mahasiswa/Pelajar (Student ID)',
            'KITAS/KITAP (Foreign Identity Card)',
            'Paspor (Passport)',
        ];

        $created = 0;
        $existing = 0;

        foreach ($idTypeNames as $typeName) {
            $idType = IdType::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'id_type_name' => $typeName,
                ]
            );

            if ($idType->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        echo "  ✅ ID Types: {$created} created, {$existing} already exist.\n";
    }

    /**
     * Seed Visitor Lanyards for a company.
     *
     * @param int $companyId
     * @param string $companyName
     */
    protected function seedVisitorLanyards($companyId, $companyName): void
    {
        $lanyardCount = 15; // Create 15 lanyards per company
        $created = 0;
        $existing = 0;

        for ($i = 1; $i <= $lanyardCount; $i++) {
            $lanyardName = sprintf('Lanyard-%03d', $i);
            
            $lanyard = VisitorLanyard::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'lanyard_name' => $lanyardName,
                ],
                [
                    'status' => 1, // Available by default
                ]
            );

            if ($lanyard->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        echo "  ✅ Visitor Lanyards: {$created} created, {$existing} already exist.\n";
    }
}
