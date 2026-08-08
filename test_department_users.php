<?php

/**
 * Test script to verify Department->users relationship fix
 * Run with: php test_department_users.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "\n=== Department Users Relationship Test ===\n\n";

try {
    // Get first company
    $companyId = 2; // Kebun Raya Bogor from seeder
    
    echo "Testing for Company ID: {$companyId}\n\n";
    
    // Get departments
    $departments = Department::where('company_id', $companyId)
        ->orderBy('department_name')
        ->get();
    
    echo "Found " . $departments->count() . " departments\n\n";
    
    foreach ($departments as $dept) {
        echo "Department: {$dept->department_name} (ID: {$dept->department_id})\n";
        
        // Test direct relationship
        $usersViaRelation = $dept->users()->count();
        echo "  - Users via relationship: {$usersViaRelation}\n";
        
        // Test with role filter
        $managersReceptionists = $dept->users()
            ->whereHas('role', function ($q) {
                $q->whereIn('roles.name', ['Manager', 'Receptionist']);
            })
            ->count();
        echo "  - Manager/Receptionist users: {$managersReceptionists}\n";
        
        // Test direct query
        $usersDirectQuery = User::where('company_id', $companyId)
            ->where('department_id', $dept->department_id)
            ->whereHas('role', function ($q) {
                $q->whereIn('roles.name', ['Manager', 'Receptionist']);
            })
            ->count();
        echo "  - Direct query result: {$usersDirectQuery}\n";
        
        // List users
        $users = $dept->users()
            ->whereHas('role', function ($q) {
                $q->whereIn('roles.name', ['Manager', 'Receptionist']);
            })
            ->with('role')
            ->get();
        
        if ($users->count() > 0) {
            foreach ($users as $user) {
                echo "    • {$user->full_name} ({$user->email}) - {$user->role->name}\n";
            }
        }
        
        echo "\n";
    }
    
    echo "=== Test Complete ===\n\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
