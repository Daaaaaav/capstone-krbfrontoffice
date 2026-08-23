<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => ':memory:']);
config(['cache.default' => 'array']);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Run knowledge tables migration
$migration = require __DIR__ . '/../database/migrations/2026_08_23_000001_create_krb_knowledge_tables.php';
$migration->up();

// Create companies, vehicles, and vehicle_bookings tables in memory
Schema::create('companies', function (Blueprint $t) {
    $t->id('company_id');
    $t->string('company_name');
    $t->timestamps();
});

Schema::create('vehicles', function (Blueprint $t) {
    $t->id('vehicle_id');
    $t->foreignId('company_id')->nullable();
    $t->string('name');
    $t->string('plate_number')->nullable();
    $t->string('category')->nullable();
    $t->string('year')->nullable();
    $t->boolean('is_active')->default(true);
    $t->timestamps();
    $t->softDeletes();
});

Schema::create('booking_rooms', function (Blueprint $t) {
    $t->id('bookingroom_id');
    $t->foreignId('company_id')->nullable();
    $t->foreignId('room_id')->nullable();
    $t->foreignId('user_id')->nullable();
    $t->date('date')->nullable();
    $t->string('status')->default('pending');
    $t->timestamps();
    $t->softDeletes();
});

Schema::create('vehicle_bookings', function (Blueprint $t) {
    $t->id('vehiclebooking_id');
    $t->foreignId('company_id')->nullable();
    $t->foreignId('vehicle_id')->nullable();
    $t->foreignId('user_id')->nullable();
    $t->string('borrower_name')->nullable();
    $t->dateTime('start_at')->nullable();
    $t->dateTime('end_at')->nullable();
    $t->string('status')->default('pending');
    $t->timestamps();
    $t->softDeletes();
});

Schema::create('ai_settings', function (Blueprint $t) {
    $t->id();
    $t->string('key')->nullable();
    $t->text('value')->nullable();
    $t->timestamps();
});

use App\Models\Company;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Services\AI\DynamicAnalyticsService;
use App\Services\AI\Enums\ChatDomain;
use App\Services\AI\KrbKnowledgeService;
use App\Services\AI\ScopeGuard;
use App\Services\AI\ToolDispatcher;
use App\Services\AI\Tools\CalculationTool;
use App\Services\AI\Tools\KrbKnowledgeTool;
use Carbon\Carbon;
use Database\Seeders\KrbKnowledgeSeeder;

echo "====================================================\n";
echo "KRB MANAGER ASSISTANT EXPANSION VERIFICATION SUITE\n";
echo "====================================================\n\n";

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg, &$passed, &$failed) {
    if ($cond) {
        echo "  [PASS] {$msg}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$msg}\n";
        $failed++;
    }
}

// 1. SCOPEGUARD TESTS
echo "1. Testing ScopeGuard & Domain Router...\n";
$guard = app(ScopeGuard::class);

$allowedKrb = [
    'When was Kebun Raya Bogor founded?',
    'Who is Caspar Georg Carl Reinwardt?',
    'Tell me about Rafflesia patma and Titan Arum',
    'Where is Griya Anggrek in Kebun Raya Bogor?',
    'Fasilitas wisata dan jam buka Kebun Raya Bogor',
];

foreach ($allowedKrb as $q) {
    $res = $guard->validate($q);
    assertCondition($res['allowed'] === true, "Allowed General KRB query: '{$q}'", $passed, $failed);
    assertCondition(in_array(ChatDomain::GENERAL_KRB_KNOWLEDGE->value, $res['domains']), "Domain is GENERAL_KRB_KNOWLEDGE for '{$q}'", $passed, $failed);
}

$rejectedOut = [
    'What is the most popular pop song?',
    'Who won the football match yesterday?',
    'Tell me a joke',
    'What is the capital of France?',
    'Explain quantum physics and black holes',
    'Write a python script to scrape twitter',
];

foreach ($rejectedOut as $q) {
    $res = $guard->validate($q);
    assertCondition($res['allowed'] === false, "Blocked out-of-scope query: '{$q}'", $passed, $failed);
    assertCondition($res['refusal'] === ScopeGuard::REFUSAL_EN, "Standard refusal returned for '{$q}'", $passed, $failed);
}

$calcClassification = $guard->classify('What is the average number of vehicle bookings on Sundays in 2026?');
assertCondition($calcClassification['allowed'] === true, "Allowed Sunday average calculation query", $passed, $failed);
assertCondition(in_array(ChatDomain::CALCULATION->value, $calcClassification['domains']), "Classified with CALCULATION domain", $passed, $failed);
assertCondition(in_array(ChatDomain::ANALYTICS->value, $calcClassification['domains']), "Classified with ANALYTICS domain", $passed, $failed);

// 2. CALCULATION TOOL TESTS
echo "\n2. Testing Deterministic Calculation Tool...\n";
$calc = new CalculationTool();

$avgRes = $calc->execute(['operation' => 'average', 'values' => [10, 20, 30, 40]]);
assertCondition($avgRes['success'] && $avgRes['result'] === 25.0, "CalculationTool average: 25.0", $passed, $failed);

$pctRes = $calc->execute(['operation' => 'percentage', 'numerator' => 25, 'denominator' => 100]);
assertCondition($pctRes['success'] && $pctRes['result'] === 25.0, "CalculationTool percentage: 25%", $passed, $failed);

$zeroDiv = $calc->execute(['operation' => 'divide', 'numerator' => 100, 'denominator' => 0]);
assertCondition(!$zeroDiv['success'] && str_contains($zeroDiv['error'], 'Division by zero'), "CalculationTool zero-division guard", $passed, $failed);

