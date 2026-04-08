<?php

declare(strict_types=1);

namespace App\Grpc;

use App\Model\Order;
use App\Model\OrderItem;
use Grpc\Order\CreateOrderRequest;
use Grpc\Order\OrderListRequest;
use Grpc\Order\OrderDetailRequest;
use Grpc\Order\CancelOrderRequest;
use Grpc\Order\PayOrderRequest;
use Grpc\Order\UpdateOrderStatusRequest;
use Grpc\Order\OrderResponse;
use Grpc\Order\OrderListResponse;
use Grpc\Order\OrderInfo;
use Grpc\Order\OrderItemInfo;
use Grpc\Product\BatchGetProductsRequest;
use Grpc\Inventory\BatchGetStockRequest;
use Grpc\Inventory\LockStockRequest;
use Grpc\Inventory\UnlockStockRequest;
use Grpc\Inventory\DeductStockRequest;
use Grpc\Inventory\StockItem;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;

use function Hyperf\Support\make;

/**
 * 订单服务 gRPC 实现
 * 
 * 遵循微服务架构原则：
 * - 通过 gRPC 调用 ProductService 获取商品信息
 * - 通过 gRPC 调用 InventoryService 管理库存
 * - 仅操作自己的订单数据库表
 */
class OrderService implements OrderServiceInterface
{
    protected LoggerInterface $logger;
    protected GrpcClientFactory $grpcFactory;

    public function __construct()
    {
        $this->logger = make(LoggerInterface::class);
        $this->grpcFactory = make(GrpcClientFactory::class);
    }

