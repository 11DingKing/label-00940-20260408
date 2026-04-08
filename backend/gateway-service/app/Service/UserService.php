<?php

declare(strict_types=1);

namespace App\Service;

use App\Grpc\GrpcClientFactory;
use App\Grpc\UserServiceClient;
use Grpc\User\RegisterRequest;
use Grpc\User\LoginRequest;
use Grpc\User\GetUserRequest;
use Grpc\User\UpdateUserRequest;
use Grpc\User\ValidateTokenRequest;
use Grpc\User\UserResponse;
use Grpc\User\LoginResponse;
use Grpc\User\ValidateTokenResponse;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * 用户服务 - 使用 gRPC 客户端连接池
 */
class UserService
{
    protected GrpcClientFactory $clientFactory;
    protected LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->clientFactory = $container->get(GrpcClientFactory::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * 获取 gRPC 客户端 (从连接池)
     */
    protected function getClient(): UserServiceClient
    {
        return $this->clientFactory->getUserServiceClient();
    }

    /**
     * 将 Protobuf UserInfo 转换为数组
     */
    protected function userInfoToArray(?\Grpc\User\UserInfo $userInfo): ?array
    {
        if (!$userInfo) {
            return null;
        }
        return [
            'id' => $userInfo->getId(),
            'username' => $userInfo->getUsername(),
            'email' => $userInfo->getEmail(),
            'phone' => $userInfo->getPhone(),
            'avatar' => $userInfo->getAvatar(),
            'status' => $userInfo->getStatus(),
            'created_at' => $userInfo->getCreatedAt(),
        ];
    }

    public function register(string $username, string $password, string $email, string $phone): array
    {
        try {
            $request = new RegisterRequest();
            $request->setUsername($username);
            $request->setPassword($password);
            $request->setEmail($email);
            $request->setPhone($phone);

            $client = $this->getClient();
            [$response, $status] = $client->register($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'register', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var UserResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'user' => $this->userInfoToArray($response->getUser()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'register', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function login(string $username, string $password): array
    {
        try {
            $request = new LoginRequest();
            $request->setUsername($username);
            $request->setPassword($password);

            $client = $this->getClient();
            [$response, $status] = $client->login($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'login', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var LoginResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'token' => $response->getToken(),
                'user' => $this->userInfoToArray($response->getUser()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'login', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function getUserInfo(int $userId): array
    {
        try {
            $request = new GetUserRequest();
            $request->setUserId($userId);

            $client = $this->getClient();
            [$response, $status] = $client->getUserInfo($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'getUserInfo', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var UserResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'user' => $this->userInfoToArray($response->getUser()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'getUserInfo', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function updateUser(int $userId, string $email, string $phone, string $avatar): array
    {
        try {
            $request = new UpdateUserRequest();
            $request->setUserId($userId);
            $request->setEmail($email);
            $request->setPhone($phone);
            $request->setAvatar($avatar);

            $client = $this->getClient();
            [$response, $status] = $client->updateUser($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'updateUser', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var UserResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'user' => $this->userInfoToArray($response->getUser()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'updateUser', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function validateToken(string $token): array
    {
        try {
            $request = new ValidateTokenRequest();
            $request->setToken($token);

            $client = $this->getClient();
            [$response, $status] = $client->validateToken($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'validateToken', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var ValidateTokenResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'user_id' => $response->getUserId(),
                'username' => $response->getUsername(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'validateToken', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }
}
