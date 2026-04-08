<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\LoginRequest;
use App\Request\RegisterRequest;
use App\Request\UpdateUserRequest;
use App\Service\UserService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

class UserController extends AbstractController
{
    protected UserService $userService;

    public function __construct(
        ContainerInterface $container,
        RequestInterface $request,
        ResponseInterface $response,
        UserService $userService
    ) {
        parent::__construct($container, $request, $response);
        $this->userService = $userService;
    }

    public function register(RegisterRequest $request): array
    {
        $validated = $request->validated();
        
        $this->getLogger()->info('User registration attempt', ['username' => $validated['username']]);
        
        $result = $this->userService->register(
            $validated['username'],
            $validated['password'],
            $validated['email'] ?? '',
            $validated['phone'] ?? ''
        );

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('User registered successfully', ['username' => $validated['username']]);
        
        return $this->success($result['user'], '注册成功');
    }

    public function login(LoginRequest $request): array
    {
        $validated = $request->validated();
        
        $this->getLogger()->info('User login attempt', ['username' => $validated['username']]);
        
        $result = $this->userService->login(
            $validated['username'],
            $validated['password']
        );

        if ($result['code'] !== 0) {
            $this->getLogger()->warning('User login failed', ['username' => $validated['username']]);
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('User logged in successfully', ['username' => $validated['username']]);
        
        return $this->success([
            'token' => $result['token'],
            'user' => $result['user'],
        ], '登录成功');
    }

    public function info(): array
    {
        $userId = $this->getUserId();
        
        $result = $this->userService->getUserInfo($userId);

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->success($result['user']);
    }

    public function update(UpdateUserRequest $request): array
    {
        $userId = $this->getUserId();
        $validated = $request->validated();
        
        $this->getLogger()->info('User update attempt', ['user_id' => $userId]);
        
        $result = $this->userService->updateUser(
            $userId,
            $validated['email'] ?? '',
            $validated['phone'] ?? '',
            $validated['avatar'] ?? ''
        );

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('User updated successfully', ['user_id' => $userId]);
        
        return $this->success($result['user'], '更新成功');
    }
}
