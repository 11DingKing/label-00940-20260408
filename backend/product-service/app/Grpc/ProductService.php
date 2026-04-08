<?php

declare(strict_types=1);

namespace App\Grpc;

use App\Model\Category;
use App\Model\Product;
use Grpc\Product\ProductListRequest;
use Grpc\Product\ProductListResponse;
use Grpc\Product\ProductDetailRequest;
use Grpc\Product\ProductResponse;
use Grpc\Product\CreateProductRequest;
use Grpc\Product\UpdateProductRequest;
use Grpc\Product\DeleteProductRequest;
use Grpc\Product\BaseResponse;
use Grpc\Product\CategoryListRequest;
use Grpc\Product\CategoryListResponse;
use Grpc\Product\BatchGetProductsRequest;
use Grpc\Product\BatchGetProductsResponse;
use Grpc\Product\ProductInfo;
use Grpc\Product\CategoryInfo;
use Grpc\Inventory\BatchGetStockRequest;
use Grpc\Inventory\GetStockRequest;
use Grpc\Inventory\UpdateStockRequest;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;

use function Hyperf\Support\make;

/**
 * 商品服务 gRPC 实现
 * 
 * 遵循微服务架构原则：
 * - 仅操作自己的数据库表 (products, categories)
 * - 通过 gRPC 调用 InventoryService 获取/管理库存
 */
class ProductService implements ProductServiceInterface
{
    protected LoggerInterface $logger;
    protected GrpcClientFactory $grpcFactory;

    public function __construct()
    {
        $this->logger = make(LoggerInterface::class);
        $this->grpcFactory = make(GrpcClientFactory::class);
    }

    public function getProductList(ProductListRequest $request): ProductListResponse
    {
        $response = new ProductListResponse();
        
        $categoryId = $request->getCategoryId();
        $keyword = $request->getKeyword();
        $page = max(1, $request->getPage());
        $pageSize = $request->getPageSize() ?: 10;

        $query = Product::query()->with('category');
        if ($categoryId > 0) $query->where('category_id', $categoryId);
        if ($keyword) $query->where('name', 'like', "%{$keyword}%");

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')->offset(($page - 1) * $pageSize)->limit($pageSize)->get();

        // 通过 gRPC 调用 InventoryService 获取库存信息
        $productIds = $list->pluck('id')->toArray();
        $stocks = $this->batchGetStocks($productIds);

        foreach ($list as $product) {
            $stock = $stocks[$product->id] ?? null;
            $response->getList()[] = $this->buildProductInfo($product, $stock);
        }

        $response->setCode(0);
        $response->setMessage('success');
        $response->setTotal($total);
        
        return $response;
    }

    public function getProductDetail(ProductDetailRequest $request): ProductResponse
    {
        $response = new ProductResponse();
        $productId = $request->getProductId();
        
        $product = Product::query()->with('category')->find($productId);
        if (!$product) {
            $response->setCode(-1);
            $response->setMessage('商品不存在');
            return $response;
        }

        // 通过 gRPC 调用 InventoryService 获取库存
        $stock = $this->getStock($productId);
        
        $response->setCode(0);
        $response->setMessage('success');
        $response->setProduct($this->buildProductInfo($product, $stock));
        
        return $response;
    }

    public function createProduct(CreateProductRequest $request): ProductResponse
    {
        $response = new ProductResponse();
        
        Db::beginTransaction();
        try {
            $product = new Product();
            $product->name = $request->getName();
            $product->description = $request->getDescription();
            $product->price = $request->getPrice();
            $product->image = $request->getImage();
            $product->category_id = $request->getCategoryId();
            $product->status = Product::STATUS_ON;
            $product->save();

            Db::commit();

            // 通过 gRPC 调用 InventoryService 初始化库存 (使用 updateStock)
            $updateRequest = new UpdateStockRequest();
            $updateRequest->setProductId($product->id);
            $updateRequest->setStock($request->getStock());
            
            $inventoryClient = $this->grpcFactory->getInventoryServiceClient();
            [$initResponse, $initStatus] = $inventoryClient->updateStock($updateRequest);
            
            if ($initStatus !== 0 || $initResponse->getCode() !== 0) {
                $this->logger->warning('Failed to init stock via gRPC', [
                    'product_id' => $product->id,
                    'status' => $initStatus,
                    'code' => $initResponse?->getCode()
                ]);
            }

            $product->load('category');
            $stock = $initResponse?->getStock();
            
            $this->logger->info('Product created via gRPC', ['product_id' => $product->id]);
            
            $response->setCode(0);
            $response->setMessage('success');
            $response->setProduct($this->buildProductInfo($product, $stock));
        } catch (\Exception $e) {
            Db::rollBack();
            $this->logger->error('Product creation failed', ['error' => $e->getMessage()]);
            $response->setCode(-1);
            $response->setMessage('创建商品失败');
        }
        
        return $response;
    }

