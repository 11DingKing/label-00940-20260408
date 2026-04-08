<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use Hyperf\Testing\Client;

/**
 * @internal
 * @coversNothing
 */
class ProductControllerTest extends AbstractTestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
    }

    public function testGetProductList(): void
    {
        $response = $this->client->get('/api/product/list');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        
        if ($response['code'] === 0) {
            $this->assertArrayHasKey('data', $response);
            $this->assertArrayHasKey('list', $response['data']);
            $this->assertArrayHasKey('total', $response['data']);
            $this->assertIsArray($response['data']['list']);
        }
    }

    public function testGetProductListWithPagination(): void
    {
        $response = $this->client->get('/api/product/list', [
            'page' => 1,
            'page_size' => 5,
        ]);

        $this->assertIsArray($response);
        
        if ($response['code'] === 0) {
            $this->assertArrayHasKey('data', $response);
            $this->assertEquals(1, $response['data']['page']);
            $this->assertEquals(5, $response['data']['page_size']);
        }
    }

    public function testGetProductListWithCategoryFilter(): void
    {
        $response = $this->client->get('/api/product/list', [
            'category_id' => 1,
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
    }

    public function testGetProductListWithKeyword(): void
    {
        $response = $this->client->get('/api/product/list', [
            'keyword' => 'iPhone',
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
    }

    public function testGetProductDetail(): void
    {
        $response = $this->client->get('/api/product/detail/1');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        
        if ($response['code'] === 0) {
            $this->assertArrayHasKey('data', $response);
            $this->assertArrayHasKey('id', $response['data']);
            $this->assertArrayHasKey('name', $response['data']);
            $this->assertArrayHasKey('price', $response['data']);
        }
    }

    public function testGetProductDetailNotFound(): void
    {
        $response = $this->client->get('/api/product/detail/999999');

        $this->assertIsArray($response);
        $this->assertNotEquals(0, $response['code']);
    }

    public function testGetCategoryList(): void
    {
        $response = $this->client->get('/api/product/category/list');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        
        if ($response['code'] === 0) {
            $this->assertArrayHasKey('data', $response);
            $this->assertIsArray($response['data']);
        }
    }

    public function testCreateProductWithoutAuth(): void
    {
        $response = $this->client->post('/api/product/create', [
            'name' => 'Test Product',
            'price' => '99.99',
            'category_id' => 1,
        ]);

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }

    // ==================== 边界条件测试 ====================

    public function testGetProductDetailWithInvalidId(): void
    {
        $response = $this->client->get('/api/product/detail/0');

        $this->assertIsArray($response);
        $this->assertNotEquals(0, $response['code']);
    }

    public function testGetProductDetailWithNegativeId(): void
    {
        $response = $this->client->get('/api/product/detail/-1');

        $this->assertIsArray($response);
        $this->assertNotEquals(0, $response['code']);
    }

    public function testGetProductListWithLargePage(): void
    {
        $response = $this->client->get('/api/product/list', [
            'page' => 99999,
            'page_size' => 10,
        ]);

        $this->assertIsArray($response);
        // 超大页码应该返回空列表或错误
        if ($response['code'] === 0) {
            $this->assertEmpty($response['data']['list']);
        }
    }

    public function testGetProductListWithEmptyKeyword(): void
    {
        $response = $this->client->get('/api/product/list', [
            'keyword' => '',
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
    }

    public function testUpdateProductWithoutAuth(): void
    {
        $response = $this->client->put('/api/product/update/1', [
            'name' => 'Updated Product',
            'price' => '199.99',
        ]);

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }

    public function testDeleteProductWithoutAuth(): void
    {
        $response = $this->client->delete('/api/product/delete/1');

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }

    // ==================== 响应结构验证 ====================

    public function testProductListResponseStructure(): void
    {
        $response = $this->client->get('/api/product/list');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        $this->assertArrayHasKey('message', $response);
        
        if ($response['code'] === 0) {
            $this->assertArrayHasKey('data', $response);
            $this->assertArrayHasKey('list', $response['data']);
            $this->assertArrayHasKey('total', $response['data']);
            $this->assertArrayHasKey('page', $response['data']);
            $this->assertArrayHasKey('page_size', $response['data']);
        }
    }

    public function testProductDetailResponseStructure(): void
    {
        $response = $this->client->get('/api/product/detail/1');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        $this->assertArrayHasKey('message', $response);
        
        if ($response['code'] === 0) {
            $this->assertArrayHasKey('data', $response);
            $data = $response['data'];
            $this->assertArrayHasKey('id', $data);
            $this->assertArrayHasKey('name', $data);
            $this->assertArrayHasKey('price', $data);
            $this->assertArrayHasKey('description', $data);
            $this->assertArrayHasKey('category_id', $data);
        }
    }

    public function testCategoryListResponseStructure(): void
    {
        $response = $this->client->get('/api/product/category/list');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        $this->assertArrayHasKey('message', $response);
        
        if ($response['code'] === 0) {
            $this->assertArrayHasKey('data', $response);
            $this->assertIsArray($response['data']);
        }
    }
}
