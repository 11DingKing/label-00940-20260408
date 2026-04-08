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

/**
 * gRPC UserService 接口定义
 * 对应 proto/user.proto 中的 service UserService
 */
interface UserServiceInterface
{
    public function register(RegisterRequest $request): UserResponse;
    
    public function login(LoginRequest $request): LoginResponse;
    
    public function getUserInfo(GetUserRequest $request): UserResponse;
    
    public function updateUser(UpdateUserRequest $request): UserResponse;
    
    public function validateToken(ValidateTokenRequest $request): ValidateTokenResponse;
}
