<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\ProductService;
use Hyperf\Testing\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ProductServiceTest extends TestCase
{
    protected ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productService = $this->getContainer()->get(ProductService::class);
    }

    public function testGetProductList(): void
    {
        $result = $this->productService->getProductList(1, 10, 0, '');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertIsArray($result['list']);
    }

    public function testGetProductListWithPagination(): void
    {
        $result = $this->productService->getProductList(1, 5, 0, '');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        $this->assertLessThanOrEqual(5, count($result['list']));
    }

    public function testGetProductListWithCategoryFilter(): void
    {
        $result = $this->productService->getProductList(1, 10, 3, '');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        
        foreach ($result['list'] as $product) {
            $this->assertEquals(3, $product['category_id']);
        }
    }

    public function testGetProductListWithKeyword(): void
    {
        $result = $this->productService->getProductList(1, 10, 0, 'iPhone');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
    }

    public function testGetProductDetail(): void
    {
        $result = $this->productService->getProductDetail(1);

        $this->assertIsArray($result);
        
        if ($result['code'] === 0) {
            $this->assertArrayHasKey('product', $result);
            $this->assertArrayHasKey('id', $result['product']);
            $this->assertArrayHasKey('name', $result['product']);
            $this->assertArrayHasKey('price', $result['product']);
            $this->assertArrayHasKey('stock', $result['product']);
        }
    }

    public function testGetProductDetailNotFound(): void
    {
        $result = $this->productService->getProductDetail(999999);

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
    }

    public function testCreateProduct(): void
    {
        $name = 'Test Product ' . uniqid();
        $result = $this->productService->createProduct(
            $name,
            'Test description',
            '99.99',
            '',
            3,
            100
        );

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        $this->assertArrayHasKey('product', $result);
        $this->assertEquals($name, $result['product']['name']);
        $this->assertEquals('99.99', $result['product']['price']);
        $this->assertEquals(100, $result['product']['stock']);
    }

    public function testUpdateProduct(): void
    {
        // 先创建一个产品
        $createResult = $this->productService->createProduct(
            'Update Test ' . uniqid(),
            'Original description',
            '50.00',
            '',
            3,
            50
        );

        if ($createResult['code'] === 0) {
            $productId = $createResult['product']['id'];
            
            // 更新产品
            $result = $this->productService->updateProduct(
                $productId,
                'Updated Name',
                'Updated description',
                '75.00',
                '',
                4,
                1
            );

            $this->assertIsArray($result);
            $this->assertEquals(0, $result['code']);
            $this->assertEquals('Updated Name', $result['product']['name']);
        }
    }

    public function testUpdateProductNotFound(): void
    {
        $result = $this->productService->updateProduct(
            999999,
            'Name',
            '',
            '',
            '',
            0,
            1
        );

        $this->assertIsArray($result);
        $this->assertEquals(-1, $result['code']);
    }

    public function testDeleteProduct(): void
    {
        // 先创建一个产品
        $createResult = $this->productService->createProduct(
            'Delete Test ' . uniqid(),
            'To be deleted',
            '10.00',
            '',
            3,
            10
        );

        if ($createResult['code'] === 0) {
            $productId = $createResult['product']['id'];
            
            // 删除产品
            $result = $this->productService->deleteProduct($productId);

            $this->assertIsArray($result);
            $this->assertEquals(0, $result['code']);
            
            // 验证已删除
            $getResult = $this->productService->getProductDetail($productId);
            $this->assertEquals(-1, $getResult['code']);
        }
    }

    public function testGetCategoryList(): void
    {
        $result = $this->productService->getCategoryList(0);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        $this->assertArrayHasKey('list', $result);
        $this->assertIsArray($result['list']);
    }

    public function testBatchGetProducts(): void
    {
        $result = $this->productService->batchGetProducts([1, 2, 3]);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['code']);
        $this->assertArrayHasKey('list', $result);
    }
}
