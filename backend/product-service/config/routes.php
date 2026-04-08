<?php

declare(strict_types=1);

use App\Grpc\ProductService;
use Hyperf\HttpServer\Router\Router;

Router::addServer('grpc', function () {
    // gRPC 服务路由
    Router::addGroup('/product.ProductService', function () {
        Router::post('/GetProductList', [ProductService::class, 'getProductList']);
        Router::post('/GetProductDetail', [ProductService::class, 'getProductDetail']);
        Router::post('/CreateProduct', [ProductService::class, 'createProduct']);
        Router::post('/UpdateProduct', [ProductService::class, 'updateProduct']);
        Router::post('/DeleteProduct', [ProductService::class, 'deleteProduct']);
        Router::post('/BatchGetProducts', [ProductService::class, 'batchGetProducts']);
        Router::post('/GetCategoryList', [ProductService::class, 'getCategoryList']);
    });

    // 健康检查
    Router::get('/health', function () {
        return ['status' => 'ok', 'service' => 'product-service', 'protocol' => 'grpc'];
    });
});
