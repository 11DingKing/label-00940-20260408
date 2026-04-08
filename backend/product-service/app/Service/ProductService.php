<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Category;
use App\Model\Product;
use App\Grpc\GrpcClientFactory;
use Grpc\Inventory\BatchGetStockRequest;
use Grpc\Inventory\GetStockRequest;
use Grpc\Inventory\UpdateStockRequest;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

use function Hyperf\Support\make;

/**
 * 商品服务 - HTTP 控制器使用
 * 
 * 遵循微服务架构原则：
 * - 仅操作自己的数据库表 (products, categories)
 * - 通过 gRPC 调用 InventoryService 获取/管理库存
 */
class ProductService
{
    #[Inject]
    protected LoggerInterface $logger;

    protected GrpcClientFactory $grpcFactory;

    public function __construct()
    {
        $this->grpcFactory = make(GrpcClientFactory::class);
    }

    public function getProductList(int $page, int $pageSize, int $categoryId, string $keyword, int $status = -1): array
    {
        $query = Product::query()->with('category');

        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        if (!empty($keyword)) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        if ($status >= 0) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        // 通过 gRPC 获取库存信息
        $productIds = $list->pluck('id')->toArray();
        $stocks = $this->batchGetStocks($productIds);

        $products = [];
        foreach ($list as $product) {
            $stock = $stocks[$product->id] ?? 0;
            $products[] = $this->formatProduct($product, $stock);
        }

        return [
            'code' => 0,
            'message' => 'success',
            'list' => $products,
            'total' => $total,
        ];
    }

    public function getProductDetail(int $productId): array
    {
        $product = Product::query()->with('category')->find($productId);

        if (!$product) {
            return ['code' => -1, 'message' => '商品不存在', 'product' => null];
        }

        // 通过 gRPC 获取库存
        $stock = $this->getStock($productId);

        return [
            'code' => 0,
            'message' => 'success',
            'product' => $this->formatProduct($product, $stock),
        ];
    }

    public function createProduct(
        string $name,
        string $description,
        string $price,
        string $image,
        int $categoryId,
        int $stock
    ): array {
        $product = new Product();
        $product->name = $name;
        $product->description = $description;
        $product->price = $price;
        $product->image = $image;
        $product->category_id = $categoryId;
        $product->status = Product::STATUS_ON;
        $product->save();

        // 通过 gRPC 调用 InventoryService 初始化库存
        $updateRequest = new UpdateStockRequest();
        $updateRequest->setProductId($product->id);
        $updateRequest->setStock($stock);
        
        $inventoryClient = $this->grpcFactory->getInventoryServiceClient();
        [$response, $status] = $inventoryClient->updateStock($updateRequest);

        $product->load('category');

        $this->logger->info('Product created', ['product_id' => $product->id, 'name' => $name]);

        return [
            'code' => 0,
            'message' => 'success',
            'product' => $this->formatProduct($product, $stock),
        ];
    }

    public function updateProduct(
        int $productId,
        string $name,
        string $description,
        string $price,
        string $image,
        int $categoryId,
        int $status
    ): array {
        $product = Product::query()->find($productId);

        if (!$product) {
            return ['code' => -1, 'message' => '商品不存在', 'product' => null];
        }

        if (!empty($name)) {
            $product->name = $name;
        }
        if (!empty($description)) {
            $product->description = $description;
        }
        if (!empty($price)) {
            $product->price = $price;
        }
        if (!empty($image)) {
            $product->image = $image;
        }
        if ($categoryId > 0) {
            $product->category_id = $categoryId;
        }
        if ($status >= 0) {
            $product->status = $status;
        }
        $product->save();

        $product->load('category');
        
        // 通过 gRPC 获取库存
        $stock = $this->getStock($productId);

        $this->logger->info('Product updated', ['product_id' => $productId]);

        return [
            'code' => 0,
            'message' => 'success',
            'product' => $this->formatProduct($product, $stock),
        ];
    }

    public function deleteProduct(int $productId): array
    {
        $product = Product::query()->find($productId);

        if (!$product) {
            return ['code' => -1, 'message' => '商品不存在'];
        }

        // 软删除：设置状态为下架
        $product->status = Product::STATUS_OFF;
        $product->save();

        $this->logger->info('Product deleted', ['product_id' => $productId]);

        return ['code' => 0, 'message' => 'success'];
    }

    public function getCategoryList(int $parentId): array
    {
        $categories = Category::query()
            ->where('parent_id', $parentId)
            ->where('status', 1)
            ->orderBy('sort')
            ->get();

        $list = [];
        foreach ($categories as $category) {
            $list[] = $this->formatCategory($category);
        }

        return [
            'code' => 0,
            'message' => 'success',
            'list' => $list,
        ];
    }

    public function batchGetProducts(array $productIds): array
    {
        $products = Product::query()
            ->with('category')
            ->whereIn('id', $productIds)
            ->get();

        // 通过 gRPC 获取库存信息
        $stocks = $this->batchGetStocks($productIds);

        $list = [];
        foreach ($products as $product) {
            $stock = $stocks[$product->id] ?? 0;
            $list[] = $this->formatProduct($product, $stock);
        }

        return [
            'code' => 0,
            'message' => 'success',
            'list' => $list,
        ];
    }

    /**
     * 通过 gRPC 获取单个商品库存
     */
    private function getStock(int $productId): int
    {
        try {
            $request = new GetStockRequest();
            $request->setProductId($productId);
            
            $client = $this->grpcFactory->getInventoryServiceClient();
            [$response, $status] = $client->getStock($request);
            
            if ($status === 0 && $response->getCode() === 0) {
                return $response->getStock()->getAvailableStock();
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get stock via gRPC', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }
        return 0;
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
                    $stocks[$stockInfo->getProductId()] = $stockInfo->getAvailableStock();
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

    private function formatProduct(Product $product, int $stock): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description ?? '',
            'price' => (string) $product->price,
            'image' => $product->image ?? '',
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name ?? '',
            'status' => $product->status,
            'stock' => $stock,
            'created_at' => $product->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    private function formatCategory(Category $category): array
    {
        $children = [];
        if ($category->relationLoaded('children')) {
            foreach ($category->children as $child) {
                $children[] = $this->formatCategory($child);
            }
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'parent_id' => $category->parent_id,
            'sort' => $category->sort,
            'children' => $children,
        ];
    }
}
