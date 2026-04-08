<?php

declare(strict_types=1);

namespace App\Service;

use App\Grpc\GrpcClientFactory;
use App\Grpc\InventoryServiceClient;
use Grpc\Inventory\GetStockRequest;
use Grpc\Inventory\UpdateStockRequest;
use Grpc\Inventory\LockStockRequest;
use Grpc\Inventory\UnlockStockRequest;
use Grpc\Inventory\DeductStockRequest;
use Grpc\Inventory\BatchGetStockRequest;
use Grpc\Inventory\StockItem;
use Grpc\Inventory\StockResponse;
use Grpc\Inventory\BaseResponse;
use Grpc\Inventory\BatchStockResponse;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * 库存服务 - 使用 gRPC 客户端连接池
 */
class InventoryService
{
    protected GrpcClientFactory $clientFactory;
    protected LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->clientFactory = $container->get(GrpcClientFactory::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    protected function getClient(): InventoryServiceClient
    {
        return $this->clientFactory->getInventoryServiceClient();
    }

    protected function stockInfoToArray(?\Grpc\Inventory\StockInfo $info): ?array
    {
        if (!$info) return null;
        return [
            'product_id' => $info->getProductId(),
            'stock' => $info->getStock(),
            'locked_stock' => $info->getLockedStock(),
            'available_stock' => $info->getAvailableStock(),
        ];
    }

    public function getStock(int $productId): array
    {
        try {
            $request = new GetStockRequest();
            $request->setProductId($productId);

            $client = $this->getClient();
            [$response, $status] = $client->getStock($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'getStock', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var StockResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'stock' => $this->stockInfoToArray($response->getStock()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'getStock', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function updateStock(int $productId, int $stock): array
    {
        try {
            $request = new UpdateStockRequest();
            $request->setProductId($productId);
            $request->setStock($stock);

            $client = $this->getClient();
            [$response, $status] = $client->updateStock($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'updateStock', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var StockResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'stock' => $this->stockInfoToArray($response->getStock()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'updateStock', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function lockStock(string $orderNo, array $items): array
    {
        try {
            $request = new LockStockRequest();
            $request->setOrderNo($orderNo);

            foreach ($items as $item) {
                $stockItem = new StockItem();
                $stockItem->setProductId((int) ($item['product_id'] ?? 0));
                $stockItem->setQuantity((int) ($item['quantity'] ?? 0));
                $request->getItems()[] = $stockItem;
            }

            $client = $this->getClient();
            [$response, $status] = $client->lockStock($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'lockStock', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var BaseResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'lockStock', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function unlockStock(string $orderNo, array $items): array
    {
        try {
            $request = new UnlockStockRequest();
            $request->setOrderNo($orderNo);

            foreach ($items as $item) {
                $stockItem = new StockItem();
                $stockItem->setProductId((int) ($item['product_id'] ?? 0));
                $stockItem->setQuantity((int) ($item['quantity'] ?? 0));
                $request->getItems()[] = $stockItem;
            }

            $client = $this->getClient();
            [$response, $status] = $client->unlockStock($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'unlockStock', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var BaseResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'unlockStock', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function deductStock(string $orderNo, array $items): array
    {
        try {
            $request = new DeductStockRequest();
            $request->setOrderNo($orderNo);

            foreach ($items as $item) {
                $stockItem = new StockItem();
                $stockItem->setProductId((int) ($item['product_id'] ?? 0));
                $stockItem->setQuantity((int) ($item['quantity'] ?? 0));
                $request->getItems()[] = $stockItem;
            }

            $client = $this->getClient();
            [$response, $status] = $client->deductStock($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'deductStock', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var BaseResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'deductStock', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function batchGetStock(array $productIds): array
    {
        try {
            $request = new BatchGetStockRequest();
            foreach ($productIds as $id) {
                $request->getProductIds()[] = $id;
            }

            $client = $this->getClient();
            [$response, $status] = $client->batchGetStock($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'batchGetStock', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var BatchStockResponse $response */
            $list = [];
            foreach ($response->getList() as $item) {
                $list[] = $this->stockInfoToArray($item);
            }
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'list' => $list,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'batchGetStock', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }
}
