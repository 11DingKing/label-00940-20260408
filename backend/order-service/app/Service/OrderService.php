<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Order;
use App\Model\OrderItem;
use App\Grpc\GrpcClientFactory;
use Grpc\Product\BatchGetProductsRequest;
use Grpc\Inventory\BatchGetStockRequest;
use Grpc\Inventory\LockStockRequest;
use Grpc\Inventory\UnlockStockRequest;
use Grpc\Inventory\DeductStockRequest;
use Grpc\Inventory\StockItem;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 订单业务服务
 * 
 * 遵循微服务架构原则：
 * - 通过 gRPC 调用 ProductService 获取商品信息
 * - 通过 gRPC 调用 InventoryService 管理库存
 * - 仅操作自己的订单数据库表
 */
class OrderService
{
    #[Inject]
    protected LoggerInterface $logger;

    #[Inject]
    protected GrpcClientFactory $grpcFactory;

    public function createOrder(
        int $userId,
        array $items,
        string $address,
        string $receiver,
        string $phone,
        string $remark
    ): array {
        if (empty($items)) {
            return ['code' => -1, 'message' => '订单商品不能为空', 'order' => null];
        }

        $productIds = array_column($items, 'product_id');

        // 1. 通过 gRPC 调用 ProductService 获取商品信息
        $productRequest = new BatchGetProductsRequest();
        $productRequest->setProductIds($productIds);
        
        $productClient = $this->grpcFactory->getProductServiceClient();
        [$productResponse, $productStatus] = $productClient->batchGetProducts($productRequest);
        
        if ($productStatus !== 0 || $productResponse->getCode() !== 0) {
            $this->logger->error('Failed to get products via gRPC', [
                'status' => $productStatus,
                'code' => $productResponse?->getCode()
            ]);
            return ['code' => -1, 'message' => '获取商品信息失败', 'order' => null];
        }

        $products = [];
        foreach ($productResponse->getList() as $product) {
            $products[$product->getId()] = $product;
        }

        if (count($products) !== count($productIds)) {
            return ['code' => -1, 'message' => '部分商品不存在', 'order' => null];
        }

        // 2. 通过 gRPC 调用 InventoryService 检查库存
        $stockRequest = new BatchGetStockRequest();
        $stockRequest->setProductIds($productIds);
        
        $inventoryClient = $this->grpcFactory->getInventoryServiceClient();
        [$stockResponse, $stockStatus] = $inventoryClient->batchGetStock($stockRequest);
        
        if ($stockStatus !== 0 || $stockResponse->getCode() !== 0) {
            $this->logger->error('Failed to get stock via gRPC', [
                'status' => $stockStatus,
                'code' => $stockResponse?->getCode()
            ]);
            return ['code' => -1, 'message' => '获取库存信息失败', 'order' => null];
        }

        $stocks = [];
        foreach ($stockResponse->getList() as $stock) {
            $stocks[$stock->getProductId()] = $stock;
        }

        // 检查库存是否充足
        foreach ($items as $item) {
            $stock = $stocks[$item['product_id']] ?? null;
            if (!$stock || $stock->getAvailableStock() < $item['quantity']) {
                $productName = $products[$item['product_id']]->getName() ?? '';
                return ['code' => -1, 'message' => "商品 {$productName} 库存不足", 'order' => null];
            }
        }

        // 3. 生成订单号
        $orderNo = $this->generateOrderNo();

        // 4. 通过 gRPC 调用 InventoryService 锁定库存
        $lockRequest = new LockStockRequest();
        $lockRequest->setOrderNo($orderNo);
        $stockItems = [];
        foreach ($items as $item) {
            $stockItem = new StockItem();
            $stockItem->setProductId($item['product_id']);
            $stockItem->setQuantity($item['quantity']);
            $stockItems[] = $stockItem;
        }
        $lockRequest->setItems($stockItems);
        
        [$lockResponse, $lockStatus] = $inventoryClient->lockStock($lockRequest);
        
        if ($lockStatus !== 0 || $lockResponse->getCode() !== 0) {
            $this->logger->error('Failed to lock stock via gRPC', [
                'status' => $lockStatus,
                'code' => $lockResponse?->getCode()
            ]);
            return ['code' => -1, 'message' => $lockResponse?->getMessage() ?: '锁定库存失败', 'order' => null];
        }

        // 5. 创建订单（仅操作订单表）
        Db::beginTransaction();
        try {
            $totalAmount = 0;
            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                $totalAmount += (float)$product->getPrice() * $item['quantity'];
            }

            $order = new Order();
            $order->order_no = $orderNo;
            $order->user_id = $userId;
            $order->total_amount = $totalAmount;
            $order->status = Order::STATUS_PENDING;
            $order->address = $address;
            $order->receiver = $receiver;
            $order->phone = $phone;
            $order->remark = $remark;
            $order->save();

            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                $orderItem->product_id = $item['product_id'];
                $orderItem->product_name = $product->getName();
                $orderItem->product_image = $product->getImage() ?? '';
                $orderItem->quantity = $item['quantity'];
                $orderItem->price = $product->getPrice();
                $orderItem->save();
            }

