<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Inventory;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

class InventoryService
{
    #[Inject]
    protected LoggerInterface $logger;

    public function getStock(int $productId): array
    {
        $inventory = Inventory::query()->where('product_id', $productId)->first();

        if (!$inventory) {
            return ['code' => -1, 'message' => '库存记录不存在', 'stock' => null];
        }

        return [
            'code' => 0,
            'message' => 'success',
            'stock' => $this->formatStock($inventory),
        ];
    }

    public function updateStock(int $productId, int $stock): array
    {
        $inventory = Inventory::query()->where('product_id', $productId)->first();

        if (!$inventory) {
            // 创建新的库存记录
            $inventory = new Inventory();
            $inventory->product_id = $productId;
            $inventory->locked_stock = 0;
        }

        $inventory->stock = $stock;
        $inventory->save();

        $this->logger->info('Stock updated', ['product_id' => $productId, 'stock' => $stock]);

        return [
            'code' => 0,
            'message' => 'success',
            'stock' => $this->formatStock($inventory),
        ];
    }

    public function lockStock(array $items, string $orderNo): array
    {
        Db::beginTransaction();
        try {
            foreach ($items as $item) {
                $inventory = Inventory::query()
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$inventory) {
                    Db::rollBack();
                    return ['code' => -1, 'message' => "商品 {$item['product_id']} 库存记录不存在"];
                }

                if ($inventory->available_stock < $item['quantity']) {
                    Db::rollBack();
                    return ['code' => -1, 'message' => "商品 {$item['product_id']} 库存不足"];
                }

                $inventory->locked_stock += $item['quantity'];
                $inventory->save();
            }

            Db::commit();

            $this->logger->info('Stock locked', ['order_no' => $orderNo, 'items' => $items]);

            return ['code' => 0, 'message' => 'success'];
        } catch (\Exception $e) {
            Db::rollBack();
            $this->logger->error('Stock lock failed', ['error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '锁定库存失败'];
        }
    }

    public function unlockStock(array $items, string $orderNo): array
    {
        Db::beginTransaction();
        try {
            foreach ($items as $item) {
                $inventory = Inventory::query()
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    $inventory->locked_stock = max(0, $inventory->locked_stock - $item['quantity']);
                    $inventory->save();
                }
            }

            Db::commit();

            $this->logger->info('Stock unlocked', ['order_no' => $orderNo, 'items' => $items]);

            return ['code' => 0, 'message' => 'success'];
        } catch (\Exception $e) {
            Db::rollBack();
            $this->logger->error('Stock unlock failed', ['error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '释放库存失败'];
        }
    }

    public function deductStock(array $items, string $orderNo): array
    {
        Db::beginTransaction();
        try {
            foreach ($items as $item) {
                $inventory = Inventory::query()
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$inventory) {
                    Db::rollBack();
                    return ['code' => -1, 'message' => "商品 {$item['product_id']} 库存记录不存在"];
                }

                if ($inventory->stock < $item['quantity']) {
                    Db::rollBack();
                    return ['code' => -1, 'message' => "商品 {$item['product_id']} 库存不足"];
                }

                if ($inventory->locked_stock < $item['quantity']) {
                    Db::rollBack();
                    return ['code' => -1, 'message' => "商品 {$item['product_id']} 锁定库存不足"];
                }

                $inventory->stock -= $item['quantity'];
                $inventory->locked_stock -= $item['quantity'];
                $inventory->save();
            }

            Db::commit();

            $this->logger->info('Stock deducted', ['order_no' => $orderNo, 'items' => $items]);

            return ['code' => 0, 'message' => 'success'];
        } catch (\Exception $e) {
            Db::rollBack();
            $this->logger->error('Stock deduction failed', ['error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '扣减库存失败'];
        }
    }

    public function batchGetStock(array $productIds): array
    {
        $inventories = Inventory::query()
            ->whereIn('product_id', $productIds)
            ->get();

        $list = [];
        foreach ($inventories as $inventory) {
            $list[] = $this->formatStock($inventory);
        }

        return [
            'code' => 0,
            'message' => 'success',
            'list' => $list,
        ];
    }

    private function formatStock(Inventory $inventory): array
    {
        return [
            'product_id' => $inventory->product_id,
            'stock' => $inventory->stock,
            'locked_stock' => $inventory->locked_stock,
            'available_stock' => $inventory->available_stock,
        ];
    }
}
