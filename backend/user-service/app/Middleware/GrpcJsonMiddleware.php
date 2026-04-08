<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;

class GrpcJsonMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('content-type');
        
        // 处理 gRPC JSON 请求
        if (str_contains($contentType, 'application/grpc+json') || str_contains($contentType, 'application/grpc')) {
            $body = $request->getBody()->getContents();
            
            // 解析 gRPC 消息格式
            if (strlen($body) >= 5) {
                $header = unpack('Ccompressed/Nlength', substr($body, 0, 5));
                $jsonData = substr($body, 5, $header['length']);
                $data = json_decode($jsonData, true) ?: [];
                
                // 将解析后的数据放入请求属性
                $request = $request->withParsedBody($data);
            }
        }
        
        $response = $handler->handle($request);
        
        // 如果是 gRPC 请求，格式化响应
        if (str_contains($contentType, 'application/grpc')) {
            $responseBody = $response->getBody()->getContents();
            
            // 封装为 gRPC 消息格式
            $grpcMessage = pack('CN', 0, strlen($responseBody)) . $responseBody;
            
            return $response
                ->withHeader('content-type', 'application/grpc+json')
                ->withHeader('grpc-status', '0')
                ->withBody(new SwooleStream($grpcMessage));
        }
        
        return $response;
    }
}
