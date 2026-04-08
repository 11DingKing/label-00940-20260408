<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\InventoryService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

#[Controller(prefix: '/api/inventory', server: 'http')]
class InventoryController
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected RequestInterface $request
    ) {}

    #[GetMapping(path: 'stock/{product_id}')]
    public function getStock(int $product_id): array
    {
        return $this->inventoryService->getStock($product_id);
    }

    #[PutMapping(path: 'update')]
    public function updateStock(): array
    {
        return $this->inventoryService->updateStock(
            (int) $this->request->input('product_id', 0),
            (int) $this->request->input('stock', 0)
        );
    }

    #[PostMapping(path: 'lock')]
    public function lockStock(): array
    {
        return $this->inventoryService->lockStock(
            $this->request->input('items', []),
            $this->request->input('order_no', '')
        );
    }

    #[PostMapping(path: 'unlock')]
    public function unlockStock(): array
    {
        return $this->inventoryService->unlockStock(
            $this->request->input('items', []),
            $this->request->input('order_no', '')
        );
    }
}
