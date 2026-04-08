<?php

declare(strict_types=1);

namespace App\Grpc;

use Grpc\Order\CreateOrderRequest;
use Grpc\Order\OrderListRequest;
use Grpc\Order\OrderDetailRequest;
use Grpc\Order\CancelOrderRequest;
use Grpc\Order\PayOrderRequest;
use Grpc\Order\UpdateOrderStatusRequest;
use Grpc\Order\OrderResponse;
use Grpc\Order\OrderListResponse;
use Hyperf\GrpcClient\BaseClient;

/**
 * 标准 gRPC 客户端 - 订单服务
 */
class OrderServiceClient extends BaseClient
{
    public function createOrder(CreateOrderRequest $request): array
    {
        return $this->_simpleRequest(
            '/order.OrderService/CreateOrder',
            $request,
            [OrderResponse::class, 'decode']
        );
    }

    public function getOrderList(OrderListRequest $request): array
    {
        return $this->_simpleRequest(
            '/order.OrderService/GetOrderList',
            $request,
            [OrderListResponse::class, 'decode']
        );
    }

    public function getOrderDetail(OrderDetailRequest $request): array
    {
        return $this->_simpleRequest(
            '/order.OrderService/GetOrderDetail',
            $request,
            [OrderResponse::class, 'decode']
        );
    }

    public function cancelOrder(CancelOrderRequest $request): array
    {
        return $this->_simpleRequest(
            '/order.OrderService/CancelOrder',
            $request,
            [OrderResponse::class, 'decode']
        );
    }

    public function payOrder(PayOrderRequest $request): array
    {
        return $this->_simpleRequest(
            '/order.OrderService/PayOrder',
            $request,
            [OrderResponse::class, 'decode']
        );
    }

    public function updateOrderStatus(UpdateOrderStatusRequest $request): array
    {
        return $this->_simpleRequest(
            '/order.OrderService/UpdateOrderStatus',
            $request,
            [OrderResponse::class, 'decode']
        );
    }
}
