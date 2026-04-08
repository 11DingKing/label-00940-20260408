<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ProductService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/api/product', server: 'http')]
class ProductController
{
    public function __construct(
        protected ProductService $productService,
        protected RequestInterface $request
    ) {}

    #[GetMapping(path: 'list')]
    public function list(): array
    {
        $page = (int) $this->request->input('page', 1);
        $pageSize = (int) $this->request->input('page_size', 10);
        $categoryId = (int) $this->request->input('category_id', 0);
        $keyword = $this->request->input('keyword', '');
        $status = (int) $this->request->input('status', -1);

        return $this->productService->getProductList($page, $pageSize, $categoryId, $keyword, $status);
    }

    #[GetMapping(path: 'detail/{id}')]
    public function detail(int $id): array
    {
        return $this->productService->getProductDetail($id);
    }

    #[PostMapping(path: 'create')]
    public function create(): array
    {
        return $this->productService->createProduct(
            $this->request->input('name', ''),
            $this->request->input('description', ''),
            $this->request->input('price', '0'),
            $this->request->input('image', ''),
            (int) $this->request->input('category_id', 0),
            (int) $this->request->input('stock', 0)
        );
    }

    #[PutMapping(path: 'update/{id}')]
    public function update(int $id): array
    {
        return $this->productService->updateProduct(
            $id,
            $this->request->input('name', ''),
            $this->request->input('description', ''),
            $this->request->input('price', '0'),
            $this->request->input('image', ''),
            (int) $this->request->input('category_id', 0),
            (int) $this->request->input('status', 1)
        );
    }

    #[DeleteMapping(path: 'delete/{id}')]
    public function delete(int $id): array
    {
        return $this->productService->deleteProduct($id);
    }

    #[GetMapping(path: 'category/list')]
    public function categoryList(): array
    {
        $parentId = (int) $this->request->input('parent_id', 0);
        return $this->productService->getCategoryList($parentId);
    }

    #[PostMapping(path: 'batch')]
    public function batch(): array
    {
        $productIds = $this->request->input('product_ids', []);
        return $this->productService->batchGetProducts($productIds);
    }
}