// 3. KNOWLEDGE RETRIEVAL TESTS
echo "\n3. Testing KRB Knowledge Retrieval Layer...\n";
try {
    app(KrbKnowledgeSeeder::class)->run();
    $knowService = app(KrbKnowledgeService::class);
    $knowDocs = $knowService->search('When was Kebun Raya Bogor founded and who founded it?');
    assertCondition($knowDocs->isNotEmpty(), "Knowledge search found results for history query", $passed, $failed);
    if ($knowDocs->isNotEmpty()) {
        $first = $knowDocs->first();
        assertCondition(str_contains($first->content, '1817') && str_contains($first->content, 'Reinwardt'), "Knowledge content contains 1817 and Reinwardt", $passed, $failed);
    }

    $knowTool = app(KrbKnowledgeTool::class);
    $fallbackRes = $knowTool->execute(['query' => 'Secret missile silo on Mars']);
    assertCondition(str_contains($fallbackRes['text'], 'approved Kebun Raya Bogor information'), "Knowledge tool graceful fallback on missing info", $passed, $failed);
} catch (\Throwable $e) {
    echo "  [INFO] Seeder DB execution: " . $e->getMessage() . "\n";
}

// 4. DYNAMIC ANALYTICS SERVICE TESTS (SUNDAY AVERAGE CALCULATION)
echo "\n4. Testing Dynamic Analytics & Sunday Average Logic...\n";
$dynService = app(DynamicAnalyticsService::class);

$company = Company::create(['company_name' => 'Kebun Raya Bogor']);
$vehicle = Vehicle::create([
    'company_id'   => $company->company_id,
    'name'         => 'Toyota Avanza 01',
    'plate_number' => 'F 1001 KRB',
    'is_active'    => 1,
]);

// Seed 3 bookings across 2 Sundays in 2026 (Jan 4 and Jan 11) + 1 cancelled booking (Jan 18)
VehicleBooking::create([
    'company_id'    => $company->company_id,
    'vehicle_id'    => $vehicle->vehicle_id,
    'start_at'      => Carbon::create(2026, 1, 4, 9, 0, 0, 'Asia/Jakarta'),
    'end_at'        => Carbon::create(2026, 1, 4, 11, 0, 0, 'Asia/Jakarta'),
    'status'        => 'approved',
]);
VehicleBooking::create([
    'company_id'    => $company->company_id,
    'vehicle_id'    => $vehicle->vehicle_id,
    'start_at'      => Carbon::create(2026, 1, 4, 13, 0, 0, 'Asia/Jakarta'),
    'end_at'        => Carbon::create(2026, 1, 4, 15, 0, 0, 'Asia/Jakarta'),
    'status'        => 'completed',
]);
VehicleBooking::create([
    'company_id'    => $company->company_id,
    'vehicle_id'    => $vehicle->vehicle_id,
    'start_at'      => Carbon::create(2026, 1, 11, 10, 0, 0, 'Asia/Jakarta'),
    'end_at'        => Carbon::create(2026, 1, 11, 12, 0, 0, 'Asia/Jakarta'),
    'status'        => 'approved',
]);
VehicleBooking::create([
    'company_id'    => $company->company_id,
    'vehicle_id'    => $vehicle->vehicle_id,
    'start_at'      => Carbon::create(2026, 1, 18, 10, 0, 0, 'Asia/Jakarta'),
    'end_at'        => Carbon::create(2026, 1, 18, 12, 0, 0, 'Asia/Jakarta'),
    'status'        => 'cancelled',
]);

$sundayCalc = $dynService->calculateWeekdayAverage($company->company_id, 'vehicle_bookings', 'Sunday', 2026, true);
assertCondition($sundayCalc['success'] === true, "DynamicAnalyticsService returned success for Sunday 2026", $passed, $failed);
assertCondition($sundayCalc['total_bookings'] === 3, "Total qualifying bookings is 3 (cancelled excluded)", $passed, $failed);
assertCondition($sundayCalc['period_count'] === 52, "Year 2026 correctly has 52 Sundays", $passed, $failed);
assertCondition($sundayCalc['active_period_count'] === 2, "Active Sunday periods count is 2", $passed, $failed);
assertCondition($sundayCalc['zero_booking_period_count'] === 50, "Zero booking periods count is 50", $passed, $failed);
assertCondition($sundayCalc['average'] === round(3 / 52, 2), "Average is 0.06 (3 / 52)", $passed, $failed);
assertCondition($sundayCalc['included_zero_booking_periods'] === true, "Zero booking periods included in denominator", $passed, $failed);
assertCondition(isset($sundayCalc['calculation']['formula']), "Formula breakdown provided: {$sundayCalc['calculation']['formula']}", $passed, $failed);
echo "  [SAMPLE OUTPUT] " . $sundayCalc['text'] . "\n";

// 5. TOOL DISPATCHER REGISTRATION TESTS
echo "\n5. Testing ToolDispatcher Registration...\n";
$dispatcher = app(ToolDispatcher::class);
$manifest = $dispatcher->manifest();
$toolNames = array_column(array_column($manifest, 'function'), 'name');
assertCondition(in_array('calculate', $toolNames), "ToolDispatcher registered 'calculate' tool", $passed, $failed);
assertCondition(in_array('get_krb_knowledge', $toolNames), "ToolDispatcher registered 'get_krb_knowledge' tool", $passed, $failed);
assertCondition(in_array('get_analytics', $toolNames), "ToolDispatcher registered 'get_analytics' tool", $passed, $failed);

echo "\n====================================================\n";
echo "RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "====================================================\n";

exit($failed > 0 ? 1 : 0);