    public function updateProduct(UpdateProductRequest $request): ProductResponse
    {
        $response = new ProductResponse();
        $productId = $request->getProductId();
        
        $product = Product::query()->find($productId);
        if (!$product) {
            $response->setCode(-1);
            $response->setMessage('商品不存在');
            return $response;
        }

        if ($request->getName()) $product->name = $request->getName();
        if ($request->getDescription()) $product->description = $request->getDescription();
        if ($request->getPrice()) $product->price = $request->getPrice();
        if ($request->getImage()) $product->image = $request->getImage();
        if ($request->getCategoryId() > 0) $product->category_id = $request->getCategoryId();
        if ($request->getStatus() > 0) $product->status = $request->getStatus();
        $product->save();

        $product->load('category');
        
        // 通过 gRPC 获取库存
        $stock = $this->getStock($productId);
        
        $this->logger->info('Product updated via gRPC', ['product_id' => $productId]);
        
        $response->setCode(0);
        $response->setMessage('success');
        $response->setProduct($this->buildProductInfo($product, $stock));
        
        return $response;
    }

    public function deleteProduct(DeleteProductRequest $request): BaseResponse
    {
        $response = new BaseResponse();
        $productId = $request->getProductId();
        
        $product = Product::query()->find($productId);
        if (!$product) {
            $response->setCode(-1);
            $response->setMessage('商品不存在');
            return $response;
        }

        $product->status = Product::STATUS_OFF;
        $product->save();
        
        $this->logger->info('Product deleted via gRPC', ['product_id' => $productId]);
        
        $response->setCode(0);
        $response->setMessage('success');
        
        return $response;
    }

    public function getCategoryList(CategoryListRequest $request): CategoryListResponse
    {
        $response = new CategoryListResponse();
        $parentId = $request->getParentId();
        
        $query = Category::query();
        if ($parentId >= 0) $query->where('parent_id', $parentId);

        $categories = $query->orderBy('sort')->get();
        
        foreach ($categories as $category) {
            $info = new CategoryInfo();
            $info->setId($category->id);
            $info->setName($category->name);
            $info->setParentId($category->parent_id ?? 0);
            $info->setSort($category->sort ?? 0);
            $response->getList()[] = $info;
        }
        
        $response->setCode(0);
        $response->setMessage('success');
        
        return $response;
    }

    public function batchGetProducts(BatchGetProductsRequest $request): BatchGetProductsResponse
    {
        $response = new BatchGetProductsResponse();
        $productIds = iterator_to_array($request->getProductIds());
        
        $products = Product::query()->with('category')->whereIn('id', $productIds)->get();
        
        // 通过 gRPC 调用 InventoryService 批量获取库存
        $stocks = $this->batchGetStocks($productIds);

        foreach ($products as $product) {
            $stock = $stocks[$product->id] ?? null;
            $response->getList()[] = $this->buildProductInfo($product, $stock);
        }
        
        $response->setCode(0);
        $response->setMessage('success');
        
        return $response;
    }

    /**
     * 通过 gRPC 获取单个商品库存
     */
    private function getStock(int $productId): ?\Grpc\Inventory\StockInfo
    {
        try {
            $request = new GetStockRequest();
            $request->setProductId($productId);
            
            $client = $this->grpcFactory->getInventoryServiceClient();
            [$response, $status] = $client->getStock($request);
            
            if ($status === 0 && $response->getCode() === 0) {
                return $response->getStock();
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get stock via gRPC', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }
        return null;
    }

    /**
     * 通过 gRPC 批量获取库存
     */
    private function batchGetStocks(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        try {
            $request = new BatchGetStockRequest();
            $request->setProductIds($productIds);
            
            $client = $this->grpcFactory->getInventoryServiceClient();
            [$response, $status] = $client->batchGetStock($request);
            
            if ($status === 0 && $response->getCode() === 0) {
                $stocks = [];
                foreach ($response->getList() as $stockInfo) {
                    $stocks[$stockInfo->getProductId()] = $stockInfo;
                }
                return $stocks;
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to batch get stocks via gRPC', [
                'product_ids' => $productIds,
                'error' => $e->getMessage()
            ]);
        }
        return [];
    }

    private function buildProductInfo(Product $product, ?\Grpc\Inventory\StockInfo $stock): ProductInfo
    {
        $info = new ProductInfo();
        $info->setId($product->id);
        $info->setName($product->name);
        $info->setDescription($product->description ?? '');
        $info->setPrice((string) $product->price);
        $info->setImage($product->image ?? '');
        $info->setCategoryId($product->category_id ?? 0);
        $info->setCategoryName($product->category?->name ?? '');
        $info->setStatus($product->status);
        $info->setStock($stock ? $stock->getAvailableStock() : 0);
        $info->setCreatedAt($product->created_at?->format('Y-m-d H:i:s') ?? '');
        return $info;
    }
}
