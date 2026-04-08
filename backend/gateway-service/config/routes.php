<?php

declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;
use App\Middleware\AuthMiddleware;

// 健康检查
Router::get('/health', function () {
    return ['status' => 'ok', 'service' => 'gateway'];
});

// 用户相关路由 - 无需认证
Router::addGroup('/api/user', function () {
    Router::post('/register', [App\Controller\UserController::class, 'register']);
    Router::post('/login', [App\Controller\UserController::class, 'login']);
});

// 用户相关路由 - 需要认证
Router::addGroup('/api/user', function () {
    Router::get('/info', [App\Controller\UserController::class, 'info']);
    Router::put('/update', [App\Controller\UserController::class, 'update']);
}, ['middleware' => [AuthMiddleware::class]]);

// 商品相关路由 - 公开
Router::addGroup('/api/product', function () {
    Router::get('/list', [App\Controller\ProductController::class, 'list']);
    Router::get('/detail/{id:\d+}', [App\Controller\ProductController::class, 'detail']);
    Router::get('/category/list', [App\Controller\ProductController::class, 'categoryList']);
});

// 商品管理路由 - 需要认证
Router::addGroup('/api/product', function () {
    Router::post('/create', [App\Controller\ProductController::class, 'create']);
    Router::put('/update/{id:\d+}', [App\Controller\ProductController::class, 'update']);
    Router::delete('/delete/{id:\d+}', [App\Controller\ProductController::class, 'delete']);
}, ['middleware' => [AuthMiddleware::class]]);

// 订单相关路由 - 需要认证
Router::addGroup('/api/order', function () {
    Router::post('/create', [App\Controller\OrderController::class, 'create']);
    Router::get('/list', [App\Controller\OrderController::class, 'list']);
    Router::get('/detail/{id:\d+}', [App\Controller\OrderController::class, 'detail']);
    Router::put('/cancel/{id:\d+}', [App\Controller\OrderController::class, 'cancel']);
    Router::put('/pay/{id:\d+}', [App\Controller\OrderController::class, 'pay']);
}, ['middleware' => [AuthMiddleware::class]]);

// 库存相关路由 - 需要认证
Router::addGroup('/api/inventory', function () {
    Router::get('/stock/{product_id:\d+}', [App\Controller\InventoryController::class, 'getStock']);
    Router::put('/update', [App\Controller\InventoryController::class, 'updateStock']);
    Router::post('/lock', [App\Controller\InventoryController::class, 'lockStock']);
    Router::post('/unlock', [App\Controller\InventoryController::class, 'unlockStock']);
}, ['middleware' => [AuthMiddleware::class]]);
