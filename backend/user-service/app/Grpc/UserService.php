<?php

declare(strict_types=1);

namespace App\Grpc;

use App\Model\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Grpc\User\RegisterRequest;
use Grpc\User\LoginRequest;
use Grpc\User\GetUserRequest;
use Grpc\User\UpdateUserRequest;
use Grpc\User\ValidateTokenRequest;
use Grpc\User\UserResponse;
use Grpc\User\LoginResponse;
use Grpc\User\ValidateTokenResponse;
use Grpc\User\UserInfo;
use Psr\Log\LoggerInterface;

use function Hyperf\Support\env;
use function Hyperf\Support\make;

/**
 * 标准 gRPC 服务实现
 * 使用 Protobuf Message 对象进行序列化/反序列化
 */
class UserService implements UserServiceInterface
{
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = make(LoggerInterface::class);
    }

    public function register(RegisterRequest $request): UserResponse
    {
        $response = new UserResponse();
        
        $username = $request->getUsername();
        $password = $request->getPassword();
        $email = $request->getEmail();
        $phone = $request->getPhone();

        // 检查用户名是否已存在
        $exists = User::query()->where('username', $username)->exists();
        if ($exists) {
            $response->setCode(-1);
            $response->setMessage('用户名已存在');
            return $response;
        }

        // 创建用户
        $user = new User();
        $user->username = $username;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->email = $email;
        $user->phone = $phone;
        $user->status = User::STATUS_ACTIVE;
        $user->save();

        $this->logger->info('User registered via gRPC', ['user_id' => $user->id, 'username' => $username]);

        $response->setCode(0);
        $response->setMessage('success');
        $response->setUser($this->buildUserInfo($user));
        
        return $response;
    }

    public function login(LoginRequest $request): LoginResponse
    {
        $response = new LoginResponse();
        
        $username = $request->getUsername();
        $password = $request->getPassword();

        $user = User::query()->where('username', $username)->first();
        if (!$user) {
            $response->setCode(-1);
            $response->setMessage('用户不存在');
            return $response;
        }

        if (!password_verify($password, $user->password)) {
            $response->setCode(-1);
            $response->setMessage('密码错误');
            return $response;
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            $response->setCode(-1);
            $response->setMessage('用户已被禁用');
            return $response;
        }

        // 生成 JWT Token
        $token = JWT::encode([
            'user_id' => $user->id,
            'username' => $user->username,
            'exp' => time() + 86400 * 7,
        ], env('JWT_SECRET', 'hyperf-secret'), 'HS256');

        $this->logger->info('User logged in via gRPC', ['user_id' => $user->id, 'username' => $username]);

        $response->setCode(0);
        $response->setMessage('success');
        $response->setToken($token);
        $response->setUser($this->buildUserInfo($user));
        
        return $response;
    }

    public function getUserInfo(GetUserRequest $request): UserResponse
    {
        $response = new UserResponse();
        
        $userId = $request->getUserId();
        $user = User::query()->find($userId);
        
        if (!$user) {
            $response->setCode(-1);
            $response->setMessage('用户不存在');
            return $response;
        }

        $response->setCode(0);
        $response->setMessage('success');
        $response->setUser($this->buildUserInfo($user));
        
        return $response;
    }

    public function updateUser(UpdateUserRequest $request): UserResponse
    {
        $response = new UserResponse();
        
        $userId = $request->getUserId();
        $user = User::query()->find($userId);
        
        if (!$user) {
            $response->setCode(-1);
            $response->setMessage('用户不存在');
            return $response;
        }

        $email = $request->getEmail();
        $phone = $request->getPhone();
        $avatar = $request->getAvatar();

        if ($email) $user->email = $email;
        if ($phone) $user->phone = $phone;
        if ($avatar) $user->avatar = $avatar;
        $user->save();

        $this->logger->info('User updated via gRPC', ['user_id' => $userId]);

        $response->setCode(0);
        $response->setMessage('success');
        $response->setUser($this->buildUserInfo($user));
        
        return $response;
    }

    public function validateToken(ValidateTokenRequest $request): ValidateTokenResponse
    {
        $response = new ValidateTokenResponse();
        
        $token = $request->getToken();

        try {
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET', 'hyperf-secret'), 'HS256'));
            
            $response->setCode(0);
            $response->setMessage('success');
            $response->setUserId($decoded->user_id);
            $response->setUsername($decoded->username);
        } catch (\Exception $e) {
            $response->setCode(-1);
            $response->setMessage('Token无效或已过期');
            $response->setUserId(0);
            $response->setUsername('');
        }
        
        return $response;
    }

    /**
     * 构建 UserInfo Protobuf 消息
     */
    private function buildUserInfo(User $user): UserInfo
    {
        $userInfo = new UserInfo();
        $userInfo->setId($user->id);
        $userInfo->setUsername($user->username);
        $userInfo->setEmail($user->email ?? '');
        $userInfo->setPhone($user->phone ?? '');
        $userInfo->setAvatar($user->avatar ?? '');
        $userInfo->setStatus($user->status);
        $userInfo->setCreatedAt($user->created_at?->format('Y-m-d H:i:s') ?? '');
        
        return $userInfo;
    }
}
