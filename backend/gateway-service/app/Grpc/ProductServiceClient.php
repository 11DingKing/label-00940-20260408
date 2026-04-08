<?php

declare(strict_types=1);

namespace App\Grpc;

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
use Hyperf\GrpcClient\BaseClient;

/**
 * 标准 gRPC 客户端 - 商品服务
 */
class ProductServiceClient extends BaseClient
{
    public function getProductList(ProductListRequest $request): array
    {
        return $this->_simpleRequest(
            '/product.ProductService/GetProductList',
            $request,
            [ProductListResponse::class, 'decode']
        );
    }

    public function getProductDetail(ProductDetailRequest $request): array
    {
        return $this->_simpleRequest(
            '/product.ProductService/GetProductDetail',
            $request,
            [ProductResponse::class, 'decode']
        );
    }

    public function createProduct(CreateProductRequest $request): array
    {
        return $this->_simpleRequest(
            '/product.ProductService/CreateProduct',
            $request,
            [ProductResponse::class, 'decode']
        );
    }

    public function updateProduct(UpdateProductRequest $request): array
    {
        return $this->_simpleRequest(
            '/product.ProductService/UpdateProduct',
            $request,
            [ProductResponse::class, 'decode']
        );
    }

    public function deleteProduct(DeleteProductRequest $request): array
    {
        return $this->_simpleRequest(
            '/product.ProductService/DeleteProduct',
            $request,
            [BaseResponse::class, 'decode']
        );
    }

    public function getCategoryList(CategoryListRequest $request): array
    {
        return $this->_simpleRequest(
            '/product.ProductService/GetCategoryList',
            $request,
            [CategoryListResponse::class, 'decode']
        );
    }

    public function batchGetProducts(BatchGetProductsRequest $request): array
    {
        return $this->_simpleRequest(
            '/product.ProductService/BatchGetProducts',
            $request,
            [BatchGetProductsResponse::class, 'decode']
        );
    }
}