    public function createOrder(CreateOrderRequest $request): OrderResponse
    {
        $response = new OrderResponse();
        $userId = $request->getUserId();
        $items = iterator_to_array($request->getItems());

        if (empty($items)) {
            $response->setCode(-1);
            $response->setMessage('订单商品不能为空');
            return $response;
        }

        $productIds = array_map(fn($item) => $item->getProductId(), $items);

        // 1. 通过 gRPC 调用 ProductService 获取商品信息
        $productRequest = new BatchGetProductsRequest();
        $productRequest->setProductIds($productIds);
        
        $productClient = $this->grpcFactory->getProductServiceClient();
        [$productResponse, $productStatus] = $productClient->batchGetProducts($productRequest);
        
        if ($productStatus !== 0 || $productResponse->getCode() !== 0) {
            $this->logger->error('Failed to get products via gRPC', [
                'status' => $productStatus,
                'code' => $productResponse?->getCode(),
                'message' => $productResponse?->getMessage()
            ]);
            $response->setCode(-1);
            $response->setMessage('获取商品信息失败');
            return $response;
        }

        $products = [];
        foreach ($productResponse->getList() as $product) {
            $products[$product->getId()] = $product;
        }

        if (count($products) !== count($productIds)) {
            $response->setCode(-1);
            $response->setMessage('部分商品不存在');
            return $response;
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
            $response->setCode(-1);
            $response->setMessage('获取库存信息失败');
            return $response;
        }

        $stocks = [];
        foreach ($stockResponse->getList() as $stock) {
            $stocks[$stock->getProductId()] = $stock;
        }

        // 检查库存是否充足
        foreach ($items as $item) {
            $stock = $stocks[$item->getProductId()] ?? null;
            if (!$stock || $stock->getAvailableStock() < $item->getQuantity()) {
                $productName = $products[$item->getProductId()]->getName() ?? '';
                $response->setCode(-1);
                $response->setMessage("商品 {$productName} 库存不足");
                return $response;
            }
        }

        // 3. 生成订单号
        $orderNo = date('YmdHis') . str_pad((string) mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

        // 4. 通过 gRPC 调用 InventoryService 锁定库存
        $lockRequest = new LockStockRequest();
        $lockRequest->setOrderNo($orderNo);
        $stockItems = [];
        foreach ($items as $item) {
            $stockItem = new StockItem();
            $stockItem->setProductId($item->getProductId());
            $stockItem->setQuantity($item->getQuantity());
            $stockItems[] = $stockItem;
        }
        $lockRequest->setItems($stockItems);
        
        [$lockResponse, $lockStatus] = $inventoryClient->lockStock($lockRequest);
        
        if ($lockStatus !== 0 || $lockResponse->getCode() !== 0) {
            $this->logger->error('Failed to lock stock via gRPC', [
                'status' => $lockStatus,
                'code' => $lockResponse?->getCode(),
                'message' => $lockResponse?->getMessage()
            ]);
            $response->setCode(-1);
            $response->setMessage($lockResponse?->getMessage() ?: '锁定库存失败');
            return $response;
        }

        // 5. 创建订单（仅操作订单表）
        Db::beginTransaction();
        try {
            $totalAmount = 0;
            foreach ($items as $item) {
                $product = $products[$item->getProductId()];
                $totalAmount += (float)$product->getPrice() * $item->getQuantity();
            }

            $order = new Order();
            $order->order_no = $orderNo;
            $order->user_id = $userId;
            $order->total_amount = $totalAmount;
            $order->status = Order::STATUS_PENDING;
            $order->address = $request->getAddress();
            $order->receiver = $request->getReceiver();
            $order->phone = $request->getPhone();
            $order->remark = $request->getRemark();
            $order->save();

            foreach ($items as $item) {
                $product = $products[$item->getProductId()];
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                $orderItem->product_id = $item->getProductId();
                $orderItem->product_name = $product->getName();
                $orderItem->product_image = $product->getImage() ?? '';
                $orderItem->quantity = $item->getQuantity();
                $orderItem->price = $product->getPrice();
                $orderItem->save();
            }

            Db::commit();
            $order->load('items');
            $this->logger->info('Order created via gRPC', ['order_id' => $order->id, 'order_no' => $orderNo]);
            
            $response->setCode(0);
            $response->setMessage('success');
            $response->setOrder($this->buildOrderInfo($order));
        } catch (\Exception $e) {
            Db::rollBack();
            
            // 订单创建失败，释放锁定的库存
            $unlockRequest = new UnlockStockRequest();
            $unlockRequest->setOrderNo($orderNo);
            $unlockRequest->setItems($stockItems);
            $inventoryClient->unlockStock($unlockRequest);
            
            $this->logger->error('Order creation failed', ['error' => $e->getMessage()]);
            $response->setCode(-1);
            $response->setMessage('订单创建失败');
        }
        
        return $response;
    }

    public function getOrderList(OrderListRequest $request): OrderListResponse
    {
        $response = new OrderListResponse();
        $userId = $request->getUserId();
        $status = $request->getStatus();
        $page = max(1, $request->getPage());
        $pageSize = $request->getPageSize() ?: 10;

        $query = Order::query()->with('items')->where('user_id', $userId);
        if ($status >= 0) $query->where('status', $status);

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')->offset(($page - 1) * $pageSize)->limit($pageSize)->get();

        foreach ($list as $order) {
            $response->getList()[] = $this->buildOrderInfo($order);
        }
        
        $response->setCode(0);
        $response->setMessage('success');
        $response->setTotal($total);
        
        return $response;
    }

    public function getOrderDetail(OrderDetailRequest $request): OrderResponse
    {
        $response = new OrderResponse();
        $orderId = $request->getOrderId();
        
        $order = Order::query()->with('items')->find($orderId);
        if (!$order) {
            $response->setCode(-1);
            $response->setMessage('订单不存在');
            return $response;
        }
        
        $response->setCode(0);
        $response->setMessage('success');
        $response->setOrder($this->buildOrderInfo($order));
        
        return $response;
    }

    public function cancelOrder(CancelOrderRequest $request): OrderResponse
    {
        $response = new OrderResponse();
        $orderId = $request->getOrderId();
        $userId = (int) $request->getUserId();

        $order = Order::query()->with('items')->find($orderId);
        if (!$order) {
            $response->setCode(-1);
            $response->setMessage('订单不存在');
            return $response;
        }
        if ((int) $order->user_id !== $userId) {
            $response->setCode(-1);
            $response->setMessage('无权操作此订单');
            return $response;
        }
        if ($order->status !== Order::STATUS_PENDING) {
            $response->setCode(-1);
            $response->setMessage('订单状态不允许取消');
            return $response;
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
                'status' => $unlockStatus,
                'code' => $unlockResponse?->getCode()
            ]);
            $response->setCode(-1);
            $response->setMessage('释放库存失败');
            return $response;
        }

