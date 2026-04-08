<?php

use Swoole\Coroutine;
use Swoole\Coroutine\Http2\Client;

Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

Coroutine\run(function () {
    $client = new Client('user-service', 9502, false);
    $client->set(['timeout' => 10]);
    
    if (!$client->connect()) {
        echo "Connect failed: " . $client->errMsg . "\n";
        return;
    }
    
    $payload = json_encode(['username' => 'grpctest', 'password' => 'test123', 'email' => 'grpc@test.com', 'phone' => '123']);
    $message = pack('CN', 0, strlen($payload)) . $payload;
    
    $request = new \Swoole\Http2\Request();
    $request->method = 'POST';
    $request->path = '/user.UserService/Register';
    $request->headers = ['content-type' => 'application/grpc+json', 'te' => 'trailers'];
    $request->data = $message;
    
    $client->send($request);
    $response = $client->recv(10);
    $client->close();
    
    if (!$response) {
        echo "No response\n";
        return;
    }
    
    echo "Status: " . $response->statusCode . "\n";
    echo "Headers: " . json_encode($response->headers) . "\n";
    echo "Body length: " . strlen($response->data) . "\n";
    
    if (strlen($response->data) >= 5) {
        $header = unpack('Ccompressed/Nlength', substr($response->data, 0, 5));
        echo "Compressed: " . $header['compressed'] . "\n";
        echo "Length: " . $header['length'] . "\n";
        $jsonData = substr($response->data, 5, $header['length']);
        echo "JSON: " . $jsonData . "\n";
    } else {
        echo "Raw body: " . $response->data . "\n";
    }
});
