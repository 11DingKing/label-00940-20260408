<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OrderService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/api/order', server: 'http')]
class OrderController
{
    public function __construct(
        protected OrderService $orderService,
        protected RequestInterface $request
    ) {}

    #[PostMapping(path: 'create')]
    public function create(): array
    {
        return $this->orderService->createOrder(
            (int) $this->request->input('user_id', 0),
            $this->request->input('items', []),
            $this->request->input('address', ''),
            $this->request->input('receiver', ''),
            $this->request->input('phone', ''),
            $this->request->input('remark', '')
        );
    }

    #[GetMapping(path: 'list')]
    public function list(): array
    {
        return $this->orderService->getOrderList(
            (int) $this->request->input('user_id', 0),
            (int) $this->request->input('status', -1),
            (int) $this->request->input('page', 1),
            (int) $this->request->input('page_size', 10)
        );
    }

    #[GetMapping(path: 'detail/{id}')]
    public function detail(int $id): array
    {
        return $this->orderService->getOrderDetail($id);
    }

    #[PutMapping(path: 'cancel/{id}')]
    public function cancel(int $id): array
    {
        $userId = (int) $this->request->input('user_id', 0);
        return $this->orderService->cancelOrder($id, $userId);
    }

    #[PutMapping(path: 'pay/{id}')]
    public function pay(int $id): array
    {
        $userId = (int) $this->request->input('user_id', 0);
        $paymentMethod = $this->request->input('payment_method', 'alipay');
        return $this->orderService->payOrder($id, $userId, $paymentMethod);
    }
}
