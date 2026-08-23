<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Services\AI\CsvDataReader;
use App\Services\AI\DataSourceResolver;
use App\Services\AI\DynamicAnalyticsService;
use App\Services\AI\Enums\ChatDataSource;
use App\Services\AI\ScopeGuard;
use App\Services\AI\Tools\AnalyticsTool;
use App\Services\AI\Tools\KrbKnowledgeTool;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatDataSourceRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $managerUser;
    private Vehicle $vehicle;
    private CsvDataReader $csvReader;
    private DynamicAnalyticsService $dynService;
    private DataSourceResolver $resolver;
    private AnalyticsTool $analyticsTool;
    private KrbKnowledgeTool $knowledgeTool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'company_name' => 'Kebun Raya Bogor',
        ]);

        $dept = Department::create([
            'company_id'      => $this->company->company_id,
            'department_name' => 'Management',
        ]);

        $role = Role::create(['name' => 'Manager']);

        $this->managerUser = User::create([
            'company_id'    => $this->company->company_id,
            'department_id' => $dept->department_id,
            'role_id'       => $role->role_id,
            'full_name'     => 'Manager Test',
            'email'         => 'manager@krbogor.id',
            'phone_number'  => '081234567891',
            'password'      => bcrypt('password'),
            'status'        => 'active',
        ]);

        $this->vehicle = Vehicle::create([
            'company_id'   => $this->company->company_id,
            'name'         => 'Toyota Innova 01',
            'plate_number' => 'F 2026 KRB',
            'category'     => 'car',
            'year'         => '2024',
            'is_active'    => 1,
        ]);

        $this->csvReader = app(CsvDataReader::class);
        $this->dynService = app(DynamicAnalyticsService::class);
        $this->resolver = app(DataSourceResolver::class);
        $this->analyticsTool = app(AnalyticsTool::class);
        $this->knowledgeTool = app(KrbKnowledgeTool::class);

        $this->seed(\Database\Seeders\KrbKnowledgeSeeder::class);
    }

    /**
     * Test 1: User asks "How about from the server csv?"
     * Expected: Detect SERVER_CSV, invoke CSV analytics, read configured CSV, return available analytics, no upload requirement.
     */
    public function test_1_detect_server_csv_preference(): void
    {
        $query = 'How about from the server csv?';
        $pref = $this->resolver->detectSourcePreference($query);
        $this->assertEquals(ChatDataSource::SERVER_CSV, $pref);

        $summary = $this->csvReader->getComprehensiveServerCsvSummary();
        $this->assertTrue($summary['success']);
        $this->assertGreaterThan(0, $summary['total_records']);
        $this->assertStringContainsString('krb_historical_data.csv', $summary['text']);
    }

    /**
     * Test 2: User asks "Can you tell the analytics from the server CSV?"
     * Expected: Read actual CSV, generate summary from available columns, clearly identify Server Historical CSV.
     */
    public function test_2_server_csv_analytics_summary(): void
    {
        $this->actingAs($this->managerUser);

        $res = $this->analyticsTool->execute([
            'data_source' => 'server_csv',
            'operation'   => 'summary',
        ]);

        $this->assertArrayHasKey('text', $res);
        $this->assertStringContainsString('Server Historical CSV', $res['text']);
        $this->assertStringContainsString('Visitors', $res['text']);
        $this->assertStringContainsString('Room Bookings', $res['text']);
        $this->assertStringContainsString('Vehicle Bookings', $res['text']);
    }

    /**
     * Test 3: User asks "What is the average vehicle bookings on Sunday from the server CSV?"
     * Expected: Use only server CSV, filter actual Sunday rows, calculate deterministic average from vehicle_bookings, identify CSV source.
     */
    public function test_3_sunday_vehicle_bookings_from_server_csv(): void
    {
        $this->actingAs($this->managerUser);

        $res = $this->analyticsTool->execute([
            'data_source' => 'server_csv',
            'entity'      => 'vehicle_bookings',
            'weekday'     => 'Sunday',
            'year'        => 2026,
            'operation'   => 'average',
        ]);

        $this->assertArrayHasKey('data', $res);
        $data = $res['data'];
        $this->assertTrue($data['success']);
        $this->assertEquals('server_csv', $data['source_type']);
        $this->assertEquals('Sunday', $data['weekday']);
        $this->assertGreaterThan(0, $data['period_count']);
        $this->assertArrayHasKey('average', $data);
        $this->assertStringContainsString('Server Historical CSV', $res['text']);
    }

    /**
     * Test 4: User asks "What is the average vehicle bookings on Sunday?"
     * Expected: Default to COMBINED_AUTO, evaluate Live and CSV sources, safely handle overlap, report contributing sources.
     */
    public function test_4_default_combined_auto_sunday_vehicle_bookings(): void
    {
        $this->actingAs($this->managerUser);

        // Seed 1 live booking on Sunday Jan 4, 2026
        VehicleBooking::create([
            'company_id'    => $this->company->company_id,
            'department_id' => $this->managerUser->department_id,
            'vehicle_id'    => $this->vehicle->vehicle_id,
            'user_id'       => $this->managerUser->user_id,
            'borrower_name' => 'Tester',
            'start_at'      => Carbon::create(2026, 1, 4, 9, 0, 0, 'Asia/Jakarta'),
            'end_at'        => Carbon::create(2026, 1, 4, 11, 0, 0, 'Asia/Jakarta'),
            'status'        => 'approved',
            'purpose'       => 'Dinas',
            'destination'   => 'Bogor',
            'terms_agreed'  => 1,
        ]);

        $query = 'What is the average vehicle bookings on Sunday?';
        $pref = $this->resolver->detectSourcePreference($query);
        $this->assertEquals(ChatDataSource::COMBINED_AUTO, $pref);

        $res = $this->analyticsTool->execute([
            'data_source' => 'combined_auto',
            'entity'      => 'vehicle_bookings',
            'weekday'     => 'Sunday',
            'year'        => 2026,
            'operation'   => 'average',
        ]);

        $this->assertArrayHasKey('data', $res);
        $this->assertTrue($res['data']['success']);
        $this->assertStringContainsString('Live System Records', $res['text']);
        $this->assertStringContainsString('Server Historical CSV Baseline', $res['text']);
        $this->assertStringContainsString('Data sources:', $res['text']);
    }

    /**
     * Test 5: User asks "Use only the live system data."
     * Expected: Route to END_TO_END, do not query CSV, report Live KRB System Data.
     */
    public function test_5_explicit_live_system_data(): void
    {
        $query = 'Use only the live system data.';
        $pref = $this->resolver->detectSourcePreference($query);
        $this->assertEquals(ChatDataSource::END_TO_END, $pref);

        $this->actingAs($this->managerUser);

        $res = $this->analyticsTool->execute([
            'data_source' => 'end_to_end',
            'entity'      => 'vehicle_bookings',
            'weekday'     => 'Sunday',
            'year'        => 2026,
            'operation'   => 'average',
        ]);

        $this->assertTrue($res['data']['success']);
        $this->assertEquals('end_to_end', $res['data']['source_type']);
        $this->assertStringContainsString('Live KRB System Data', $res['text']);
        $this->assertStringNotContainsString('Server Historical CSV', $res['text']);
    }

    /**
     * Test 6: User asks "Tell me about the history of Kebun Raya Bogor."
     * Expected: Internal operational data recognized as insufficient, approved external KRB knowledge data used as fallback with provenance.
     */
    public function test_6_external_krb_knowledge_fallback(): void
    {
        $query = 'Tell me about the history of Kebun Raya Bogor.';
        $guard = app(ScopeGuard::class)->validate($query, $this->managerUser);
        $this->assertTrue($guard['allowed']);

        $res = $this->knowledgeTool->execute([
            'query' => 'sejarah kebun raya bogor',
        ]);

        $this->assertTrue($res['success']);
        $this->assertStringContainsString('Reinwardt', $res['text']);
        $this->assertStringContainsString('Approved Kebun Raya Bogor Knowledge Base', $res['text']);
    }

    /**
     * Test 7: Ask genuinely unrelated question.
     * Expected: Remains safely out of scope, refusal returned without unnecessary lookups.
     */
    public function test_7_out_of_scope_rejection(): void
    {
        $query = 'Who won yesterday\'s football match?';
        $guard = app(ScopeGuard::class)->validate($query, $this->managerUser);

        $this->assertFalse($guard['allowed']);
        $this->assertEquals(ScopeGuard::REFUSAL_EN, $guard['refusal']);
    }

    /**
     * Test 8: Overlap detection and prevention of double-counting.
     */
    public function test_8_overlap_analysis(): void
    {
        $analysis = $this->resolver->analyzeCoverageRelationship($this->company->company_id);
        $this->assertArrayHasKey('overlap', $analysis);
        $this->assertArrayHasKey('live_coverage', $analysis);
        $this->assertArrayHasKey('csv_coverage', $analysis);
    }

    /**
     * Test 9: Read-only CSV integrity check (CSV file is never modified).
     */
    public function test_9_csv_read_only_integrity(): void
    {
        $path = $this->csvReader->resolveServerCsvPath();
        $this->assertFileExists($path);

        $initialHash = md5_file($path);

        // Perform multiple analytical operations
        $this->csvReader->serverCsvInfo();
        $this->csvReader->getComprehensiveServerCsvSummary();
        $this->csvReader->getWeekdayAverageFromCsv('vehicle_bookings', 'Sunday', 2026);
        $this->csvReader->getHistoricalRows('2026-01-01', '2026-06-30');

        $postHash = md5_file($path);
        $this->assertEquals($initialHash, $postHash, 'CSV file must remain strictly identical and read-only.');
    }
}
