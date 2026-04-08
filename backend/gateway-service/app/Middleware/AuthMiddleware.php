<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\BusinessException;
use App\Service\UserService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Hyperf\Support\env;

class AuthMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected UserService $userService;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getHeaderLine('Authorization');
        
        if (empty($token)) {
            throw new BusinessException(401, '请先登录');
        }

        // 移除 Bearer 前缀
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        try {
            $secret = env('JWT_SECRET', 'your-secret-key');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            
            $userId = $decoded->user_id ?? 0;
            $username = $decoded->username ?? '';

            if ($userId <= 0) {
                throw new BusinessException(401, 'Token无效');
            }

            // 将用户信息注入到请求中
            $request = $request->withAttribute('user_id', $userId);
            $request = $request->withAttribute('username', $username);

            return $handler->handle($request);
        } catch (\Exception $e) {
            throw new BusinessException(401, 'Token无效或已过期');
        }
    }
}
