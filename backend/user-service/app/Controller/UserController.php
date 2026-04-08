<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/api/user', server: 'http')]
class UserController
{
    public function __construct(
        protected UserService $userService,
        protected RequestInterface $request
    ) {}

    #[PostMapping(path: 'register')]
    public function register(): array
    {
        return $this->userService->register(
            $this->request->input('username', ''),
            $this->request->input('password', ''),
            $this->request->input('email', ''),
            $this->request->input('phone', '')
        );
    }

    #[PostMapping(path: 'login')]
    public function login(): array
    {
        return $this->userService->login(
            $this->request->input('username', ''),
            $this->request->input('password', '')
        );
    }

    #[GetMapping(path: 'info/{id}')]
    public function info(int $id): array
    {
        return $this->userService->getUserInfo($id);
    }

    #[PutMapping(path: 'update/{id}')]
    public function update(int $id): array
    {
        return $this->userService->updateUser(
            $id,
            $this->request->input('email', ''),
            $this->request->input('phone', ''),
            $this->request->input('avatar', '')
        );
    }

    #[PostMapping(path: 'validate-token')]
    public function validateToken(): array
    {
        return $this->userService->validateToken(
            $this->request->input('token', '')
        );
    }
}
