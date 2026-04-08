<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class Inventory extends Model
{
    protected ?string $table = 'inventory';

    public bool $timestamps = false;

    protected array $fillable = [
        'product_id',
        'stock',
        'locked_stock',
    ];

    protected array $casts = [
        'id' => 'integer',
        'product_id' => 'integer',
        'stock' => 'integer',
        'locked_stock' => 'integer',
    ];

    public function getAvailableStockAttribute(): int
    {
        return $this->stock - $this->locked_stock;
    }
}
