<?php
/**
 * gRPC Protobuf 类生成与验证脚本
 * 
 * 本项目的 gRPC Protobuf Message 类通过 protoc 工具生成，并提交到 Git 仓库。
 * 这种方式确保：
 * 1. 项目可以在任何环境中直接运行，无需安装 protoc 工具
 * 2. .proto 定义与 PHP 类实现保持一致
 * 3. 构建速度快，Docker 构建时无需生成代码
 * 
 * 生成的类位于各服务的 grpc/ 目录下：
 * - backend/user-service/grpc/
 * - backend/product-service/grpc/
 * - backend/order-service/grpc/
 * - backend/inventory-service/grpc/
 * - backend/gateway-service/grpc/
 * 
 * 如需重新生成（例如修改了 .proto 文件），请按以下步骤操作：
 * 
 * 1. 安装 protoc 和 grpc_php_plugin:
 *    - macOS: brew install protobuf grpc
 *    - Ubuntu: apt-get install protobuf-compiler php-grpc
 * 
 * 2. 运行生成脚本:
 *    cd backend/proto
 *    bash generate.sh
 * 
 * 3. 将生成的文件复制到各服务目录
 * 
 * Proto 文件定义位于 backend/proto/ 目录：
 * - user.proto     - 用户服务消息定义
 * - product.proto  - 商品服务消息定义
 * - order.proto    - 订单服务消息定义
 * - inventory.proto - 库存服务消息定义
 */

echo "==============================================\n";
echo "gRPC Protobuf Classes - 验证脚本\n";
echo "==============================================\n\n";

echo "本项目的 Protobuf Message 类通过 protoc 生成并提交到 Git 仓库。\n";
echo "无需运行此脚本即可直接使用项目。\n\n";

echo "生成的类位置:\n";
$services = [
    'user-service' => 'Grpc\User\*',
    'product-service' => 'Grpc\Product\*, Grpc\Inventory\*',
    'order-service' => 'Grpc\Order\*, Grpc\Product\*, Grpc\Inventory\*',
    'inventory-service' => 'Grpc\Inventory\*',
    'gateway-service' => 'Grpc\User\*, Grpc\Product\*, Grpc\Order\*, Grpc\Inventory\*',
];

foreach ($services as $service => $classes) {
    echo "  - backend/{$service}/grpc/ ({$classes})\n";
}

echo "\n如需重新生成，请执行:\n";
echo "  cd backend/proto && bash generate.sh\n\n";

echo "Proto 文件位置: backend/proto/*.proto\n";
echo "  - user.proto\n";
echo "  - product.proto\n";
echo "  - order.proto\n";
echo "  - inventory.proto\n\n";

// 验证现有类是否存在
echo "==============================================\n";
echo "验证已生成的类\n";
echo "==============================================\n\n";

$checkPaths = [
    'user-service/grpc/Grpc/User/RegisterRequest.php',
    'user-service/grpc/Grpc/User/LoginRequest.php',
    'user-service/grpc/Grpc/User/UserResponse.php',
    'product-service/grpc/Grpc/Product/ProductListRequest.php',
    'product-service/grpc/Grpc/Product/ProductResponse.php',
    'product-service/grpc/Grpc/Inventory/BatchGetStockRequest.php',
    'order-service/grpc/Grpc/Order/CreateOrderRequest.php',
    'order-service/grpc/Grpc/Order/OrderResponse.php',
    'order-service/grpc/Grpc/Inventory/LockStockRequest.php',
    'inventory-service/grpc/Grpc/Inventory/GetStockRequest.php',
    'inventory-service/grpc/Grpc/Inventory/StockResponse.php',
    'inventory-service/grpc/Grpc/Inventory/InitStockRequest.php',
    'gateway-service/grpc/GPBMetadata/User.php',
    'gateway-service/grpc/GPBMetadata/Product.php',
    'gateway-service/grpc/GPBMetadata/Order.php',
    'gateway-service/grpc/GPBMetadata/Inventory.php',
];

$allExist = true;
foreach ($checkPaths as $path) {
    $fullPath = __DIR__ . '/' . $path;
    $exists = file_exists($fullPath);
    $status = $exists ? '✓' : '✗';
    echo "  [{$status}] {$path}\n";
    if (!$exists) {
        $allExist = false;
    }
}

echo "\n";
if ($allExist) {
    echo "所有核心 Protobuf 类已存在，项目可以正常运行。\n";
} else {
    echo "警告: 部分类文件缺失，请检查 grpc/ 目录。\n";
}

echo "\n==============================================\n";
echo "完成\n";
echo "==============================================\n";
