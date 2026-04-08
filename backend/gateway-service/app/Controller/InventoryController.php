<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\LockStockRequest;
use App\Request\UpdateStockRequest;
use App\Service\InventoryService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

class InventoryController extends AbstractController
{
    protected InventoryService $inventoryService;

    public function __construct(
        ContainerInterface $container,
        RequestInterface $request,
        ResponseInterface $response,
        InventoryService $inventoryService
    ) {
        parent::__construct($container, $request, $response);
        $this->inventoryService = $inventoryService;
    }

    public function getStock(int $product_id): array
    {
        $result = $this->inventoryService->getStock($product_id);

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->success($result['stock']);
    }

    public function updateStock(UpdateStockRequest $request): array
    {
        $validated = $request->validated();
        
        $this->getLogger()->info('Stock update attempt', [
            'product_id' => $validated['product_id'],
            'stock' => $validated['stock']
        ]);

        $result = $this->inventoryService->updateStock(
            (int) $validated['product_id'],
            (int) $validated['stock']
        );

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('Stock updated successfully', [
            'product_id' => $validated['product_id']
        ]);

        return $this->success($result['stock'], '库存更新成功');
    }

    public function lockStock(LockStockRequest $request): array
    {
        $validated = $request->validated();
        
        $this->getLogger()->info('Stock lock attempt', [
            'order_no' => $validated['order_no'],
            'items_count' => count($validated['items'])
        ]);

        $result = $this->inventoryService->lockStock(
            $validated['order_no'],
            $validated['items']
        );

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('Stock locked successfully', [
            'order_no' => $validated['order_no']
        ]);

        return $this->success(null, '库存锁定成功');
    }

    public function unlockStock(LockStockRequest $request): array
    {
        $validated = $request->validated();
        
        $this->getLogger()->info('Stock unlock attempt', [
            'order_no' => $validated['order_no'],
            'items_count' => count($validated['items'])
        ]);

        $result = $this->inventoryService->unlockStock(
            $validated['order_no'],
            $validated['items']
        );

        if ($result['code'] !== 0) {
            return $this->error($result['message'], $result['code']);
        }

        $this->getLogger()->info('Stock unlocked successfully', [
            'order_no' => $validated['order_no']
        ]);

        return $this->success(null, '库存释放成功');
    }
}
