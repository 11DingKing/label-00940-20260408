<?php

declare(strict_types=1);

namespace App\Grpc;

use App\Model\Inventory;
use Grpc\Inventory\GetStockRequest;
use Grpc\Inventory\UpdateStockRequest;
use Grpc\Inventory\LockStockRequest;
use Grpc\Inventory\UnlockStockRequest;
use Grpc\Inventory\DeductStockRequest;
use Grpc\Inventory\BatchGetStockRequest;
use Grpc\Inventory\StockResponse;
use Grpc\Inventory\BaseResponse;
use Grpc\Inventory\BatchStockResponse;
use Grpc\Inventory\StockInfo;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;

use function Hyperf\Support\make;

/**
 * 标准 gRPC 服务实现
 * 使用 Protobuf Message 对象进行序列化/反序列化
 */
class InventoryService implements InventoryServiceInterface
{
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = make(LoggerInterface::class);
    }

    public function getStock(GetStockRequest $request): StockResponse
    {
        $response = new StockResponse();
        $productId = $request->getProductId();
        
        $inventory = Inventory::query()->where('product_id', $productId)->first();
        if (!$inventory) {
            $response->setCode(-1);
            $response->setMessage('库存记录不存在');
            return $response;
        }

        $response->setCode(0);
        $response->setMessage('success');
        $response->setStock($this->buildStockInfo($inventory));
        return $response;
    }

    public function updateStock(UpdateStockRequest $request): StockResponse
    {
        $response = new StockResponse();
        $productId = $request->getProductId();
        $stock = $request->getStock();

        $inventory = Inventory::query()->where('product_id', $productId)->first();
        if (!$inventory) {
            // 如果不存在则创建新记录（支持初始化库存场景）
            $inventory = new Inventory();
            $inventory->product_id = $productId;
            $inventory->stock = $stock;
            $inventory->locked_stock = 0;
            $inventory->save();
            $this->logger->info('Stock initialized via gRPC', ['product_id' => $productId, 'stock' => $stock]);
        } else {
            $inventory->stock = $stock;
            $inventory->save();
            $this->logger->info('Stock updated via gRPC', ['product_id' => $productId, 'stock' => $stock]);
        }
        
        $response->setCode(0);
        $response->setMessage('success');
        $response->setStock($this->buildStockInfo($inventory));
        return $response;
    }

    public function lockStock(LockStockRequest $request): BaseResponse
    {
        $response = new BaseResponse();
        $orderNo = $request->getOrderNo();
        $items = $request->getItems();

        Db::beginTransaction();
        try {
            foreach ($items as $item) {
                $productId = $item->getProductId();
                $quantity = $item->getQuantity();

                $inventory = Inventory::query()->where('product_id', $productId)->lockForUpdate()->first();
                if (!$inventory) {
                    throw new \Exception("商品 {$productId} 库存记录不存在");
                }

                $availableStock = $inventory->stock - $inventory->locked_stock;
                if ($availableStock < $quantity) {
                    throw new \Exception("商品 {$productId} 库存不足");
                }

                $inventory->locked_stock += $quantity;
                $inventory->save();
            }
            Db::commit();
            $this->logger->info('Stock locked via gRPC', ['order_no' => $orderNo]);
            
            $response->setCode(0);
            $response->setMessage('success');
        } catch (\Exception $e) {
            Db::rollBack();
            $this->logger->error('Stock lock failed', ['error' => $e->getMessage()]);
            $response->setCode(-1);
            $response->setMessage($e->getMessage());
        }
        return $response;
    }

    public function unlockStock(UnlockStockRequest $request): BaseResponse
    {
        $response = new BaseResponse();
        $orderNo = $request->getOrderNo();
        $items = $request->getItems();

        Db::beginTransaction();
        try {
            foreach ($items as $item) {
                $productId = $item->getProductId();
                $quantity = $item->getQuantity();

                $inventory = Inventory::query()->where('product_id', $productId)->lockForUpdate()->first();
                if ($inventory) {
                    $inventory->locked_stock = max(0, $inventory->locked_stock - $quantity);
                    $inventory->save();
                }
            }
            Db::commit();
            $this->logger->info('Stock unlocked via gRPC', ['order_no' => $orderNo]);
            
            $response->setCode(0);
            $response->setMessage('success');
        } catch (\Exception $e) {
            Db::rollBack();
            $this->logger->error('Stock unlock failed', ['error' => $e->getMessage()]);
            $response->setCode(-1);
            $response->setMessage($e->getMessage());
        }
        return $response;
    }

    public function deductStock(DeductStockRequest $request): BaseResponse
    {
        $response = new BaseResponse();
        $orderNo = $request->getOrderNo();
        $items = $request->getItems();

        Db::beginTransaction();
        try {
            foreach ($items as $item) {
                $productId = $item->getProductId();
                $quantity = $item->getQuantity();

                $inventory = Inventory::query()->where('product_id', $productId)->lockForUpdate()->first();
                if (!$inventory) {
                    throw new \Exception("商品 {$productId} 库存记录不存在");
                }

                $inventory->stock -= $quantity;
                $inventory->locked_stock = max(0, $inventory->locked_stock - $quantity);
                $inventory->save();
            }
            Db::commit();
            $this->logger->info('Stock deducted via gRPC', ['order_no' => $orderNo]);
            
            $response->setCode(0);
            $response->setMessage('success');
        } catch (\Exception $e) {
            Db::rollBack();
            $this->logger->error('Stock deduct failed', ['error' => $e->getMessage()]);
            $response->setCode(-1);
            $response->setMessage($e->getMessage());
        }
        return $response;
    }

    public function batchGetStock(BatchGetStockRequest $request): BatchStockResponse
    {
        $response = new BatchStockResponse();
        $productIds = iterator_to_array($request->getProductIds());
        
        $inventories = Inventory::query()->whereIn('product_id', $productIds)->get();

        $response->setCode(0);
        $response->setMessage('success');
        foreach ($inventories as $inventory) {
            $response->getList()[] = $this->buildStockInfo($inventory);
        }
        return $response;
    }

    private function buildStockInfo(Inventory $inventory): StockInfo
    {
        $stockInfo = new StockInfo();
        $stockInfo->setProductId($inventory->product_id);
        $stockInfo->setStock($inventory->stock);
        $stockInfo->setLockedStock($inventory->locked_stock);
        $stockInfo->setAvailableStock($inventory->stock - $inventory->locked_stock);
        return $stockInfo;
    }
}