        // 更新订单状态
        $order->status = Order::STATUS_CANCELLED;
        $order->save();
        
        $this->logger->info('Order cancelled via gRPC', ['order_id' => $orderId]);
        
        $response->setCode(0);
        $response->setMessage('success');
        $response->setOrder($this->buildOrderInfo($order));
        
        return $response;
    }

    public function payOrder(PayOrderRequest $request): OrderResponse
    {
        $response = new OrderResponse();
        $orderId = $request->getOrderId();
        $userId = (int) $request->getUserId();

        $order = Order::query()->with('items')->find($orderId);
        if (!$order) {
            $response->setCode(-1);
            $response->setMessage('订单不存在');
            return $response;
        }
        if ((int) $order->user_id !== $userId) {
            $response->setCode(-1);
            $response->setMessage('无权操作此订单');
            return $response;
        }
        if ($order->status !== Order::STATUS_PENDING) {
            $response->setCode(-1);
            $response->setMessage('订单状态不允许支付');
            return $response;
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
                'status' => $deductStatus,
                'code' => $deductResponse?->getCode(),
                'message' => $deductResponse?->getMessage()
            ]);
            $response->setCode(-1);
            $response->setMessage($deductResponse?->getMessage() ?: '扣减库存失败');
            return $response;
        }

        // 更新订单状态
        $order->status = Order::STATUS_PAID;
        $order->paid_at = date('Y-m-d H:i:s');
        $order->save();
        
        $this->logger->info('Order paid via gRPC', ['order_id' => $orderId]);
        
        $response->setCode(0);
        $response->setMessage('success');
        $response->setOrder($this->buildOrderInfo($order));
        
        return $response;
    }

    public function updateOrderStatus(UpdateOrderStatusRequest $request): OrderResponse
    {
        $response = new OrderResponse();
        $orderId = $request->getOrderId();
        $status = $request->getStatus();

        $order = Order::query()->with('items')->find($orderId);
        if (!$order) {
            $response->setCode(-1);
            $response->setMessage('订单不存在');
            return $response;
        }

        $order->status = $status;
        $order->save();
        
        $response->setCode(0);
        $response->setMessage('success');
        $response->setOrder($this->buildOrderInfo($order));
        
        return $response;
    }

    private function buildOrderInfo(Order $order): OrderInfo
    {
        $info = new OrderInfo();
        $info->setId($order->id);
        $info->setOrderNo($order->order_no);
        $info->setUserId($order->user_id);
        $info->setTotalAmount((string) $order->total_amount);
        $info->setStatus($order->status);
        $info->setStatusText($order->status_text);
        $info->setAddress($order->address ?? '');
        $info->setReceiver($order->receiver ?? '');
        $info->setPhone($order->phone ?? '');
        $info->setRemark($order->remark ?? '');
        $paidAt = $order->paid_at;
        $info->setPaidAt($paidAt instanceof \DateTimeInterface ? $paidAt->format('Y-m-d H:i:s') : ($paidAt ?? ''));
        $info->setCreatedAt($order->created_at?->format('Y-m-d H:i:s') ?? '');

        foreach ($order->items as $item) {
            $itemInfo = new OrderItemInfo();
            $itemInfo->setId($item->id);
            $itemInfo->setProductId($item->product_id);
            $itemInfo->setProductName($item->product_name);
            $itemInfo->setProductImage($item->product_image ?? '');
            $itemInfo->setQuantity($item->quantity);
            $itemInfo->setPrice((string) $item->price);
            $info->getItems()[] = $itemInfo;
        }
        
        return $info;
    }
}
