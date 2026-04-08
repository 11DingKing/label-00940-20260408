<?php

declare(strict_types=1);

use App\Grpc\OrderService;
use Hyperf\HttpServer\Router\Router;

Router::addServer('grpc', function () {
    // gRPC 服务路由
    Router::addGroup('/order.OrderService', function () {
        Router::post('/CreateOrder', [OrderService::class, 'createOrder']);
        Router::post('/GetOrderList', [OrderService::class, 'getOrderList']);
        Router::post('/GetOrderDetail', [OrderService::class, 'getOrderDetail']);
        Router::post('/CancelOrder', [OrderService::class, 'cancelOrder']);
        Router::post('/PayOrder', [OrderService::class, 'payOrder']);
        Router::post('/UpdateOrderStatus', [OrderService::class, 'updateOrderStatus']);
    });

    // 健康检查
    Router::get('/health', function () {
        return ['status' => 'ok', 'service' => 'order-service', 'protocol' => 'grpc'];
    });
});
