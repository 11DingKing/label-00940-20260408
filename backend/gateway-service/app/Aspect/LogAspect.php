<?php

declare(strict_types=1);

namespace App\Aspect;

use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

#[Aspect]
class LogAspect extends AbstractAspect
{
    public array $classes = [
        'App\Controller\*Controller::*',
    ];

    public function __construct(
        protected ContainerInterface $container,
        protected LoggerInterface $logger
    ) {
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $startTime = microtime(true);
        $request = $this->container->get(RequestInterface::class);
        
        $className = $proceedingJoinPoint->className;
        $methodName = $proceedingJoinPoint->methodName;
        $requestId = uniqid('req_', true);
        
        // 记录请求开始
        $this->logger->info('Request started', [
            'request_id' => $requestId,
            'class' => $className,
            'method' => $methodName,
            'uri' => $request->getUri()->getPath(),
            'http_method' => $request->getMethod(),
            'params' => $request->all(),
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->getHeaderLine('User-Agent'),
        ]);

        try {
            $result = $proceedingJoinPoint->process();
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            // 记录请求完成
            $this->logger->info('Request completed', [
                'request_id' => $requestId,
                'class' => $className,
                'method' => $methodName,
                'duration_ms' => $duration,
                'response_code' => is_array($result) ? ($result['code'] ?? 0) : 0,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            // 记录请求异常
            $this->logger->error('Request failed', [
                'request_id' => $requestId,
                'class' => $className,
                'method' => $methodName,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function getClientIp(RequestInterface $request): string
    {
        $headers = ['X-Forwarded-For', 'X-Real-IP', 'CF-Connecting-IP'];
        foreach ($headers as $header) {
            $ip = $request->getHeaderLine($header);
            if (!empty($ip)) {
                return explode(',', $ip)[0];
            }
        }
        return $request->getServerParams()['remote_addr'] ?? '127.0.0.1';
    }
}
