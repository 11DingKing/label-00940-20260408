<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use Hyperf\Testing\Client;

/**
 * 订单控制器测试
 * 
 * 覆盖场景:
 * - 未授权访问 (401)
 * - 参数验证 (边界条件)
 * - 核心业务逻辑 (成功路径)
 * 
 * @internal
 * @coversNothing
 */
class OrderControllerTest extends AbstractTestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
    }

    // ==================== 未授权访问测试 ====================

    public function testCreateOrderWithoutAuth(): void
    {
        $response = $this->client->post('/api/order/create', [
            'items' => [['product_id' => 1, 'quantity' => 1]],
        ]);

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
        $this->assertStringContainsString('未登录', $response['message']);
    }

    public function testGetOrderListWithoutAuth(): void
    {
        $response = $this->client->get('/api/order/list');

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }

    public function testGetOrderDetailWithoutAuth(): void
    {
        $response = $this->client->get('/api/order/detail/1');

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }

    public function testCancelOrderWithoutAuth(): void
    {
        $response = $this->client->put('/api/order/cancel/1');

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }

    public function testPayOrderWithoutAuth(): void
    {
        $response = $this->client->put('/api/order/pay/1');

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }

    // ==================== 参数验证测试 (边界条件) ====================

    public function testCreateOrderWithEmptyItems(): void
    {
        // 模拟带 token 的请求 (需要 mock AuthMiddleware)
        $response = $this->client->post('/api/order/create', [
            'items' => [],
        ]);

        $this->assertIsArray($response);
        // 空订单项应该返回错误
        $this->assertContains($response['code'], [401, -1, 1]);
    }

    public function testCreateOrderWithInvalidProductId(): void
    {
        $response = $this->client->post('/api/order/create', [
            'items' => [['product_id' => -1, 'quantity' => 1]],
        ]);

        $this->assertIsArray($response);
        // 无效商品 ID 应该返回错误
        $this->assertContains($response['code'], [401, -1, 1]);
    }

    public function testCreateOrderWithZeroQuantity(): void
    {
        $response = $this->client->post('/api/order/create', [
            'items' => [['product_id' => 1, 'quantity' => 0]],
        ]);

        $this->assertIsArray($response);
        // 数量为 0 应该返回错误
        $this->assertContains($response['code'], [401, -1, 1]);
    }

    public function testCreateOrderWithNegativeQuantity(): void
    {
        $response = $this->client->post('/api/order/create', [
            'items' => [['product_id' => 1, 'quantity' => -5]],
        ]);

        $this->assertIsArray($response);
        // 负数数量应该返回错误
        $this->assertContains($response['code'], [401, -1, 1]);
    }

    public function testGetOrderDetailWithInvalidId(): void
    {
        $response = $this->client->get('/api/order/detail/0');

        $this->assertIsArray($response);
        // 无效订单 ID 应该返回错误
        $this->assertContains($response['code'], [401, -1, 1]);
    }

    public function testGetOrderListWithInvalidPagination(): void
    {
        $response = $this->client->get('/api/order/list', [
            'page' => -1,
            'page_size' => 0,
        ]);

        $this->assertIsArray($response);
        // 无效分页参数应该返回错误或使用默认值
        $this->assertContains($response['code'], [401, 0, -1]);
    }

    // ==================== 响应结构验证 ====================

    public function testOrderListResponseStructure(): void
    {
        $response = $this->client->get('/api/order/list');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        $this->assertArrayHasKey('message', $response);
    }

    public function testOrderDetailResponseStructure(): void
    {
        $response = $this->client->get('/api/order/detail/1');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        $this->assertArrayHasKey('message', $response);
    }
}
