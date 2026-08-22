<?php

namespace Tests\Feature;

use App\Services\ApplicationHealthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApplicationHealthEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('cache.default', 'array');
        Config::set('session.driver', 'array');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
    }

    /**
     * Test Laravel /health endpoint returns HTTP 200 with required structure.
     */
    public function test_laravel_health_endpoint_returns_200_json(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status'  => 'healthy',
            'service' => 'KRB Application',
        ]);
        $response->assertJsonStructure([
            'status',
            'service',
            'timestamp',
        ]);

        // Security check: ensure no sensitive keys are returned
        $content = $response->getContent();
        $this->assertStringNotContainsString('password', strtolower($content));
        $this->assertStringNotContainsString('app_key', strtolower($content));
        $this->assertStringNotContainsString('db_', strtolower($content));
    }

    /**
     * Test ApplicationHealthService reports healthy when all endpoints return 200.
     */
    public function test_application_health_service_all_healthy(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/health'                 => Http::response(['status' => 'healthy'], 200),
            'http://localhost/health'                      => Http::response(['status' => 'healthy'], 200),
            'http://127.0.0.1:8001/'                       => Http::response(['status' => 'healthy'], 200),
            'https://receptionistkebunraya.online/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        Cache::flush();
        $service = new ApplicationHealthService();
        $summary = $service->getHealthSummary(true);

        $this->assertEquals('healthy', $summary['status']);
        $this->assertEquals(3, $summary['healthy_count']);
        $this->assertEquals(3, $summary['total_count']);
        $this->assertArrayHasKey('krb_laravel_local', $summary['services']);
        $this->assertArrayHasKey('lstm_local', $summary['services']);
        $this->assertArrayHasKey('krb_public', $summary['services']);
    }

    /**
     * Test ApplicationHealthService detects degraded state when an endpoint is down.
     */
    public function test_application_health_service_degraded_when_endpoint_fails(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/health'                 => Http::response(['status' => 'healthy'], 200),
            'http://localhost/health'                      => Http::response(['status' => 'healthy'], 200),
            'http://127.0.0.1:8001/'                       => Http::response(['error' => 'down'], 500),
            'https://receptionistkebunraya.online/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        Cache::flush();
        $service = new ApplicationHealthService();
        $summary = $service->getHealthSummary(true);

        $this->assertEquals('degraded', $summary['status']);
        $this->assertEquals(2, $summary['healthy_count']);
        $this->assertFalse($summary['services']['lstm_local']['is_healthy']);
    }
}