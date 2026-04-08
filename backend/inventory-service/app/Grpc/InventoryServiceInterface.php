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

interface InventoryServiceInterface
{
    public function getStock(GetStockRequest $request): StockResponse;
    public function updateStock(UpdateStockRequest $request): StockResponse;
    public function lockStock(LockStockRequest $request): BaseResponse;
    public function unlockStock(UnlockStockRequest $request): BaseResponse;
    public function deductStock(DeductStockRequest $request): BaseResponse;
    public function batchGetStock(BatchGetStockRequest $request): BatchStockResponse;
}
