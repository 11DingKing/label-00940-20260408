<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use Firebase\JWT\JWT;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

use function Hyperf\Support\env;

class UserService
{
    #[Inject]
    protected LoggerInterface $logger;

    public function register(string $username, string $password, string $email, string $phone): array
    {
        // 检查用户名是否已存在
        $exists = User::query()->where('username', $username)->exists();
        if ($exists) {
            return ['code' => -1, 'message' => '用户名已存在', 'user' => null];
        }

        // 创建用户
        $user = new User();
        $user->username = $username;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->email = $email;
        $user->phone = $phone;
        $user->status = User::STATUS_ENABLED;
        $user->save();

        $this->logger->info('User registered', ['user_id' => $user->id, 'username' => $username]);

        return [
            'code' => 0,
            'message' => 'success',
            'user' => $this->formatUser($user),
        ];
    }

    public function login(string $username, string $password): array
    {
        $this->logger->info('Login attempt', ['username' => $username]);
        
        $user = User::query()->where('username', $username)->first();

        if (!$user) {
            $this->logger->info('User not found', ['username' => $username]);
            return ['code' => -1, 'message' => '用户不存在', 'token' => '', 'user' => null];
        }

        $this->logger->info('User found', ['user_id' => $user->id, 'status' => $user->status]);

        if (!password_verify($password, $user->password)) {
            $this->logger->info('Password mismatch', ['username' => $username]);
            return ['code' => -1, 'message' => '密码错误', 'token' => '', 'user' => null];
        }

        if ($user->status !== User::STATUS_ENABLED) {
            $this->logger->info('User disabled', ['username' => $username]);
            return ['code' => -1, 'message' => '账号已被禁用', 'token' => '', 'user' => null];
        }

        $this->logger->info('Generating token', ['user_id' => $user->id]);
        
        try {
            // 生成 JWT Token
            $token = $this->generateToken($user);
            $this->logger->info('Token generated', ['user_id' => $user->id, 'token_length' => strlen($token)]);
        } catch (\Throwable $e) {
            $this->logger->error('Token generation failed', ['error' => $e->getMessage()]);
            return ['code' => -1, 'message' => 'Token生成失败: ' . $e->getMessage(), 'token' => '', 'user' => null];
        }

        $this->logger->info('User logged in', ['user_id' => $user->id, 'username' => $username]);

        return [
            'code' => 0,
            'message' => 'success',
            'token' => $token,
            'user' => $this->formatUser($user),
        ];
    }

    public function getUserInfo(int $userId): array
    {
        $user = User::query()->find($userId);

        if (!$user) {
            return ['code' => -1, 'message' => '用户不存在', 'user' => null];
        }

        return [
            'code' => 0,
            'message' => 'success',
            'user' => $this->formatUser($user),
        ];
    }

    public function updateUser(int $userId, string $email, string $phone, string $avatar): array
    {
        $user = User::query()->find($userId);

        if (!$user) {
            return ['code' => -1, 'message' => '用户不存在', 'user' => null];
        }

        if (!empty($email)) {
            $user->email = $email;
        }
        if (!empty($phone)) {
            $user->phone = $phone;
        }
        if (!empty($avatar)) {
            $user->avatar = $avatar;
        }
        $user->save();

        $this->logger->info('User updated', ['user_id' => $userId]);

        return [
            'code' => 0,
            'message' => 'success',
            'user' => $this->formatUser($user),
        ];
    }

    public function validateToken(string $token): array
    {
        try {
            $secret = env('JWT_SECRET', 'your-secret-key');
            $decoded = JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));

            return [
                'code' => 0,
                'message' => 'success',
                'user_id' => $decoded->user_id ?? 0,
                'username' => $decoded->username ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'code' => -1,
                'message' => 'Token无效或已过期',
                'user_id' => 0,
                'username' => '',
            ];
        }
    }

    private function generateToken(User $user): string
    {
        $secret = env('JWT_SECRET', 'your-secret-key');
        $ttl = (int) env('JWT_TTL', 86400);

        $payload = [
            'iss' => 'ecommerce',
            'sub' => $user->id,
            'user_id' => $user->id,
            'username' => $user->username,
            'iat' => time(),
            'exp' => time() + $ttl,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email ?? '',
            'phone' => $user->phone ?? '',
            'avatar' => $user->avatar ?? '',
            'status' => $user->status,
            'created_at' => $user->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }
}
