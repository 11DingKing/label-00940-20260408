<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\UserService;
use Hyperf\Testing\TestCase;

/**
 * @internal
 * @coversNothing
 */
class UserServiceTest extends TestCase
{
    protected UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = $this->getContainer()->get(UserService::class);
    }

    public function testRegisterWithValidData(): void
    {
        $username = 'test_' . uniqid();
        $result = $this->userService->register(
            $username,
            'password123',
            'test@example.com',
            '13800138000'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        
        if ($result['code'] === 0) {
            $this->assertArrayHasKey('user', $result);
            $this->assertEquals($username, $result['user']['username']);
        }
    }

    public function testRegisterDuplicateUsername(): void
    {
        $username = 'duplicate_' . uniqid();
        
        // 第一次注册
        $this->userService->register($username, 'password123', '', '');
        
        // 第二次注册相同用户名
        $result = $this->userService->register($username, 'password456', '', '');

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
        $this->assertStringContainsString('已存在', $result['message']);
    }

    public function testLoginWithValidCredentials(): void
    {
        $username = 'login_test_' . uniqid();
        $password = 'password123';
        
        // 先注册
        $this->userService->register($username, $password, '', '');
        
        // 再登录
        $result = $this->userService->login($username, $password);

        $this->assertIsArray($result);
        
        if ($result['code'] === 0) {
            $this->assertArrayHasKey('token', $result);
            $this->assertNotEmpty($result['token']);
            $this->assertArrayHasKey('user', $result);
        }
    }

    public function testLoginWithWrongPassword(): void
    {
        $username = 'wrong_pwd_' . uniqid();
        
        // 先注册
        $this->userService->register($username, 'correct_password', '', '');
        
        // 用错误密码登录
        $result = $this->userService->login($username, 'wrong_password');

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
    }

    public function testLoginWithNonExistentUser(): void
    {
        $result = $this->userService->login('nonexistent_' . uniqid(), 'password');

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
    }

    public function testGetUserInfo(): void
    {
        $username = 'info_test_' . uniqid();
        
        // 先注册
        $registerResult = $this->userService->register($username, 'password123', 'test@test.com', '');
        
        if ($registerResult['code'] === 0) {
            $userId = $registerResult['user']['id'];
            
            // 获取用户信息
            $result = $this->userService->getUserInfo($userId);

            $this->assertIsArray($result);
            $this->assertEquals(0, $result['code']);
            $this->assertEquals($username, $result['user']['username']);
        }
    }

    public function testGetUserInfoNotFound(): void
    {
        $result = $this->userService->getUserInfo(999999);

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
    }

    public function testUpdateUser(): void
    {
        $username = 'update_test_' . uniqid();
        
        // 先注册
        $registerResult = $this->userService->register($username, 'password123', '', '');
        
        if ($registerResult['code'] === 0) {
            $userId = $registerResult['user']['id'];
            $newEmail = 'updated_' . uniqid() . '@test.com';
            
            // 更新用户信息
            $result = $this->userService->updateUser($userId, $newEmail, '13900139000', '');

            $this->assertIsArray($result);
            
            if ($result['code'] === 0) {
                $this->assertEquals($newEmail, $result['user']['email']);
            }
        }
    }

    public function testValidateToken(): void
    {
        $username = 'token_test_' . uniqid();
        
        // 先注册并登录
        $this->userService->register($username, 'password123', '', '');
        $loginResult = $this->userService->login($username, 'password123');
        
        if ($loginResult['code'] === 0) {
            $token = $loginResult['token'];
            
            // 验证 Token
            $result = $this->userService->validateToken($token);

            $this->assertIsArray($result);
            $this->assertEquals(0, $result['code']);
            $this->assertEquals($username, $result['username']);
        }
    }

    public function testValidateInvalidToken(): void
    {
        $result = $this->userService->validateToken('invalid_token');

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
    }
}
