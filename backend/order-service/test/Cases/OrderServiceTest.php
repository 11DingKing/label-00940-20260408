<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Model\Order;
use App\Service\OrderService;
use Hyperf\Testing\TestCase;

/**
 * @internal
 * @coversNothing
 */
class OrderServiceTest extends TestCase
{
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = $this->getContainer()->get(OrderService::class);
    }

    public function testCreateOrder(): void
    {
        $result = $this->orderService->createOrder(
            1, // user_id
            [
                ['product_id' => 1, 'quantity' => 1],
            ],
            '北京市朝阳区测试地址',
            '测试用户',
            '13800138000',
            '测试订单'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        
        if ($result['code'] === 0) {
            $this->assertArrayHasKey('order', $result);
            $this->assertArrayHasKey('order_no', $result['order']);
            $this->assertArrayHasKey('total_amount', $result['order']);
            $this->assertEquals(Order::STATUS_PENDING, $result['order']['status']);
        }
    }

    public function testCreateOrderWithInvalidProduct(): void
    {
        $result = $this->orderService->createOrder(
            1,
            [
                ['product_id' => 999999, 'quantity' => 1],
            ],
            '测试地址',
            '测试用户',
            '13800138000',
            ''
        );

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
    }

    public function testCreateOrderWithInsufficientStock(): void
    {
        $result = $this->orderService->createOrder(
            1,
            [
                ['product_id' => 1, 'quantity' => 999999],
            ],
            '测试地址',
            '测试用户',
            '13800138000',
            ''
        );

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
        $this->assertStringContainsString('库存不足', $result['message']);
    }

    public function testGetOrderList(): void
    {
        $result = $this->orderService->getOrderList(1, -1, 1, 10);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testGetOrderListWithStatusFilter(): void
    {
        $result = $this->orderService->getOrderList(1, Order::STATUS_PENDING, 1, 10);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        
        foreach ($result['list'] as $order) {
            $this->assertEquals(Order::STATUS_PENDING, $order['status']);
        }
    }

    public function testGetOrderDetail(): void
    {
        // 先创建一个订单
        $createResult = $this->orderService->createOrder(
            1,
            [['product_id' => 1, 'quantity' => 1]],
            '测试地址',
            '测试用户',
            '13800138000',
            ''
        );

        if ($createResult['code'] === 0) {
            $orderId = $createResult['order']['id'];
            
            $result = $this->orderService->getOrderDetail($orderId);

            $this->assertIsArray($result);
            $this->assertEquals(0, $result['code']);
            $this->assertArrayHasKey('order', $result);
            $this->assertEquals($orderId, $result['order']['id']);
            $this->assertArrayHasKey('items', $result['order']);
        }
    }

    public function testGetOrderDetailNotFound(): void
    {
        $result = $this->orderService->getOrderDetail(999999);

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
    }

    public function testCancelOrder(): void
    {
        // 先创建一个订单
        $createResult = $this->orderService->createOrder(
            1,
            [['product_id' => 1, 'quantity' => 1]],
            '测试地址',
            '测试用户',
            '13800138000',
            ''
        );

        if ($createResult['code'] === 0) {
            $orderId = $createResult['order']['id'];
            
            $result = $this->orderService->cancelOrder($orderId, 1);

            $this->assertIsArray($result);
            $this->assertEquals(0, $result['code']);
            $this->assertEquals(Order::STATUS_CANCELLED, $result['order']['status']);
        }
    }

    public function testCancelOrderByWrongUser(): void
    {
        // 先创建一个订单
        $createResult = $this->orderService->createOrder(
            1,
            [['product_id' => 1, 'quantity' => 1]],
            '测试地址',
            '测试用户',
            '13800138000',
            ''
        );

        if ($createResult['code'] === 0) {
            $orderId = $createResult['order']['id'];
            
            // 用不同的用户ID取消
            $result = $this->orderService->cancelOrder($orderId, 999);

            $this->assertIsArray($result);
            $this->assertEquals(-1, $result['code']);
        }
    }

    public function testPayOrder(): void
    {
        // 先创建一个订单
        $createResult = $this->orderService->createOrder(
            1,
            [['product_id' => 1, 'quantity' => 1]],
            '测试地址',
            '测试用户',
            '13800138000',
            ''
        );

        if ($createResult['code'] === 0) {
            $orderId = $createResult['order']['id'];
            
            $result = $this->orderService->payOrder($orderId, 1, 'alipay');

            $this->assertIsArray($result);
            $this->assertEquals(0, $result['code']);
            $this->assertEquals(Order::STATUS_PAID, $result['order']['status']);
            $this->assertNotEmpty($result['order']['paid_at']);
        }
    }

    public function testPayAlreadyPaidOrder(): void
    {
        // 先创建并支付一个订单
        $createResult = $this->orderService->createOrder(
            1,
            [['product_id' => 1, 'quantity' => 1]],
            '测试地址',
            '测试用户',
            '13800138000',
            ''
        );

        if ($createResult['code'] === 0) {
            $orderId = $createResult['order']['id'];
            
            // 第一次支付
            $this->orderService->payOrder($orderId, 1, 'alipay');
            
            // 第二次支付
            $result = $this->orderService->payOrder($orderId, 1, 'wechat');

            $this->assertIsArray($result);
            $this->assertEquals(-1, $result['code']);
        }
    }

    public function testUpdateOrderStatus(): void
    {
        // 先创建并支付一个订单
        $createResult = $this->orderService->createOrder(
            1,
            [['product_id' => 1, 'quantity' => 1]],
            '测试地址',
            '测试用户',
            '13800138000',
            ''
        );

        if ($createResult['code'] === 0) {
            $orderId = $createResult['order']['id'];
            $this->orderService->payOrder($orderId, 1, 'alipay');
            
            // 更新为已发货
            $result = $this->orderService->updateOrderStatus($orderId, Order::STATUS_SHIPPED);

            $this->assertIsArray($result);
            $this->assertEquals(0, $result['code']);
            $this->assertEquals(Order::STATUS_SHIPPED, $result['order']['status']);
        }
    }
}
