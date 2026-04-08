<?php

declare(strict_types=1);

namespace App\Grpc;

use Grpc\Inventory\GetStockRequest;
use Grpc\Inventory\UpdateStockRequest;
use Grpc\Inventory\LockStockRequest;
use Grpc\Inventory\UnlockStockRequest;
use Grpc\Inventory\DeductStockRequest;
use Grpc\Inventory\BatchGetStockRequest;
use Grpc\Inventory\StockResponse;
use Grpc\Inventory\BaseResponse;
use Grpc\Inventory\BatchStockResponse;
use Hyperf\GrpcClient\BaseClient;

/**
 * 标准 gRPC 客户端 - 库存服务
 */
class InventoryServiceClient extends BaseClient
{
    public function getStock(GetStockRequest $request): array
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/GetStock',
            $request,
            [StockResponse::class, 'decode']
        );
    }

    public function updateStock(UpdateStockRequest $request): array
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/UpdateStock',
            $request,
            [StockResponse::class, 'decode']
        );
    }

    public function lockStock(LockStockRequest $request): array
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/LockStock',
            $request,
            [BaseResponse::class, 'decode']
        );
    }

    public function unlockStock(UnlockStockRequest $request): array
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/UnlockStock',
            $request,
            [BaseResponse::class, 'decode']
        );
    }

    public function deductStock(DeductStockRequest $request): array
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/DeductStock',
            $request,
            [BaseResponse::class, 'decode']
        );
    }

    public function batchGetStock(BatchGetStockRequest $request): array
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/BatchGetStock',
            $request,
            [BatchStockResponse::class, 'decode']
        );
    }
}
