<?php

declare(strict_types=1);

namespace App\Listener;

use GuzzleHttp\Client;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function Hyperf\Support\env;
use Hyperf\Coroutine\Coroutine;

#[Listener]
class NacosRegisterListener implements ListenerInterface
{
    protected ContainerInterface $container;
    protected LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
    }

    public function listen(): array
    {
        return [
            MainWorkerStart::class,
        ];
    }

    public function process(object $event): void
    {
        if (!env('NACOS_ENABLE', false)) {
            return;
        }

        $this->registerToNacos();
        $this->startHeartbeat();
    }

    protected function registerToNacos(): void
    {
        try {
            $nacosHost = env('NACOS_HOST', 'nacos');
            $nacosPort = env('NACOS_PORT', 8848);
            $serviceName = 'user.UserService';
            $serviceIp = $this->getServiceIp();
            $servicePort = (int) env('GRPC_PORT', 9502);
            $groupName = env('NACOS_GROUP', 'DEFAULT_GROUP');

            $client = new Client(['timeout' => 5]);
            $response = $client->post(
                sprintf('http://%s:%d/nacos/v1/ns/instance', $nacosHost, $nacosPort),
                [
                    'form_params' => [
                        'serviceName' => $serviceName,
                        'ip' => $serviceIp,
                        'port' => $servicePort,
                        'healthy' => 'true',
                        'enabled' => 'true',
                        'weight' => '1.0',
                        'ephemeral' => 'true',
                        'groupName' => $groupName,
                        'metadata' => json_encode(['protocol' => 'grpc']),
                    ],
                ]
            );

            $this->logger->info('Service registered to Nacos', [
                'service' => $serviceName,
                'ip' => $serviceIp,
                'port' => $servicePort,
                'response' => $response->getBody()->getContents(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to register service to Nacos', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function startHeartbeat(): void
    {
        // 启动心跳协程
        Coroutine::create(function () {
            $nacosHost = env('NACOS_HOST', 'nacos');
            $nacosPort = env('NACOS_PORT', 8848);
            $serviceName = 'user.UserService';
            $serviceIp = $this->getServiceIp();
            $servicePort = (int) env('GRPC_PORT', 9502);
            $groupName = env('NACOS_GROUP', 'DEFAULT_GROUP');

            $client = new Client(['timeout' => 5]);

            while (true) {
                try {
                    $beat = json_encode([
                        'serviceName' => $serviceName,
                        'ip' => $serviceIp,
                        'port' => $servicePort,
                        'weight' => 1.0,
                        'metadata' => ['protocol' => 'grpc'],
                    ]);

                    $client->put(
                        sprintf('http://%s:%d/nacos/v1/ns/instance/beat', $nacosHost, $nacosPort),
                        [
                            'query' => [
                                'serviceName' => $serviceName,
                                'ip' => $serviceIp,
                                'port' => $servicePort,
                                'groupName' => $groupName,
                                'beat' => $beat,
                            ],
                        ]
                    );
                } catch (\Throwable $e) {
                    $this->logger->warning('Nacos heartbeat failed', [
                        'error' => $e->getMessage(),
                    ]);
                }

                // 每 5 秒发送一次心跳
                \Swoole\Coroutine::sleep(5);
            }
        });
    }

    protected function getServiceIp(): string
    {
        $serviceIp = env('SERVICE_IP', gethostname());
        
        // 如果是主机名，尝试解析为 IP
        if (!filter_var($serviceIp, FILTER_VALIDATE_IP)) {
            $resolved = gethostbyname($serviceIp);
            if ($resolved !== $serviceIp) {
                $serviceIp = $resolved;
            }
        }

        return $serviceIp;
    }
}
