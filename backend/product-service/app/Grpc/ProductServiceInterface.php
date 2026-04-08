<?php

declare(strict_types=1);

namespace App\Grpc;

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

interface ProductServiceInterface
{
    public function getProductList(ProductListRequest $request): ProductListResponse;
    public function getProductDetail(ProductDetailRequest $request): ProductResponse;
    public function createProduct(CreateProductRequest $request): ProductResponse;
    public function updateProduct(UpdateProductRequest $request): ProductResponse;
    public function deleteProduct(DeleteProductRequest $request): BaseResponse;
    public function getCategoryList(CategoryListRequest $request): CategoryListResponse;
    public function batchGetProducts(BatchGetProductsRequest $request): BatchGetProductsResponse;
}
