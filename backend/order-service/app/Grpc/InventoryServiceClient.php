<?php

declare(strict_types=1);

namespace App\Grpc;

use Grpc\Inventory\LockStockRequest;
use Grpc\Inventory\UnlockStockRequest;
use Grpc\Inventory\DeductStockRequest;
use Grpc\Inventory\BatchGetStockRequest;
use Grpc\Inventory\BaseResponse;
use Grpc\Inventory\BatchStockResponse;
use Hyperf\GrpcClient\BaseClient;

/**
 * 库存服务 gRPC 客户端
 */
class InventoryServiceClient extends BaseClient
{
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
