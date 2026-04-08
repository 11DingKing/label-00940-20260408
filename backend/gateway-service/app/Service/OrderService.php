<?php

declare(strict_types=1);

namespace App\Service;

use App\Grpc\GrpcClientFactory;
use App\Grpc\OrderServiceClient;
use Grpc\Order\CreateOrderRequest;
use Grpc\Order\OrderListRequest;
use Grpc\Order\OrderDetailRequest;
use Grpc\Order\CancelOrderRequest;
use Grpc\Order\PayOrderRequest;
use Grpc\Order\UpdateOrderStatusRequest;
use Grpc\Order\OrderItemRequest;
use Grpc\Order\OrderResponse;
use Grpc\Order\OrderListResponse;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * 订单服务 - 使用 gRPC 客户端连接池
 */
class OrderService
{
    protected GrpcClientFactory $clientFactory;
    protected LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->clientFactory = $container->get(GrpcClientFactory::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    protected function getClient(): OrderServiceClient
    {
        return $this->clientFactory->getOrderServiceClient();
    }

    protected function orderInfoToArray(?\Grpc\Order\OrderInfo $info): ?array
    {
        if (!$info) return null;
        $items = [];
        foreach ($info->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'product_id' => $item->getProductId(),
                'product_name' => $item->getProductName(),
                'product_image' => $item->getProductImage(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice(),
            ];
        }
        return [
            'id' => $info->getId(),
            'order_no' => $info->getOrderNo(),
            'user_id' => $info->getUserId(),
            'total_amount' => $info->getTotalAmount(),
            'status' => $info->getStatus(),
            'status_text' => $info->getStatusText(),
            'address' => $info->getAddress(),
            'receiver' => $info->getReceiver(),
            'phone' => $info->getPhone(),
            'remark' => $info->getRemark(),
            'paid_at' => $info->getPaidAt(),
            'created_at' => $info->getCreatedAt(),
            'items' => $items,
        ];
    }

    public function createOrder(int $userId, array $items, string $address, string $receiver, string $phone, string $remark): array
    {
        try {
            $request = new CreateOrderRequest();
            $request->setUserId($userId);
            $request->setAddress($address);
            $request->setReceiver($receiver);
            $request->setPhone($phone);
            $request->setRemark($remark);

            foreach ($items as $item) {
                $orderItem = new OrderItemRequest();
                $orderItem->setProductId((int) ($item['product_id'] ?? 0));
                $orderItem->setQuantity((int) ($item['quantity'] ?? 0));
                $request->getItems()[] = $orderItem;
            }

            $client = $this->getClient();
            [$response, $status] = $client->createOrder($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'createOrder', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var OrderResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'order' => $this->orderInfoToArray($response->getOrder()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'createOrder', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function getOrderList(int $userId, int $status, int $page, int $pageSize): array
    {
        try {
            $request = new OrderListRequest();
            $request->setUserId($userId);
            $request->setStatus($status);
            $request->setPage($page);
            $request->setPageSize($pageSize);

            $client = $this->getClient();
            [$response, $grpcStatus] = $client->getOrderList($request);

            if ($grpcStatus !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'getOrderList', 'status' => $grpcStatus]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $grpcStatus];
            }

            /** @var OrderListResponse $response */
            $list = [];
            foreach ($response->getList() as $item) {
                $list[] = $this->orderInfoToArray($item);
            }
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'list' => $list,
                'total' => $response->getTotal(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'getOrderList', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function getOrderDetail(int $orderId): array
    {
        try {
            $request = new OrderDetailRequest();
            $request->setOrderId($orderId);

            $client = $this->getClient();
            [$response, $status] = $client->getOrderDetail($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'getOrderDetail', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var OrderResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'order' => $this->orderInfoToArray($response->getOrder()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'getOrderDetail', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function cancelOrder(int $orderId, int $userId): array
    {
        try {
            $request = new CancelOrderRequest();
            $request->setOrderId($orderId);
            $request->setUserId($userId);

            $client = $this->getClient();
            [$response, $status] = $client->cancelOrder($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'cancelOrder', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var OrderResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'order' => $this->orderInfoToArray($response->getOrder()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'cancelOrder', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function payOrder(int $orderId, int $userId, string $paymentMethod): array
    {
        try {
            $request = new PayOrderRequest();
            $request->setOrderId($orderId);
            $request->setUserId($userId);
            $request->setPaymentMethod($paymentMethod);

            $client = $this->getClient();
            [$response, $status] = $client->payOrder($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'payOrder', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var OrderResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'order' => $this->orderInfoToArray($response->getOrder()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'payOrder', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function updateOrderStatus(int $orderId, int $status): array
    {
        try {
            $request = new UpdateOrderStatusRequest();
            $request->setOrderId($orderId);
            $request->setStatus($status);

            $client = $this->getClient();
            [$response, $grpcStatus] = $client->updateOrderStatus($request);

            if ($grpcStatus !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'updateOrderStatus', 'status' => $grpcStatus]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $grpcStatus];
            }

            /** @var OrderResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'order' => $this->orderInfoToArray($response->getOrder()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'updateOrderStatus', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }
}
