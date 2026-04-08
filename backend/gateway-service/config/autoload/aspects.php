<?php

declare(strict_types=1);

/**
 * AOP 切面配置
 * 
 * LogAspect: 记录所有 Controller 请求的入参、出参和耗时
 * 用于问题定位和性能分析
 */
return [
    App\Aspect\LogAspect::class,
];
