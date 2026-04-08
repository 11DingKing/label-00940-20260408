<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractController
{
    protected ContainerInterface $container;
    protected RequestInterface $request;
    protected ResponseInterface $response;

    public function __construct(
        ContainerInterface $container,
        RequestInterface $request,
        ResponseInterface $response
    ) {
        $this->container = $container;
        $this->request = $request;
        $this->response = $response;
    }

    protected function success(mixed $data = null, string $message = 'success'): array
    {
        return [
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function error(string $message = 'error', int $code = -1, mixed $data = null): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function getUserId(): int
    {
        return (int) $this->request->getAttribute('user_id', 0);
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->container->get(LoggerInterface::class);
    }
}
