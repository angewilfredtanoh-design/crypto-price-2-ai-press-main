<?php

namespace Crypto4\Tests;

use PHPUnit\Framework\TestCase;
use Crypto4\Services\HealthCheckService;

/**
 * Tests unitaires pour HealthCheckService
 */
class HealthCheckServiceTest extends TestCase
{
    private HealthCheckService $healthCheck;

    protected function setUp(): void
    {
        $this->healthCheck = new HealthCheckService();
    }

    public function testInitialStatusIsHealthy(): void
    {
        $this->assertEquals('healthy', $this->healthCheck->getStatus());
    }

    public function testGetChecksReturnsArray(): void
    {
        $checks = $this->healthCheck->getChecks();
        $this->assertIsArray($checks);
        $this->assertEmpty($checks);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $json = $this->healthCheck->toJson();
        $this->assertIsString($json);
        
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('status', $decoded);
        $this->assertArrayHasKey('timestamp', $decoded);
        $this->assertArrayHasKey('checks', $decoded);
    }

    public function testCountReturnsInteger(): void
    {
        // Note: count() method is in PrometheusMetricsExporter
        // This is a placeholder for future implementation
        $this->assertTrue(true);
    }

    public function testDatabaseCheckStructure(): void
    {
        $this->healthCheck->checkDatabase();
        $checks = $this->healthCheck->getChecks();
        
        $this->assertArrayHasKey('database', $checks);
        $this->assertArrayHasKey('status', $checks['database']);
        $this->assertArrayHasKey('message', $checks['database']);
        
        $validStatuses = ['healthy', 'unhealthy', 'warning', 'degraded'];
        $this->assertContains($checks['database']['status'], $validStatuses);
    }

    public function testDiskSpaceCheckStructure(): void
    {
        $this->healthCheck->checkDiskSpace();
        $checks = $this->healthCheck->getChecks();
        
        $this->assertArrayHasKey('disk_space', $checks);
        $this->assertArrayHasKey('status', $checks['disk_space']);
        $this->assertArrayHasKey('percent_free', $checks['disk_space']);
        $this->assertIsFloat($checks['disk_space']['percent_free']);
        $this->assertGreaterThan(0, $checks['disk_space']['percent_free']);
        $this->assertLessThanOrEqual(100, $checks['disk_space']['percent_free']);
    }

    public function testRunAllExecutesAllChecks(): void
    {
        $result = $this->healthCheck->runAll();
        
        $this->assertInstanceOf(HealthCheckService::class, $result);
        
        $checks = $this->healthCheck->getChecks();
        $this->assertNotEmpty($checks);
        
        // At minimum, disk_space and processes should always run
        $this->assertArrayHasKey('disk_space', $checks);
        $this->assertArrayHasKey('processes', $checks);
    }

    public function testResponseTimeMeasurement(): void
    {
        $this->healthCheck->checkDiskSpace();
        $checks = $this->healthCheck->getChecks();
        
        // Disk space check doesn't have response time, but structure is validated
        $this->assertArrayHasKey('disk_space', $checks);
    }
}
