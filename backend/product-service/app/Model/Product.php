<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class Product extends Model
{
    protected ?string $table = 'products';

    protected array $fillable = [
        'name',
        'description',
        'price',
        'image',
        'images',
        'category_id',
        'status',
    ];

    protected array $casts = [
        'id' => 'integer',
        'price' => 'decimal:2',
        'category_id' => 'integer',
        'status' => 'integer',
        'images' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_OFF = 0;
    public const STATUS_ON = 1;

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
}
