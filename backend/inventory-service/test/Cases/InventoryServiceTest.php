<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\InventoryService;
use Hyperf\Testing\TestCase;

/**
 * @internal
 * @coversNothing
 */
class InventoryServiceTest extends TestCase
{
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryService = $this->getContainer()->get(InventoryService::class);
    }

    public function testGetStock(): void
    {
        $result = $this->inventoryService->getStock(1);

        $this->assertIsArray($result);
        
        if ($result['code'] === 0) {
            $this->assertArrayHasKey('stock', $result);
            $this->assertArrayHasKey('product_id', $result['stock']);
            $this->assertArrayHasKey('stock', $result['stock']);
            $this->assertArrayHasKey('locked_stock', $result['stock']);
            $this->assertArrayHasKey('available_stock', $result['stock']);
        }
    }

    public function testGetStockNotFound(): void
    {
        $result = $this->inventoryService->getStock(999999);

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
    }

    public function testUpdateStock(): void
    {
        $newStock = 500;
        $result = $this->inventoryService->updateStock(1, $newStock);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        $this->assertEquals($newStock, $result['stock']['stock']);
    }

    public function testUpdateStockForNewProduct(): void
    {
        // 为不存在的产品创建库存记录
        $productId = 9999;
        $stock = 100;
        
        $result = $this->inventoryService->updateStock($productId, $stock);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        $this->assertEquals($productId, $result['stock']['product_id']);
        $this->assertEquals($stock, $result['stock']['stock']);
    }

    public function testLockStock(): void
    {
        // 先确保有足够库存
        $this->inventoryService->updateStock(1, 1000);
        
        $items = [
            ['product_id' => 1, 'quantity' => 5],
        ];
        $orderNo = 'TEST_' . uniqid();
        
        $result = $this->inventoryService->lockStock($items, $orderNo);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
    }

    public function testLockStockInsufficientStock(): void
    {
        // 先设置较少库存
        $this->inventoryService->updateStock(1, 10);
        
        $items = [
            ['product_id' => 1, 'quantity' => 999999],
        ];
        $orderNo = 'TEST_' . uniqid();
        
        $result = $this->inventoryService->lockStock($items, $orderNo);

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
        $this->assertStringContainsString('库存不足', $result['message']);
        
        // 恢复库存
        $this->inventoryService->updateStock(1, 1000);
    }

    public function testLockStockProductNotFound(): void
    {
        $items = [
            ['product_id' => 999999, 'quantity' => 1],
        ];
        $orderNo = 'TEST_' . uniqid();
        
        $result = $this->inventoryService->lockStock($items, $orderNo);

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
    }

    public function testUnlockStock(): void
    {
        // 先锁定库存
        $this->inventoryService->updateStock(1, 1000);
        $items = [
            ['product_id' => 1, 'quantity' => 5],
        ];
        $orderNo = 'TEST_' . uniqid();
        $this->inventoryService->lockStock($items, $orderNo);
        
        // 释放库存
        $result = $this->inventoryService->unlockStock($items, $orderNo);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
    }

    public function testDeductStock(): void
    {
        // 先设置库存并锁定
        $this->inventoryService->updateStock(1, 1000);
        $items = [
            ['product_id' => 1, 'quantity' => 5],
        ];
        $orderNo = 'TEST_' . uniqid();
        $this->inventoryService->lockStock($items, $orderNo);
        
        // 扣减库存
        $result = $this->inventoryService->deductStock($items, $orderNo);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
    }

    public function testBatchGetStock(): void
    {
        $result = $this->inventoryService->batchGetStock([1, 2, 3]);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        $this->assertArrayHasKey('list', $result);
        $this->assertIsArray($result['list']);
    }

    public function testAvailableStockCalculation(): void
    {
        // 设置库存
        $this->inventoryService->updateStock(1, 100);
        
        // 锁定部分库存
        $items = [['product_id' => 1, 'quantity' => 30]];
        $this->inventoryService->lockStock($items, 'TEST_' . uniqid());
        
        // 获取库存
        $result = $this->inventoryService->getStock(1);

        if ($result['code'] === 0) {
            $stock = $result['stock'];
            $expectedAvailable = $stock['stock'] - $stock['locked_stock'];
            $this->assertEquals($expectedAvailable, $stock['available_stock']);
        }
        
        // 清理：释放锁定
        $this->inventoryService->unlockStock($items, 'TEST_cleanup');
    }
}
