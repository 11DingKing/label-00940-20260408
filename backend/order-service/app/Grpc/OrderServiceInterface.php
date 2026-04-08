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

interface OrderServiceInterface
{
    public function createOrder(CreateOrderRequest $request): OrderResponse;
    public function getOrderList(OrderListRequest $request): OrderListResponse;
    public function getOrderDetail(OrderDetailRequest $request): OrderResponse;
    public function cancelOrder(CancelOrderRequest $request): OrderResponse;
    public function payOrder(PayOrderRequest $request): OrderResponse;
    public function updateOrderStatus(UpdateOrderStatusRequest $request): OrderResponse;
}
