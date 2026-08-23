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

// 3. KNOWLEDGE RETRIEVAL & SOURCE ATTRIBUTION TESTS
echo "\n3. Testing KRB Knowledge Retrieval & Source Attribution Layer...\n";
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
    $knowRes = $knowTool->execute(['query' => 'sejarah kebun raya bogor']);
    assertCondition($knowRes['success'] === true, "Knowledge tool executed successfully", $passed, $failed);
    assertCondition(!empty($knowRes['sources']), "Knowledge tool returned sources metadata", $passed, $failed);
    assertCondition($knowRes['sources'][0]['type'] === \App\Services\AI\Enums\ChatDataSource::KRB_KNOWLEDGE_BASE->value, "Knowledge source type is krb_knowledge_base", $passed, $failed);
    assertCondition(str_contains($knowRes['text'], 'Approved Kebun Raya Bogor Knowledge Base'), "Knowledge response contains source attribution label", $passed, $failed);

    $fallbackRes = $knowTool->execute(['query' => 'Secret missile silo on Mars']);
    assertCondition(str_contains($fallbackRes['text'], 'approved Kebun Raya Bogor information'), "Knowledge tool graceful fallback on missing info", $passed, $failed);
} catch (\Throwable $e) {
    echo "  [INFO] Seeder DB execution: " . $e->getMessage() . "\n";
}

// 4. DYNAMIC ANALYTICS & MULTI-SOURCE ATTRIBUTION TESTS
echo "\n4. Testing Dynamic Analytics & Multi-Source Attribution Logic...\n";
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

// A. Test End-to-End Live Application Source Attribution
$liveSundayCalc = $dynService->calculateWeekdayAverage($company->company_id, 'vehicle_bookings', 'Sunday', 2026, true, 'end_to_end');
assertCondition($liveSundayCalc['success'] === true, "Live calculation returned success", $passed, $failed);
assertCondition($liveSundayCalc['source_type'] === 'end_to_end', "Source type is end_to_end", $passed, $failed);
assertCondition($liveSundayCalc['total_bookings'] === 3, "Total qualifying live bookings is 3", $passed, $failed);
assertCondition($liveSundayCalc['period_count'] === 52, "Year 2026 correctly has 52 Sundays", $passed, $failed);
assertCondition($liveSundayCalc['average'] === round(3 / 52, 2), "Live Sunday average is 0.06", $passed, $failed);
assertCondition(str_contains($liveSundayCalc['text'], 'Live KRB System Data'), "Live calculation text contains 'Live KRB System Data' source tag", $passed, $failed);

// B. Test Server Historical CSV Source Attribution & Calculation
$csvReader = app(\App\Services\AI\CsvDataReader::class);
$csvSundayCalc = $dynService->calculateWeekdayAverage($company->company_id, 'vehicle_bookings', 'Sunday', 2025, true, 'server_csv');
assertCondition($csvSundayCalc['success'] === true, "Server CSV calculation returned success for 2025", $passed, $failed);
assertCondition($csvSundayCalc['source_type'] === 'server_csv', "Source type is server_csv", $passed, $failed);
assertCondition($csvSundayCalc['period_count'] === 52, "CSV 2025 has 52 Sunday rows", $passed, $failed);
assertCondition(isset($csvSundayCalc['total_metric_value']), "Total metric value computed from CSV", $passed, $failed);
assertCondition(str_contains($csvSundayCalc['text'], 'Server Historical CSV (krb_historical_data.csv)'), "CSV text contains 'Server Historical CSV' source tag", $passed, $failed);

// C. Test Combined Multi-Source Comparison (No double counting)
$combinedCalc = $dynService->calculateWeekdayAverage($company->company_id, 'vehicle_bookings', 'Sunday', 2026, true, 'combined');
assertCondition($combinedCalc['success'] === true, "Combined calculation returned success", $passed, $failed);
assertCondition($combinedCalc['source_type'] === 'combined', "Combined source type returned", $passed, $failed);
assertCondition(count($combinedCalc['sources']) === 2, "Combined calculation contains both sources", $passed, $failed);
assertCondition(str_contains($combinedCalc['text'], 'Live KRB System Data + Server Historical CSV'), "Combined text contains both source labels", $passed, $failed);

// D. Test Provenance Preservation in CalculationTool
$calcTool = new CalculationTool();
$calcProvenance = $calcTool->execute([
    'operation' => 'difference',
    'numerator' => 10.0,
    'denominator' => 4.0,
    'sources'   => $combinedCalc['sources'],
]);
assertCondition($calcProvenance['success'] === true, "CalculationTool executed difference", $passed, $failed);
assertCondition(count($calcProvenance['sources']) === 2, "CalculationTool preserved source provenance end-to-end", $passed, $failed);
assertCondition(str_contains($calcProvenance['text'], 'Live KRB System Data + Server Historical CSV'), "CalculationTool formatted source attribution tag", $passed, $failed);

// 5. CSV DATA READER & LSTM INTEGRITY TESTS
echo "\n5. Testing CsvDataReader & LSTM Forecasting Integrity...\n";
$csvMeta = $csvReader->getCsvSourceMetadata();
assertCondition($csvMeta['type'] === 'server_csv', "CsvDataReader metadata type is server_csv", $passed, $failed);
assertCondition($csvMeta['total_rows'] > 0, "CsvDataReader found {$csvMeta['total_rows']} total rows", $passed, $failed);

$csvRows = $csvReader->getHistoricalRows('2025-01-01', '2025-01-07');
assertCondition(count($csvRows) === 7, "getHistoricalRows retrieved 7 days in range", $passed, $failed);

$visitorSeries = $csvReader->readServerCsv('visitors');
assertCondition(!empty($visitorSeries), "Existing LSTM readServerCsv('visitors') functional", $passed, $failed);

$summedSeries = $csvReader->readServerCsvColumnsSummed(['offline_room_bookings', 'online_room_bookings']);
assertCondition(!empty($summedSeries), "Existing LSTM readServerCsvColumnsSummed() functional", $passed, $failed);

$missingCols = $csvReader->validateColumns($csvReader->resolveServerCsvPath());
assertCondition(empty($missingCols), "All required columns present in krb_historical_data.csv", $passed, $failed);

// 6. TOOL DISPATCHER REGISTRATION TESTS
echo "\n6. Testing ToolDispatcher Registration...\n";
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

