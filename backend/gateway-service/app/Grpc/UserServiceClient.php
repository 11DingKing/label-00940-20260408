<?php

declare(strict_types=1);

namespace App\Grpc;

use Grpc\User\RegisterRequest;
use Grpc\User\LoginRequest;
use Grpc\User\GetUserRequest;
use Grpc\User\UpdateUserRequest;
use Grpc\User\ValidateTokenRequest;
use Grpc\User\UserResponse;
use Grpc\User\LoginResponse;
use Grpc\User\ValidateTokenResponse;
use Hyperf\GrpcClient\BaseClient;

/**
 * 标准 gRPC 客户端
 * 继承 Hyperf\GrpcClient\BaseClient
 */
class UserServiceClient extends BaseClient
{
    public function register(RegisterRequest $request): array
    {
        return $this->_simpleRequest(
            '/user.UserService/Register',
            $request,
            [UserResponse::class, 'decode']
        );
    }

    public function login(LoginRequest $request): array
    {
        return $this->_simpleRequest(
            '/user.UserService/Login',
            $request,
            [LoginResponse::class, 'decode']
        );
    }

    public function getUserInfo(GetUserRequest $request): array
    {
        return $this->_simpleRequest(
            '/user.UserService/GetUserInfo',
            $request,
            [UserResponse::class, 'decode']
        );
    }

    public function updateUser(UpdateUserRequest $request): array
    {
        return $this->_simpleRequest(
            '/user.UserService/UpdateUser',
            $request,
            [UserResponse::class, 'decode']
        );
    }

    public function validateToken(ValidateTokenRequest $request): array
    {
        return $this->_simpleRequest(
            '/user.UserService/ValidateToken',
            $request,
            [ValidateTokenResponse::class, 'decode']
        );
    }
}
