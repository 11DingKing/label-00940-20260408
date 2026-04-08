<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use Hyperf\Testing\Client;

/**
 * @internal
 * @coversNothing
 */
class HealthCheckTest extends AbstractTestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
    }

    public function testHealthEndpoint(): void
    {
        $response = $this->client->get('/health');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('ok', $response['status']);
        $this->assertArrayHasKey('service', $response);
        $this->assertEquals('gateway', $response['service']);
    }
}
