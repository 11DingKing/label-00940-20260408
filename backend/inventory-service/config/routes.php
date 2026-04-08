<?php

declare(strict_types=1);

use App\Grpc\InventoryService;
use Hyperf\HttpServer\Router\Router;

Router::addServer('grpc', function () {
    // gRPC 服务路由
    Router::addGroup('/inventory.InventoryService', function () {
        Router::post('/GetStock', [InventoryService::class, 'getStock']);
        Router::post('/UpdateStock', [InventoryService::class, 'updateStock']);
        Router::post('/LockStock', [InventoryService::class, 'lockStock']);
        Router::post('/UnlockStock', [InventoryService::class, 'unlockStock']);
        Router::post('/DeductStock', [InventoryService::class, 'deductStock']);
        Router::post('/BatchGetStock', [InventoryService::class, 'batchGetStock']);
    });

    // 健康检查
    Router::get('/health', function () {
        return ['status' => 'ok', 'service' => 'inventory-service', 'protocol' => 'grpc'];
    });
});
