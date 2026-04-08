<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'enable' => [
        'discovery' => true,
        'register' => true,
    ],
    'consumers' => [
        // 消费 InventoryService
        [
            'name' => 'inventory.InventoryService',
            'protocol' => 'grpc',
            'load_balancer' => 'random',
            'registry' => [
                'protocol' => 'nacos',
                'address' => sprintf('http://%s:%s', env('NACOS_HOST', 'nacos'), env('NACOS_PORT', 8848)),
            ],
        ],
    ],
    'providers' => [
        [
            'id' => 'ProductService',
            'name' => 'product.ProductService',
            'protocol' => 'grpc',
            'server' => 'grpc',
            'publishTo' => 'nacos',
        ],
    ],
    'drivers' => [
        'nacos' => [
            'host' => env('NACOS_HOST', 'nacos'),
            'port' => (int) env('NACOS_PORT', 8848),
            'username' => env('NACOS_USERNAME', ''),
            'password' => env('NACOS_PASSWORD', ''),
            'group_name' => env('NACOS_GROUP', 'DEFAULT_GROUP'),
            'namespace_id' => env('NACOS_NAMESPACE', ''),
            'heartbeat' => 5,
            'ephemeral' => true,
        ],
    ],
];
