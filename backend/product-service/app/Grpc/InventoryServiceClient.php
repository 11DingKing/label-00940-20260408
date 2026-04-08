<?php

declare(strict_types=1);

namespace App\Grpc;

use Grpc\Inventory\GetStockRequest;
use Grpc\Inventory\UpdateStockRequest;
use Grpc\Inventory\InitStockRequest;
use Grpc\Inventory\BatchGetStockRequest;
use Grpc\Inventory\StockResponse;
use Grpc\Inventory\BatchStockResponse;
use Hyperf\GrpcClient\BaseClient;

/**
 * 库存服务 gRPC 客户端
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

    public function initStock(InitStockRequest $request): array
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/InitStock',
            $request,
            [StockResponse::class, 'decode']
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
