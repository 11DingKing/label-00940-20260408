<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

class OperationLogService
{
    #[Inject]
    protected LoggerInterface $logger;

    public function log(
        ?int $userId,
        string $service,
        string $action,
        string $method,
        string $path,
        array $request = [],
        array $response = [],
        string $ip = '',
        int $duration = 0
    ): void {
        try {
            Db::table('operation_logs')->insert([
                'user_id' => $userId,
                'service' => $service,
                'action' => $action,
                'method' => $method,
                'path' => $path,
                'request' => json_encode($request, JSON_UNESCAPED_UNICODE),
                'response' => json_encode($response, JSON_UNESCAPED_UNICODE),
                'ip' => $ip,
                'duration' => $duration,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to save operation log', [
                'error' => $e->getMessage(),
                'service' => $service,
                'action' => $action,
            ]);
        }
    }
}
