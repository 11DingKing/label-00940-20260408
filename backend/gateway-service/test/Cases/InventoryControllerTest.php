<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use Hyperf\Testing\Client;

/**
 * @internal
 * @coversNothing
 */
class InventoryControllerTest extends AbstractTestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
    }

    public function testGetStockWithoutAuth(): void
    {
        $response = $this->client->get('/api/inventory/stock/1');

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }

    public function testUpdateStockWithoutAuth(): void
    {
        $response = $this->client->put('/api/inventory/update', [
            'product_id' => 1,
            'stock' => 100,
        ]);

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }
}
