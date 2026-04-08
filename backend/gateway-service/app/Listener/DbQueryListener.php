<?php

declare(strict_types=1);

namespace App\Listener;

use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

#[Listener]
class DbQueryListener implements ListenerInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    public function process(object $event): void
    {
        if ($event instanceof QueryExecuted) {
            $sql = $event->sql;
            if (!empty($event->bindings)) {
                $sql = sprintf($sql, ...array_map(fn($v) => is_string($v) ? "'{$v}'" : $v, $event->bindings));
            }

            $logger = $this->container->get(LoggerInterface::class);
            
            // 慢查询阈值 100ms
            $level = $event->time > 100 ? 'warning' : 'debug';
            
            $logger->$level('Database query executed', [
                'sql' => $sql,
                'time_ms' => round($event->time, 2),
                'connection' => $event->connectionName,
            ]);
        }
    }
}
