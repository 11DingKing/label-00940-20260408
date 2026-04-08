<?php

declare(strict_types=1);

namespace App\Grpc;

use Grpc\Product\BatchGetProductsRequest;
use Grpc\Product\BatchGetProductsResponse;
use Hyperf\GrpcClient\BaseClient;

/**
 * 商品服务 gRPC 客户端
 */
class ProductServiceClient extends BaseClient
{
    public function batchGetProducts(BatchGetProductsRequest $request): array
    {
        return $this->_simpleRequest(
            '/product.ProductService/BatchGetProducts',
            $request,
            [BatchGetProductsResponse::class, 'decode']
        );
    }
}
