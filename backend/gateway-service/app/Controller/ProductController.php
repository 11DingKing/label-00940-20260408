<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\CreateProductRequest;
use App\Request\UpdateProductRequest;
use App\Service\ProductService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

class ProductController extends AbstractController
{
    protected ProductService $productService;

    public function __construct(
        ContainerInterface $container,
        RequestInterface $request,
        ResponseInterface $response,
        ProductService $productService
    ) {
        parent::__construct($container, $request, $response);
        $this->productService = $productService;
    }

    public function list(): array
    {
        $page = (int) $this->request->input('page', 1);
        $pageSize = (int) $this->request->input('page_size', 10);
        $categoryId = (int) $this->request->input('category_id', 0);
        $keyword = $this->request->input('keyword', '');

        $result = $this->productService->getProductList($categoryId, $keyword, $page, $pageSize);

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
        $result = $this->productService->getProductDetail($id);

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->success($result['product']);
    }

    public function create(CreateProductRequest $request): array
    {
        $validated = $request->validated();
        
        $this->getLogger()->info('Product creation attempt', ['name' => $validated['name']]);

        $result = $this->productService->createProduct(
            $validated['name'],
            $validated['description'] ?? '',
            (float) $validated['price'],
            $validated['image'] ?? '',
            (int) $validated['category_id'],
            (int) ($validated['stock'] ?? 0)
        );

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('Product created successfully', ['product_id' => $result['product']['id'] ?? 0]);

        return $this->success($result['product'], '创建成功');
    }

    public function update(int $id, UpdateProductRequest $request): array
    {
        $validated = $request->validated();
        
        $this->getLogger()->info('Product update attempt', ['product_id' => $id]);

        $result = $this->productService->updateProduct(
            $id,
            $validated['name'] ?? '',
            $validated['description'] ?? '',
            $validated['price'] ?? '',
            $validated['image'] ?? '',
            (int) ($validated['category_id'] ?? 0),
            (int) ($validated['status'] ?? 1)
        );

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('Product updated successfully', ['product_id' => $id]);

        return $this->success($result['product'], '更新成功');
    }

    public function delete(int $id): array
    {
        $this->getLogger()->info('Product deletion attempt', ['product_id' => $id]);

        $result = $this->productService->deleteProduct($id);

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('Product deleted successfully', ['product_id' => $id]);

        return $this->success(null, '删除成功');
    }

    public function categoryList(): array
    {
        $parentId = (int) $this->request->input('parent_id', 0);

        $result = $this->productService->getCategoryList($parentId);

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->success($result['list']);
    }
}
