<?php

declare(strict_types=1);

use App\Grpc\UserService;
use Hyperf\HttpServer\Router\Router;

Router::addServer('grpc', function () {
    // gRPC 服务路由
    Router::addGroup('/user.UserService', function () {
        Router::post('/Register', [UserService::class, 'register']);
        Router::post('/Login', [UserService::class, 'login']);
        Router::post('/GetUserInfo', [UserService::class, 'getUserInfo']);
        Router::post('/UpdateUser', [UserService::class, 'updateUser']);
        Router::post('/ValidateToken', [UserService::class, 'validateToken']);
    });

    // 健康检查
    Router::get('/health', function () {
        return ['status' => 'ok', 'service' => 'user-service', 'protocol' => 'grpc'];
    });
});
