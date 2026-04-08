<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\CreateOrderRequest;
use App\Service\OrderService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

class OrderController extends AbstractController
{
    protected OrderService $orderService;

    public function __construct(
        ContainerInterface $container,
        RequestInterface $request,
        ResponseInterface $response,
        OrderService $orderService
    ) {
        parent::__construct($container, $request, $response);
        $this->orderService = $orderService;
    }

    public function create(CreateOrderRequest $request): array
    {
        $userId = $this->getUserId();
        $validated = $request->validated();
        
        $this->getLogger()->info('Order creation attempt', ['user_id' => $userId]);

        $result = $this->orderService->createOrder(
            $userId,
            $validated['items'],
            $validated['address'],
            $validated['receiver'],
            $validated['phone'],
            $validated['remark'] ?? ''
        );

        if ($result['code'] !== 0) {
            $this->getLogger()->error('Order creation failed', [
                'user_id' => $userId,
                'error' => $result['message']
            ]);
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('Order created successfully', [
            'user_id' => $userId,
            'order_no' => $result['order']['order_no'] ?? ''
        ]);

        return $this->success($result['order'], '订单创建成功');
    }

    public function list(): array
    {
        $userId = $this->getUserId();
        $status = $this->request->input('status');
        $page = (int) $this->request->input('page', 1);
        $pageSize = (int) $this->request->input('page_size', 10);

        $result = $this->orderService->getOrderList(
            $userId,
            $status !== null ? (int) $status : -1,
            $page,
            $pageSize
        );

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->success([
            'list' => $result['list'],
            'total' => $result['total'],
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function detail(int $id): array
    {
        $result = $this->orderService->getOrderDetail($id);

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->success($result['order']);
    }

    public function cancel(int $id): array
    {
        $userId = $this->getUserId();
        
        $this->getLogger()->info('Order cancellation attempt', [
            'user_id' => $userId,
            'order_id' => $id
        ]);

        $result = $this->orderService->cancelOrder($id, $userId);

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('Order cancelled successfully', [
            'user_id' => $userId,
            'order_id' => $id
        ]);

        return $this->success($result['order'], '订单已取消');
    }

    public function pay(int $id): array
    {
        $userId = $this->getUserId();
        $paymentMethod = $this->request->input('payment_method', 'alipay');
        
        $this->getLogger()->info('Order payment attempt', [
            'user_id' => $userId,
            'order_id' => $id,
            'payment_method' => $paymentMethod
        ]);

        $result = $this->orderService->payOrder($id, $userId, $paymentMethod);

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('Order paid successfully', [
            'user_id' => $userId,
            'order_id' => $id
        ]);

        return $this->success($result['order'], '支付成功');
    }
}
