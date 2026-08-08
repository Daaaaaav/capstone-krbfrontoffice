<?php

/**
 * Diagnostic Script: Department Users Comparison
 * 
 * Compares user retrieval between Receptionist pages and IT Officer page
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Department Users Diagnostic ===\n\n";

// Get the first company
$companyId = DB::table('companies')->first()->company_id ?? null;

if (!$companyId) {
    echo "No company found in database.\n";
    exit(1);
}

echo "Company ID: $companyId\n\n";

// Get all departments for this company
$departments = Department::where('company_id', $companyId)
    ->orderBy('department_name')
    ->get();

echo "Analyzing " . count($departments) . " departments...\n\n";

foreach ($departments as $department) {
    echo "┌─────────────────────────────────────────────────────────────────\n";
    echo "│ Department: {$department->department_name} (ID: {$department->department_id})\n";
    echo "├─────────────────────────────────────────────────────────────────\n";
    
    // METHOD 1: Receptionist approach (direct department_id query)
    $receptionistUsers = User::where('company_id', $companyId)
        ->where('department_id', $department->department_id)
        ->with('role')
        ->orderBy('full_name')
        ->get();
    
    echo "│ Receptionist Method (department_id filter):\n";
    echo "│   Count: " . $receptionistUsers->count() . "\n";
    if ($receptionistUsers->count() > 0) {
        foreach ($receptionistUsers as $user) {
            $roleName = $user->role->name ?? 'No Role';
            echo "│   - [{$user->user_id}] {$user->full_name} ({$user->email}) - Role: {$roleName}\n";
        }
    } else {
        echo "│   - (No users)\n";
    }
    
    echo "│\n";
    
    // METHOD 2: IT Officer current approach (with role filter)
    $itOfficerUsers = User::where('company_id', $companyId)
        ->where('department_id', $department->department_id)
        ->whereHas('role', function ($q) {
            $q->whereIn('roles.name', ['Manager', 'Receptionist']);
        })
        ->with('role')
        ->orderBy('full_name')
        ->get();
    
    echo "│ IT Officer Method (department_id + role filter):\n";
    echo "│   Count: " . $itOfficerUsers->count() . "\n";
    if ($itOfficerUsers->count() > 0) {
        foreach ($itOfficerUsers as $user) {
            $roleName = $user->role->name ?? 'No Role';
            echo "│   - [{$user->user_id}] {$user->full_name} ({$user->email}) - Role: {$roleName}\n";
        }
    } else {
        echo "│   - (No users)\n";
    }
    
    echo "│\n";
    
    // METHOD 3: Department relationship count (what withCount uses)
    $deptWithCount = Department::where('company_id', $companyId)
        ->where('department_id', $department->department_id)
        ->withCount('users')
        ->first();
    
    echo "│ Department->users() Relationship:\n";
    echo "│   Count: " . ($deptWithCount->users_count ?? 0) . "\n";
    
    echo "│\n";
    
    // METHOD 4: Check if there are any users with other roles
    $otherRoleUsers = User::where('company_id', $companyId)
        ->where('department_id', $department->department_id)
        ->whereHas('role', function ($q) {
            $q->whereNotIn('roles.name', ['Manager', 'Receptionist']);
        })
        ->with('role')
        ->get();
    
    if ($otherRoleUsers->count() > 0) {
        echo "│ USERS WITH OTHER ROLES (filtered out by IT Officer):\n";
        foreach ($otherRoleUsers as $user) {
            $roleName = $user->role->name ?? 'No Role';
            echo "│   - [{$user->user_id}] {$user->full_name} - Role: {$roleName}\n";
        }
        echo "│\n";
    }
    
    // ANALYSIS
    $mismatch = $receptionistUsers->count() !== $itOfficerUsers->count();
    if ($mismatch) {
        echo "│ ⚠️  MISMATCH DETECTED!\n";
        echo "│     Receptionist: " . $receptionistUsers->count() . " users\n";
        echo "│     IT Officer:   " . $itOfficerUsers->count() . " users\n";
        echo "│     Difference:   " . ($receptionistUsers->count() - $itOfficerUsers->count()) . " users\n";
    } else {
        echo "│ ✓ No mismatch (counts match)\n";
    }
    
    echo "└─────────────────────────────────────────────────────────────────\n\n";
}

// Check for users with NULL department_id
echo "┌─────────────────────────────────────────────────────────────────\n";
echo "│ Users WITHOUT Department (department_id IS NULL)\n";
echo "├─────────────────────────────────────────────────────────────────\n";

$unassignedUsers = User::where('company_id', $companyId)
    ->whereNull('department_id')
    ->with('role')
    ->orderBy('full_name')
    ->get();

echo "│ Total: " . $unassignedUsers->count() . "\n";
if ($unassignedUsers->count() > 0) {
    foreach ($unassignedUsers as $user) {
        $roleName = $user->role->name ?? 'No Role';
        echo "│ - [{$user->user_id}] {$user->full_name} ({$user->email}) - Role: {$roleName}\n";
    }
}

echo "└─────────────────────────────────────────────────────────────────\n\n";

// Summary
echo "=== SUMMARY ===\n\n";
echo "Key Findings:\n";
echo "1. Receptionist pages use: User::where('department_id', \$departmentId)\n";
echo "2. IT Officer page filters: whereHas('role', ...'Manager', 'Receptionist'...)\n";
echo "3. This means IT Officer excludes users with other roles (IT Officer, etc.)\n\n";

echo "Recommendation:\n";
echo "Remove the role filter from IT Officer's UsersPerDepartment component\n";
echo "to match the Receptionist page behavior.\n\n";