            Db::commit();
            $order->load('items');
            $this->logger->info('Order created', ['order_id' => $order->id, 'order_no' => $orderNo]);

            return [
                'code' => 0,
                'message' => 'success',
                'order' => $this->formatOrder($order),
            ];
        } catch (\Exception $e) {
            Db::rollBack();
            
            // 订单创建失败，释放锁定的库存
            $unlockRequest = new UnlockStockRequest();
            $unlockRequest->setOrderNo($orderNo);
            $unlockRequest->setItems($stockItems);
            $inventoryClient->unlockStock($unlockRequest);
            
            $this->logger->error('Order creation failed', ['error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '订单创建失败', 'order' => null];
        }
    }

    public function getOrderList(int $userId, int $status, int $page, int $pageSize): array
    {
        $query = Order::query()->with('items')->where('user_id', $userId);

        if ($status >= 0) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $orders = [];
        foreach ($list as $order) {
            $orders[] = $this->formatOrder($order);
        }

        return [
            'code' => 0,
            'message' => 'success',
            'list' => $orders,
            'total' => $total,
        ];
    }

    public function getOrderDetail(int $orderId, string $orderNo = ''): array
    {
        $query = Order::query()->with('items');

        if ($orderId > 0) {
            $query->where('id', $orderId);
        } elseif (!empty($orderNo)) {
            $query->where('order_no', $orderNo);
        } else {
            return ['code' => -1, 'message' => '参数错误', 'order' => null];
        }

        $order = $query->first();

        if (!$order) {
            return ['code' => -1, 'message' => '订单不存在', 'order' => null];
        }

        return [
            'code' => 0,
            'message' => 'success',
            'order' => $this->formatOrder($order),
        ];
    }

    public function cancelOrder(int $orderId, int $userId): array
    {
        $order = Order::query()->with('items')->find($orderId);

        if (!$order) {
            return ['code' => -1, 'message' => '订单不存在', 'order' => null];
        }

        if ($order->user_id !== $userId) {
            return ['code' => -1, 'message' => '无权操作此订单', 'order' => null];
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return ['code' => -1, 'message' => '订单状态不允许取消', 'order' => null];
        }

        // 通过 gRPC 调用 InventoryService 释放锁定库存
        $unlockRequest = new UnlockStockRequest();
        $unlockRequest->setOrderNo($order->order_no);
        $stockItems = [];
        foreach ($order->items as $item) {
            $stockItem = new StockItem();
            $stockItem->setProductId($item->product_id);
            $stockItem->setQuantity($item->quantity);
            $stockItems[] = $stockItem;
        }
        $unlockRequest->setItems($stockItems);
        
        $inventoryClient = $this->grpcFactory->getInventoryServiceClient();
        [$unlockResponse, $unlockStatus] = $inventoryClient->unlockStock($unlockRequest);
        
        if ($unlockStatus !== 0 || $unlockResponse->getCode() !== 0) {
            $this->logger->error('Failed to unlock stock via gRPC', [
                'order_id' => $orderId,
                'status' => $unlockStatus
            ]);
            return ['code' => -1, 'message' => '释放库存失败', 'order' => null];
        }

        $order->status = Order::STATUS_CANCELLED;
        $order->save();

        $this->logger->info('Order cancelled', ['order_id' => $orderId]);

        return [
            'code' => 0,
            'message' => 'success',
            'order' => $this->formatOrder($order),
        ];
    }

    public function payOrder(int $orderId, int $userId, string $paymentMethod): array
    {
        $order = Order::query()->with('items')->find($orderId);

        if (!$order) {
            return ['code' => -1, 'message' => '订单不存在', 'order' => null];
        }

        if ($order->user_id !== $userId) {
            return ['code' => -1, 'message' => '无权操作此订单', 'order' => null];
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return ['code' => -1, 'message' => '订单状态不允许支付', 'order' => null];
        }

        // 通过 gRPC 调用 InventoryService 扣减库存
        $deductRequest = new DeductStockRequest();
        $deductRequest->setOrderNo($order->order_no);
        $stockItems = [];
        foreach ($order->items as $item) {
            $stockItem = new StockItem();
            $stockItem->setProductId($item->product_id);
            $stockItem->setQuantity($item->quantity);
            $stockItems[] = $stockItem;
        }
        $deductRequest->setItems($stockItems);
        
        $inventoryClient = $this->grpcFactory->getInventoryServiceClient();
        [$deductResponse, $deductStatus] = $inventoryClient->deductStock($deductRequest);
        
        if ($deductStatus !== 0 || $deductResponse->getCode() !== 0) {
            $this->logger->error('Failed to deduct stock via gRPC', [
                'order_id' => $orderId,
                'status' => $deductStatus
            ]);
            return ['code' => -1, 'message' => $deductResponse?->getMessage() ?: '扣减库存失败', 'order' => null];
        }

        $order->status = Order::STATUS_PAID;
        $order->paid_at = date('Y-m-d H:i:s');
        $order->save();

        $this->logger->info('Order paid', [
            'order_id' => $orderId,
            'payment_method' => $paymentMethod
        ]);

        return [
            'code' => 0,
            'message' => 'success',
            'order' => $this->formatOrder($order),
        ];
    }

    public function updateOrderStatus(int $orderId, int $status): array
    {
        $order = Order::query()->with('items')->find($orderId);

        if (!$order) {
            return ['code' => -1, 'message' => '订单不存在', 'order' => null];
        }

        $order->status = $status;
        $order->save();

        $this->logger->info('Order status updated', ['order_id' => $orderId, 'status' => $status]);

        return [
            'code' => 0,
            'message' => 'success',
            'order' => $this->formatOrder($order),
        ];
    }

    private function generateOrderNo(): string
    {
        return date('YmdHis') . str_pad((string) mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function formatOrder(Order $order): array
    {
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_image' => $item->product_image,
                'quantity' => $item->quantity,
                'price' => (string) $item->price,
            ];
        }

        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'user_id' => $order->user_id,
            'total_amount' => (string) $order->total_amount,
            'status' => $order->status,
            'status_text' => $order->status_text,
            'address' => $order->address ?? '',
            'receiver' => $order->receiver ?? '',
            'phone' => $order->phone ?? '',
            'remark' => $order->remark ?? '',
            'paid_at' => $order->paid_at?->format('Y-m-d H:i:s') ?? '',
            'created_at' => $order->created_at?->format('Y-m-d H:i:s') ?? '',
            'items' => $items,
        ];
    }
}
