<?php

declare(strict_types=1);

use function Hyperf\Support\env;

// 自动获取容器 IP 地址
$serviceIp = env('SERVICE_IP', gethostname());
// 如果是主机名，尝试解析为 IP
if (!filter_var($serviceIp, FILTER_VALIDATE_IP)) {
    $resolved = gethostbyname($serviceIp);
    if ($resolved !== $serviceIp) {
        $serviceIp = $resolved;
    }
}

return [
    'enable' => (bool) env('NACOS_ENABLE', true),
    'host' => env('NACOS_HOST', 'nacos'),
    'port' => (int) env('NACOS_PORT', 8848),
    'username' => env('NACOS_USERNAME', ''),
    'password' => env('NACOS_PASSWORD', ''),
    'guzzle' => [
        'config' => [
            'timeout' => 10,
            'connect_timeout' => 5,
        ],
    ],
    'service' => [
        'enable' => true,
        'service_name' => env('APP_NAME', 'gateway-service'),
        'group_name' => 'DEFAULT_GROUP',
        'namespace_id' => env('NACOS_NAMESPACE', ''),
        'protect_threshold' => 0.5,
        'metadata' => [
            'protocol' => 'http',
        ],
        'instance' => [
            'ip' => $serviceIp,
            'port' => (int) env('HTTP_PORT', 9501),
            'weight' => 1.0,
            'enabled' => true,
            'healthy' => true,
            'ephemeral' => true,
        ],
        'heartbeat' => 5,
    ],
];
