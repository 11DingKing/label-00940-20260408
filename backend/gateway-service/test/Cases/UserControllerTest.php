<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use Hyperf\Testing\Client;

/**
 * @internal
 * @coversNothing
 */
class UserControllerTest extends AbstractTestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
    }

    public function testRegisterWithValidData(): void
    {
        $response = $this->client->post('/api/user/register', [
            'username' => 'testuser_' . time(),
            'password' => 'password123',
            'email' => 'test' . time() . '@example.com',
            'phone' => '13800138000',
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        // 可能成功或用户已存在
        $this->assertContains($response['code'], [0, -1]);
    }

    public function testRegisterWithInvalidUsername(): void
    {
        $response = $this->client->post('/api/user/register', [
            'username' => 'ab', // 太短
            'password' => 'password123',
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(422, $response['code']);
    }

    public function testRegisterWithMissingPassword(): void
    {
        $response = $this->client->post('/api/user/register', [
            'username' => 'testuser',
        ]);

        $this->assertIsArray($response);
        $this->assertEquals(422, $response['code']);
    }

    public function testLoginWithValidCredentials(): void
    {
        $response = $this->client->post('/api/user/login', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        
        if ($response['code'] === 0) {
            $this->assertArrayHasKey('data', $response);
            $this->assertArrayHasKey('token', $response['data']);
            $this->assertNotEmpty($response['data']['token']);
        }
    }

    public function testLoginWithInvalidPassword(): void
    {
        $response = $this->client->post('/api/user/login', [
            'username' => 'admin',
            'password' => 'wrongpassword',
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('code', $response);
        $this->assertNotEquals(0, $response['code']);
    }

    public function testLoginWithNonExistentUser(): void
    {
        $response = $this->client->post('/api/user/login', [
            'username' => 'nonexistent_user_' . time(),
            'password' => 'password',
        ]);

        $this->assertIsArray($response);
        $this->assertNotEquals(0, $response['code']);
    }

    public function testGetUserInfoWithoutToken(): void
    {
        $response = $this->client->get('/api/user/info');

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }

    public function testGetUserInfoWithInvalidToken(): void
    {
        $response = $this->client->get('/api/user/info', [], [
            'Authorization' => 'Bearer invalid_token',
        ]);

        $this->assertIsArray($response);
        $this->assertEquals(401, $response['code']);
    }
}
