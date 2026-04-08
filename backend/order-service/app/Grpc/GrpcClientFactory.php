<?php

declare(strict_types=1);

namespace App\Grpc;

use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function Hyperf\Support\env;

/**
 * gRPC 客户端工厂
 * 
 * 提供连接池管理和 Nacos 服务发现集成
 * 使用直接 HTTP API 调用 Nacos，避免认证问题
 */
class GrpcClientFactory
{
    /**
     * 客户端实例缓存 (连接复用)
     */
    protected static array $clients = [];

    /**
     * 服务地址缓存 (减少 Nacos 查询)
     */
    protected static array $addressCache = [];

    /**
     * HTTP 客户端 (用于 Nacos API)
     */
    protected static ?Client $httpClient = null;

    /**
     * 地址缓存过期时间 (秒)
     */
    protected const ADDRESS_CACHE_TTL = 30;

    protected ContainerInterface $container;
    protected LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * 获取 HTTP 客户端 (单例)
     */
    protected function getHttpClient(): Client
    {
        if (self::$httpClient === null) {
            self::$httpClient = new Client([
                'timeout' => 5,
                'connect_timeout' => 3,
            ]);
        }
        return self::$httpClient;
    }

    /**
     * 获取 ProductService 客户端
     */
    public function getProductServiceClient(): ProductServiceClient
    {
        return $this->getClient(
            ProductServiceClient::class,
            'product.ProductService',
            env('PRODUCT_SERVICE_HOST', 'product-service'),
            (int) env('PRODUCT_SERVICE_PORT', 9503)
        );
    }

    /**
     * 获取 InventoryService 客户端
     */
    public function getInventoryServiceClient(): InventoryServiceClient
    {
        return $this->getClient(
            InventoryServiceClient::class,
            'inventory.InventoryService',
            env('INVENTORY_SERVICE_HOST', 'inventory-service'),
            (int) env('INVENTORY_SERVICE_PORT', 9505)
        );
    }

    /**
     * 获取或创建 gRPC 客户端 (连接池复用 + Nacos 服务发现)
     */
    protected function getClient(string $clientClass, string $serviceName, string $defaultHost, int $defaultPort): mixed
    {
        $address = $this->getServiceAddress($serviceName, $defaultHost, $defaultPort);
        $cacheKey = $clientClass . ':' . $address;

        // 检查缓存的客户端是否可用
        if (isset(self::$clients[$cacheKey])) {
            return self::$clients[$cacheKey];
        }

        // 创建新客户端并缓存
        $client = new $clientClass($address, [
            'credentials' => null,
            'grpc.keepalive_time_ms' => 30000,
            'grpc.keepalive_timeout_ms' => 10000,
            'grpc.keepalive_permit_without_calls' => 1,
        ]);

        self::$clients[$cacheKey] = $client;

        $this->logger->debug('gRPC client created via Nacos discovery', [
            'client' => $clientClass,
            'service' => $serviceName,
            'address' => $address,
        ]);

        return $client;
    }

    /**
     * 从 Nacos 获取服务地址 (带缓存)
     */
    protected function getServiceAddress(string $serviceName, string $defaultHost, int $defaultPort): string
    {
        $cacheKey = $serviceName;
        $now = time();

        // 检查地址缓存
        if (isset(self::$addressCache[$cacheKey])) {
            $cached = self::$addressCache[$cacheKey];
            if ($cached['expire'] > $now) {
                return $cached['address'];
            }
        }

        // 从 Nacos 获取服务地址
        $address = $this->discoverService($serviceName, $defaultHost, $defaultPort);

        // 缓存地址
        self::$addressCache[$cacheKey] = [
            'address' => $address,
            'expire' => $now + self::ADDRESS_CACHE_TTL,
        ];

        return $address;
    }

    /**
     * Nacos 服务发现 (使用直接 HTTP API 调用)
     */
    protected function discoverService(string $serviceName, string $defaultHost, int $defaultPort): string
    {
        try {
            if (!env('NACOS_ENABLE', false)) {
                return sprintf('%s:%d', $defaultHost, $defaultPort);
            }

            $nacosHost = env('NACOS_HOST', 'nacos');
            $nacosPort = env('NACOS_PORT', 8848);
            $groupName = env('NACOS_GROUP', 'DEFAULT_GROUP');
            $namespaceId = env('NACOS_NAMESPACE', '');

            // 直接调用 Nacos HTTP API (无需认证)
            $url = sprintf(
                'http://%s:%d/nacos/v1/ns/instance/list?serviceName=%s&groupName=%s&healthyOnly=true',
                $nacosHost,
                $nacosPort,
                urlencode($serviceName),
                urlencode($groupName)
            );

            if (!empty($namespaceId)) {
                $url .= '&namespaceId=' . urlencode($namespaceId);
            }

            $response = $this->getHttpClient()->get($url);
            $data = json_decode($response->getBody()->getContents(), true);

            if (!empty($data['hosts'])) {
                // 负载均衡: 随机选择一个健康实例
                $healthyHosts = array_filter($data['hosts'], fn($h) => ($h['healthy'] ?? true) && ($h['enabled'] ?? true));
                if (!empty($healthyHosts)) {
                    $host = $healthyHosts[array_rand($healthyHosts)];
                    $address = sprintf('%s:%d', $host['ip'], $host['port']);
                    
                    $this->logger->info('Service discovered from Nacos', [
                        'service' => $serviceName,
                        'address' => $address,
                        'total_instances' => count($data['hosts']),
                        'healthy_instances' => count($healthyHosts),
                    ]);
                    
                    return $address;
                }
            }

            $this->logger->warning('No healthy instances found in Nacos, using fallback', [
                'service' => $serviceName,
                'fallback' => sprintf('%s:%d', $defaultHost, $defaultPort),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Nacos service discovery failed, using fallback', [
                'service' => $serviceName,
                'fallback' => sprintf('%s:%d', $defaultHost, $defaultPort),
                'error' => $e->getMessage(),
            ]);
        }

        return sprintf('%s:%d', $defaultHost, $defaultPort);
    }

    /**
     * 清除客户端缓存 (用于服务地址变更时)
     */
    public static function clearCache(): void
    {
        self::$clients = [];
        self::$addressCache = [];
    }

    /**
     * 获取连接池状态 (用于监控)
     */
    public static function getPoolStats(): array
    {
        return [
            'active_clients' => count(self::$clients),
            'cached_addresses' => count(self::$addressCache),
            'clients' => array_keys(self::$clients),
        ];
    }
}
