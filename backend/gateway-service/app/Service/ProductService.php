<?php

declare(strict_types=1);

namespace App\Service;

use App\Grpc\GrpcClientFactory;
use App\Grpc\ProductServiceClient;
use Grpc\Product\ProductListRequest;
use Grpc\Product\ProductDetailRequest;
use Grpc\Product\CreateProductRequest;
use Grpc\Product\UpdateProductRequest;
use Grpc\Product\DeleteProductRequest;
use Grpc\Product\CategoryListRequest;
use Grpc\Product\BatchGetProductsRequest;
use Grpc\Product\ProductListResponse;
use Grpc\Product\ProductResponse;
use Grpc\Product\CategoryListResponse;
use Grpc\Product\BatchGetProductsResponse;
use Grpc\Product\BaseResponse;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * 商品服务 - 使用 gRPC 客户端连接池
 */
class ProductService
{
    protected GrpcClientFactory $clientFactory;
    protected LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->clientFactory = $container->get(GrpcClientFactory::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    protected function getClient(): ProductServiceClient
    {
        return $this->clientFactory->getProductServiceClient();
    }

    protected function productInfoToArray(?\Grpc\Product\ProductInfo $info): ?array
    {
        if (!$info) return null;
        return [
            'id' => $info->getId(),
            'name' => $info->getName(),
            'description' => $info->getDescription(),
            'price' => $info->getPrice(),
            'image' => $info->getImage(),
            'category_id' => $info->getCategoryId(),
            'category_name' => $info->getCategoryName(),
            'status' => $info->getStatus(),
            'stock' => $info->getStock(),
            'created_at' => $info->getCreatedAt(),
        ];
    }

    protected function categoryInfoToArray(?\Grpc\Product\CategoryInfo $info): ?array
    {
        if (!$info) return null;
        return [
            'id' => $info->getId(),
            'name' => $info->getName(),
            'parent_id' => $info->getParentId(),
            'sort' => $info->getSort(),
        ];
    }

    public function getProductList(int $categoryId, string $keyword, int $page, int $pageSize): array
    {
        try {
            $request = new ProductListRequest();
            $request->setCategoryId($categoryId);
            $request->setKeyword($keyword);
            $request->setPage($page);
            $request->setPageSize($pageSize);

            $client = $this->getClient();
            [$response, $status] = $client->getProductList($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'getProductList', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var ProductListResponse $response */
            $list = [];
            foreach ($response->getList() as $item) {
                $list[] = $this->productInfoToArray($item);
            }
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'list' => $list,
                'total' => $response->getTotal(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'getProductList', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function getProductDetail(int $productId): array
    {
        try {
            $request = new ProductDetailRequest();
            $request->setProductId($productId);

            $client = $this->getClient();
            [$response, $status] = $client->getProductDetail($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'getProductDetail', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var ProductResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'product' => $this->productInfoToArray($response->getProduct()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'getProductDetail', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function createProduct(string $name, string $description, float $price, string $image, int $categoryId, int $stock): array
    {
        try {
            $request = new CreateProductRequest();
            $request->setName($name);
            $request->setDescription($description);
            $request->setPrice((string) $price);
            $request->setImage($image);
            $request->setCategoryId($categoryId);
            $request->setStock($stock);

            $client = $this->getClient();
            [$response, $status] = $client->createProduct($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'createProduct', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var ProductResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'product' => $this->productInfoToArray($response->getProduct()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'createProduct', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function updateProduct(int $productId, string $name, string $description, string $price, string $image, int $categoryId, int $status): array
    {
        try {
            $request = new UpdateProductRequest();
            $request->setProductId($productId);
            $request->setName($name);
            $request->setDescription($description);
            $request->setPrice($price);
            $request->setImage($image);
            $request->setCategoryId($categoryId);
            $request->setStatus($status);

            $client = $this->getClient();
            [$response, $grpcStatus] = $client->updateProduct($request);

            if ($grpcStatus !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'updateProduct', 'status' => $grpcStatus]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $grpcStatus];
            }

            /** @var ProductResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'product' => $this->productInfoToArray($response->getProduct()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'updateProduct', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function deleteProduct(int $productId): array
    {
        try {
            $request = new DeleteProductRequest();
            $request->setProductId($productId);

            $client = $this->getClient();
            [$response, $status] = $client->deleteProduct($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'deleteProduct', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var BaseResponse $response */
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'deleteProduct', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function getCategoryList(int $parentId): array
    {
        try {
            $request = new CategoryListRequest();
            $request->setParentId($parentId);

            $client = $this->getClient();
            [$response, $status] = $client->getCategoryList($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'getCategoryList', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var CategoryListResponse $response */
            $list = [];
            foreach ($response->getList() as $item) {
                $list[] = $this->categoryInfoToArray($item);
            }
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'list' => $list,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'getCategoryList', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }

    public function batchGetProducts(array $productIds): array
    {
        try {
            $request = new BatchGetProductsRequest();
            foreach ($productIds as $id) {
                $request->getProductIds()[] = $id;
            }

            $client = $this->getClient();
            [$response, $status] = $client->batchGetProducts($request);

            if ($status !== 0) {
                $this->logger->error('gRPC call failed', ['method' => 'batchGetProducts', 'status' => $status]);
                return ['code' => -1, 'message' => 'gRPC 调用失败: status=' . $status];
            }

            /** @var BatchGetProductsResponse $response */
            $list = [];
            foreach ($response->getList() as $item) {
                $list[] = $this->productInfoToArray($item);
            }
            return [
                'code' => $response->getCode(),
                'message' => $response->getMessage(),
                'list' => $list,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Service call failed', ['method' => 'batchGetProducts', 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => '服务调用失败: ' . $e->getMessage()];
        }
    }
}
