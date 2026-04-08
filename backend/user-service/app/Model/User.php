<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class User extends Model
{
    protected ?string $table = 'users';

    protected array $fillable = [
        'username',
        'password',
        'email',
        'phone',
        'avatar',
        'status',
    ];

    protected array $hidden = [
        'password',
    ];

    protected array $casts = [
        'id' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_DISABLED = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_ENABLED = 1;
}
