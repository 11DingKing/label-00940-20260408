# E-commerce Microservices System

基于 PHP Hyperf 框架构建的电商微服务系统，使用 **标准 gRPC + Protobuf** 进行服务间通信，**Nacos** 进行服务注册与发现。

## 技术栈

| 组件 | 技术 | 版本 |
|------|------|------|
| 框架 | PHP Hyperf | 3.1 |
| 运行时 | Swoole | 5.x |
| 通信协议 | gRPC (HTTP/2 + Protobuf) | - |
| 服务发现 | Nacos | 2.2.3 |
| 数据库 | MySQL | 8.0 |
| 缓存 | Redis | 7.x |
| 容器化 | Docker & Docker Compose | - |

## 系统架构

```
                         ┌─────────────────────┐
                         │    Client (HTTP)    │
                         └──────────┬──────────┘
                                    │
                         ┌──────────▼──────────┐
                         │    API Gateway      │
                         │   (HTTP:8081/9501)  │
                         │   gRPC Client       │
                         └──────────┬──────────┘
                                    │ gRPC (HTTP/2 + Protobuf)
          ┌─────────────────────────┼─────────────────────────┐
          │                         │                         │
          ▼                         ▼                         ▼
┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
│  User Service   │      │ Product Service │      │  Order Service  │
│  (gRPC:9502)    │      │  (gRPC:9503)    │      │  (gRPC:9504)    │
│                 │      │                 │      │                 │
│ user.UserService│      │product.Product- │      │order.OrderSvc   │
└────────┬────────┘      │    Service      │      └────────┬────────┘
         │               └────────┬────────┘               │
         │                        │                        │
         │               ┌────────▼────────┐               │
         │               │Inventory Service│               │
         │               │  (gRPC:9505)    │               │
         │               │                 │               │
         │               │inventory.Inven- │               │
         │               │   toryService   │               │
         │               └────────┬────────┘               │
         │                        │                        │
         └────────────────────────┼────────────────────────┘
                                  │
         ┌────────────────────────┼────────────────────────┐
         │                        │                        │
         ▼                        ▼                        ▼
┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
│     MySQL       │      │     Redis       │      │     Nacos       │
│    (3306)       │      │    (6379)       │      │    (8848)       │
└─────────────────┘      └─────────────────┘      └─────────────────┘
```

## 服务说明

| 服务 | 端口 | 协议 | Nacos 服务名 | 职责 |
|------|------|------|--------------|------|
| Gateway | 8081 (外部) / 9501 (内部) | HTTP | gateway-service | API 网关、路由转发、JWT 认证 |
| User | 9502 | gRPC | user.UserService | 用户注册、登录、信息管理 |
| Product | 9503 | gRPC | product.ProductService | 商品 CRUD、分类管理 |
| Order | 9504 | gRPC | order.OrderService | 订单创建、支付、状态管理 |
| Inventory | 9505 | gRPC | inventory.InventoryService | 库存查询、锁定、扣减 |

## 快速开始

### 环境要求
- Docker >= 20.x
- Docker Compose >= 2.x

### 1. 一键启动 (真正的零配置)

```bash
# 克隆项目后，直接启动
docker-compose up -d --build

# 等待所有服务启动完成 (约 60-90 秒)
# Nacos 需要较长时间初始化

# 查看服务状态
docker-compose ps
```

**启动过程说明：**
1. MySQL 容器启动后自动执行 `backend/schema.sql` 初始化数据库
2. 创建所有表结构 (users, products, categories, orders, order_items, inventory, operation_logs)
3. 插入测试数据 (2 个用户, 6 个分类, 4 个商品, 4 条库存记录)
4. 各微服务启动后自动注册到 Nacos

### 2. 验证服务

```bash
# Gateway 健康检查
curl http://localhost:8081/health
# 返回: {"status":"ok","service":"gateway"}

# 检查 Nacos 服务注册 (浏览器访问)
# http://localhost:8848/nacos  账号: nacos  密码: nacos
```

### 3. 运行集成测试

```bash
# 运行 22 个 API 测试用例
bash backend/tests/api_test.sh

# 预期输出: All tests passed!
```

### 4. 可选：自定义配置

```bash
# 复制环境变量模板
cp .env.example .env

# 编辑 .env 文件自定义配置
# - MYSQL_ROOT_PASSWORD: MySQL 密码
# - JWT_SECRET: JWT 密钥
# - GATEWAY_PORT: 网关端口
```

## 微服务架构设计

本项目严格遵循微服务架构原则，实现了真正的服务自治：

### 服务边界与职责

| 服务 | 数据库表 | 职责 | gRPC 依赖 |
|------|----------|------|-----------|
| User Service | users | 用户注册、登录、信息管理 | 无 |
| Product Service | products, categories | 商品 CRUD、分类管理 | InventoryService |
| Order Service | orders, order_items | 订单创建、状态管理 | ProductService, InventoryService |
| Inventory Service | inventory | 库存查询、锁定、扣减 | 无 |

### 数据库架构说明

本项目采用**逻辑隔离**的数据库架构：
- 所有服务共享同一个 MySQL 实例（开发/演示环境）
- 每个服务仅访问自己负责的表，通过 gRPC 调用其他服务获取数据
- 生产环境可轻松切换为物理隔离（每个服务独立数据库）

```yaml
# 生产环境数据库隔离示例
user-service:
  DB_HOST: mysql-user
  DB_DATABASE: user_db

product-service:
  DB_HOST: mysql-product
  DB_DATABASE: product_db
```

### 服务间通信

```
┌─────────────────┐     ┌─────────────────┐
│  Order Service  │     │ Product Service │
└────────┬────────┘     └────────┬────────┘
         │ gRPC                  │ gRPC
    ┌────┴────┐                  │
    │         │                  │
    ▼         ▼                  ▼
┌────────┐ ┌──────────┐    ┌──────────┐
│Product │ │Inventory │◄───│Inventory │
│Service │ │ Service  │    │ Service  │
└────────┘ └──────────┘    └──────────┘
```

Order Service 通过 gRPC 调用：
- `ProductService.BatchGetProducts` - 获取商品信息
- `InventoryService.BatchGetStock` - 检查库存
- `InventoryService.LockStock` - 锁定库存
- `InventoryService.UnlockStock` - 释放库存（取消订单）
- `InventoryService.DeductStock` - 扣减库存（支付订单）

Product Service 通过 gRPC 调用：
- `InventoryService.BatchGetStock` - 获取商品库存信息
- `InventoryService.InitStock` - 创建商品时初始化库存

### 数据一致性

订单创建流程：
1. 调用 ProductService 获取商品信息
2. 调用 InventoryService 检查库存
3. 调用 InventoryService 锁定库存
4. 创建订单记录
5. 如果失败，调用 InventoryService 释放库存

## 标准 gRPC 实现

本项目采用 **Hyperf 官方推荐的标准 gRPC 实现方式**：

### 实现流程

```
1. 定义 .proto 文件
       ↓
2. 生成 PHP Protobuf Message 类
       ↓
3. 服务端: 使用 #[GrpcService] 注解 + 实现接口
       ↓
4. 客户端: 继承 BaseClient + 调用 _simpleRequest
```

### Proto 定义

```protobuf
// backend/proto/user.proto
syntax = "proto3";
package user;

option php_namespace = "Grpc\\User";
option php_metadata_namespace = "GPBMetadata";

service UserService {
    rpc Register(RegisterRequest) returns (UserResponse);
    rpc Login(LoginRequest) returns (LoginResponse);
    rpc GetUserInfo(GetUserRequest) returns (UserResponse);
}

message RegisterRequest {
    string username = 1;
    string password = 2;
    string email = 3;
    string phone = 4;
}

message UserResponse {
    int32 code = 1;
    string message = 2;
    UserInfo user = 3;
}
```

### Protobuf Message 类

```php
// backend/user-service/grpc/Grpc/User/RegisterRequest.php
namespace Grpc\User;

use Google\Protobuf\Internal\Message;

class RegisterRequest extends Message
{
    protected $username = '';
    protected $password = '';
    protected $email = '';
    protected $phone = '';

    public function __construct($data = NULL) {
        \GPBMetadata\User::initOnce();
        parent::__construct($data);
    }

    public function getUsername(): string { return $this->username; }
    public function setUsername($var): self { $this->username = $var; return $this; }
    // ... 其他 getter/setter
}
```

### gRPC 服务端实现

```php
// backend/user-service/app/Grpc/UserService.php
namespace App\Grpc;

use Grpc\User\RegisterRequest;
use Grpc\User\UserResponse;
use Hyperf\GrpcServer\Annotation\GrpcService;

#[GrpcService(name: "user.UserService", server: "grpc")]
class UserService implements UserServiceInterface
{
    public function register(RegisterRequest $request): UserResponse
    {
        $response = new UserResponse();
        
        $username = $request->getUsername();
        $password = $request->getPassword();
        
        // 业务逻辑...
        
        $response->setCode(0);
        $response->setMessage('success');
        $response->setUser($this->buildUserInfo($user));
        
        return $response;
    }
}
```

### gRPC 客户端实现

```php
// backend/gateway-service/app/Grpc/UserServiceClient.php
namespace App\Grpc;

use Grpc\User\RegisterRequest;
use Grpc\User\UserResponse;
use Hyperf\GrpcClient\BaseClient;

class UserServiceClient extends BaseClient
{
    public function register(RegisterRequest $request): array
    {
        return $this->_simpleRequest(
            '/user.UserService/Register',
            $request,
            [UserResponse::class, 'decode']
        );
    }
}
```

### Gateway 服务调用

```php
// backend/gateway-service/app/Service/UserService.php
namespace App\Service;

use App\Grpc\UserServiceClient;
use Grpc\User\RegisterRequest;

class UserService
{
    public function register(string $username, string $password, ...): array
    {
        $request = new RegisterRequest();
        $request->setUsername($username);
        $request->setPassword($password);
        // ...

        $client = new UserServiceClient($this->getServiceAddress(), ['credentials' => null]);
        [$response, $status] = $client->register($request);

        if ($status !== 0) {
            return ['code' => -1, 'message' => 'gRPC 调用失败'];
        }

        return [
            'code' => $response->getCode(),
            'message' => $response->getMessage(),
            'user' => $this->userInfoToArray($response->getUser()),
        ];
    }
}
```

### 服务器配置

```php
// backend/user-service/config/autoload/server.php
return [
    'servers' => [
        [
            'name' => 'grpc',
            'type' => Server::SERVER_HTTP,
            'host' => '0.0.0.0',
            'port' => (int) env('GRPC_PORT', 9502),
            'callbacks' => [
                // 使用 Hyperf gRPC Server 处理请求
                Event::ON_REQUEST => [\Hyperf\GrpcServer\Server::class, 'onRequest'],
            ],
            'options' => [
                'open_http2_protocol' => true,
            ],
        ],
    ],
];
```

## gRPC 代码生成策略

本项目采用 **预生成并提交到 Git** 的策略管理 Protobuf Message 类：

### 为什么预生成？

1. **零环境依赖**: 无需安装 `protoc` 或 `grpc_php_plugin`，克隆即可运行
2. **构建速度快**: Docker 构建时无需生成代码，大幅缩短构建时间
3. **版本一致性**: 所有环境使用相同的生成代码，避免版本差异
4. **CI/CD 友好**: 无需在 CI 环境配置 protoc 工具链

### 生成的文件位置

```
backend/
├── user-service/grpc/
│   ├── GPBMetadata/User.php
│   └── Grpc/User/*.php
├── product-service/grpc/
│   ├── GPBMetadata/Product.php
│   └── Grpc/Product/*.php
├── order-service/grpc/
│   ├── GPBMetadata/Order.php
│   └── Grpc/Order/*.php
├── inventory-service/grpc/
│   ├── GPBMetadata/Inventory.php
│   └── Grpc/Inventory/*.php
└── gateway-service/grpc/
    ├── GPBMetadata/*.php
    └── Grpc/{User,Product,Order,Inventory}/*.php
```

### 如需重新生成

如果修改了 `.proto` 文件，可以通过以下方式重新生成：

```bash
# 方式一: 使用 protoc (需要安装 protoc 和 grpc_php_plugin)
cd backend/proto
bash generate.sh

# 方式二: 运行验证脚本查看当前状态
php backend/generate_grpc_classes.php
```

## 项目结构

```
├── backend/
│   ├── gateway-service/          # API 网关
│   │   ├── app/
│   │   │   ├── Controller/       # HTTP 控制器
│   │   │   ├── Grpc/             # gRPC 客户端
│   │   │   │   ├── UserServiceClient.php
│   │   │   │   ├── ProductServiceClient.php
│   │   │   │   ├── OrderServiceClient.php
│   │   │   │   └── InventoryServiceClient.php
│   │   │   ├── Middleware/       # 中间件 (Auth, CORS)
│   │   │   └── Service/          # 服务层 (调用 gRPC)
│   │   └── grpc/                 # Protobuf Message 类
│   │       ├── GPBMetadata/
│   │       └── Grpc/
│   │           ├── User/
│   │           ├── Product/
│   │           ├── Order/
│   │           └── Inventory/
│   │
│   ├── user-service/             # 用户微服务
│   │   ├── app/
│   │   │   ├── Grpc/             # gRPC 服务实现
│   │   │   │   ├── UserService.php
│   │   │   │   └── UserServiceInterface.php
│   │   │   └── Model/            # 数据模型
│   │   └── grpc/                 # Protobuf Message 类
│   │       ├── GPBMetadata/User.php
│   │       └── Grpc/User/
│   │           ├── RegisterRequest.php
│   │           ├── LoginRequest.php
│   │           ├── UserResponse.php
│   │           └── ...
│   │
│   ├── product-service/          # 商品微服务
│   ├── order-service/            # 订单微服务
│   ├── inventory-service/        # 库存微服务
│   │
│   ├── proto/                    # Proto 定义文件
│   │   ├── user.proto
│   │   ├── product.proto
│   │   ├── order.proto
│   │   └── inventory.proto
│   │
│   ├── schema.sql                # 数据库初始化
│   └── tests/
│       └── api_test.sh           # 集成测试脚本
│
├── docker-compose.yml            # Docker 编排
└── README.md
```

## API 接口文档

### 用户模块

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/api/user/register` | 用户注册 | 否 |
| POST | `/api/user/login` | 用户登录 | 否 |
| GET | `/api/user/info` | 获取用户信息 | 是 |
| PUT | `/api/user/update` | 更新用户信息 | 是 |

### 商品模块

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/product/list` | 商品列表 | 否 |
| GET | `/api/product/detail/{id}` | 商品详情 | 否 |
| POST | `/api/product/create` | 创建商品 | 是 |
| PUT | `/api/product/update/{id}` | 更新商品 | 是 |
| DELETE | `/api/product/delete/{id}` | 删除商品 | 是 |
| GET | `/api/product/category/list` | 分类列表 | 否 |

### 订单模块

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/api/order/create` | 创建订单 | 是 |
| GET | `/api/order/list` | 订单列表 | 是 |
| GET | `/api/order/detail/{id}` | 订单详情 | 是 |
| PUT | `/api/order/cancel/{id}` | 取消订单 | 是 |
| PUT | `/api/order/pay/{id}` | 支付订单 | 是 |

### 库存模块

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/inventory/stock/{product_id}` | 查询库存 | 是 |
| PUT | `/api/inventory/update` | 更新库存 | 是 |

## Nacos 服务发现

本项目实现了完整的 Nacos 服务注册与发现功能：

### 服务注册

每个微服务启动时通过 `NacosRegisterListener` 自动注册到 Nacos：

```php
// backend/user-service/app/Listener/NacosRegisterListener.php
#[Listener]
class NacosRegisterListener implements ListenerInterface
{
    public function listen(): array
    {
        return [MainWorkerStart::class];
    }

    public function process(object $event): void
    {
        $this->registerToNacos();  // 注册服务
        $this->startHeartbeat();   // 启动心跳协程
    }
}
```

### 服务发现

Gateway 和其他服务通过 `GrpcClientFactory` 从 Nacos 动态发现服务地址：

```php
// backend/gateway-service/app/Grpc/GrpcClientFactory.php
protected function discoverService(string $serviceName, ...): string
{
    // 直接调用 Nacos HTTP API (无需认证)
    $url = sprintf(
        'http://%s:%d/nacos/v1/ns/instance/list?serviceName=%s&healthyOnly=true',
        $nacosHost, $nacosPort, $serviceName
    );
    
    $response = $this->getHttpClient()->get($url);
    $data = json_decode($response->getBody()->getContents(), true);
    
    // 负载均衡: 随机选择健康实例
    $healthyHosts = array_filter($data['hosts'], fn($h) => $h['healthy']);
    $host = $healthyHosts[array_rand($healthyHosts)];
    
    return sprintf('%s:%d', $host['ip'], $host['port']);
}
```

### 验证服务发现

```bash
# 查看 Nacos 注册的服务
curl -s "http://localhost:8848/nacos/v1/ns/service/list?pageNo=1&pageSize=10" | jq .
# 返回: {"count":4,"doms":["user.UserService","product.ProductService",...]}

# 查看服务实例
curl -s "http://localhost:8848/nacos/v1/ns/instance/list?serviceName=user.UserService" | jq '.hosts'

# 查看 Gateway 日志确认服务发现
docker logs ecommerce-gateway 2>&1 | grep "discovered"
# 输出: Service discovered from Nacos {"service":"user.UserService","address":"192.168.x.x:9502",...}
```

### 访问 Nacos 控制台

- 地址: http://localhost:8848/nacos
- 账号: nacos
- 密码: nacos

### 服务发现配置

服务注册配置 (`services.php`):

```php
// backend/user-service/config/autoload/services.php
return [
    'enable' => ['discovery' => true, 'register' => true],
    'providers' => [
        [
            'id' => 'UserService',
            'name' => 'user.UserService',
            'protocol' => 'grpc',
            'server' => 'grpc',
            'publishTo' => 'nacos',
        ],
    ],
    'drivers' => [
        'nacos' => [
            'host' => env('NACOS_HOST', 'nacos'),
            'port' => (int) env('NACOS_PORT', 8848),
            'username' => env('NACOS_USERNAME', ''),
            'password' => env('NACOS_PASSWORD', ''),
            'group_name' => 'DEFAULT_GROUP',
        ],
    ],
];
```

### 连接池与缓存

`GrpcClientFactory` 实现了连接池和地址缓存：

```php
class GrpcClientFactory
{
    protected static array $clients = [];        // 客户端连接池
    protected static array $addressCache = [];   // 地址缓存 (30秒TTL)
    
    public function getUserServiceClient(): UserServiceClient
    {
        return $this->getClient(
            UserServiceClient::class,
            'user.UserService',
            env('USER_SERVICE_HOST', 'user-service'),
            (int) env('USER_SERVICE_PORT', 9502)
        );
    }
}
```

## 配置说明

### 环境变量

| 变量名 | 说明 | 默认值 |
|--------|------|--------|
| `NACOS_HOST` | Nacos 地址 | nacos |
| `NACOS_PORT` | Nacos 端口 | 8848 |
| `GRPC_PORT` | gRPC 服务端口 | 各服务不同 |
| `DB_HOST` | MySQL 地址 | mysql |
| `DB_DATABASE` | 数据库名 | ecommerce |
| `REDIS_HOST` | Redis 地址 | redis |
| `JWT_SECRET` | JWT 密钥 | - |

### 服务端口映射

| 服务 | 容器内端口 | 宿主机端口 |
|------|-----------|-----------|
| Gateway | 9501 | 8081 |
| User | 9502 | 9502 |
| Product | 9503 | 9503 |
| Order | 9504 | 9504 |
| Inventory | 9505 | 9505 |
| MySQL | 3306 | 3306 |
| Redis | 6379 | 6379 |
| Nacos | 8848 | 8848 |

## 开发指南

### 添加新的 gRPC 方法

1. 在 `backend/proto/` 中的 Proto 文件定义新方法和消息
2. 生成 Protobuf Message 类到 `grpc/` 目录
3. 在后端服务的 `app/Grpc/` 中实现方法
4. 在 Gateway 的 `app/Grpc/` 中添加客户端方法
5. 在 Gateway 的 Service 层添加调用方法
6. 在 Gateway 的 Controller 中暴露 HTTP 接口

### 本地开发

```bash
# 进入服务目录
cd backend/user-service

# 安装依赖
composer install

# 启动服务 (需要先启动 MySQL, Redis, Nacos)
php bin/hyperf.php start
```

## 质检验收指南

本指南为质检人员提供完整的系统验收流程，包含环境准备、功能测试、异常场景测试等环节。

### 质检流程概览

| 阶段 | 内容 | 预计时间 |
|------|------|----------|
| 1. 环境准备 | 启动服务、检查容器状态 | 5 分钟 |
| 2. 基础健康检查 | Gateway、Nacos、数据库连接 | 3 分钟 |
| 3. 用户模块测试 | 注册、登录、信息获取 | 5 分钟 |
| 4. 商品模块测试 | 列表、详情、创建、更新、删除 | 10 分钟 |
| 5. 订单模块测试 | 创建、列表、支付、取消 | 10 分钟 |
| 6. 库存模块测试 | 查询、更新、锁定/释放 | 5 分钟 |
| 7. 异常场景测试 | 参数校验、权限、边界条件 | 10 分钟 |
| 8. 自动化测试 | 运行集成测试脚本 | 2 分钟 |

### 一、环境准备

#### 1.1 启动所有服务

```bash
# 首次启动 (构建镜像)
docker-compose up -d --build

# 后续启动
docker-compose up -d

# 等待服务完全启动 (约 60-90 秒)
sleep 90
```

#### 1.2 检查容器状态

```bash
docker-compose ps
```

预期结果：所有 8 个容器状态为 `Up`

| 容器名 | 状态 | 端口 |
|--------|------|------|
| ecommerce-gateway | Up | 8081 |
| ecommerce-user | Up | 9502 |
| ecommerce-product | Up | 9503 |
| ecommerce-order | Up | 9504 |
| ecommerce-inventory | Up | 9505 |
| ecommerce-mysql | Up | 3306 |
| ecommerce-redis | Up | 6379 |
| ecommerce-nacos | Up | 8848 |

### 二、基础健康检查

#### 2.1 Gateway 健康检查

```bash
curl -s http://localhost:8081/health | jq .
```

✅ 预期返回：
```json
{"status":"ok","service":"gateway"}
```

#### 2.2 Nacos 服务注册检查

```bash
# 检查已注册的服务列表
curl -s "http://localhost:8848/nacos/v1/ns/service/list?pageNo=1&pageSize=10" | jq .
```

✅ 预期返回 4 个服务：
- user.UserService
- product.ProductService
- order.OrderService
- inventory.InventoryService

#### 2.3 Nacos 控制台检查

- 访问: http://localhost:8848/nacos
- 账号: `nacos` 密码: `nacos`
- 检查「服务管理」→「服务列表」中有 4 个健康服务

### 三、用户模块测试

#### 3.1 用户注册

```bash
curl -s -X POST http://localhost:8081/api/user/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "qa_test_user",
    "password": "123456",
    "email": "qa@test.com",
    "phone": "13800138000"
  }' | jq .
```

✅ 预期：`code: 0`，返回用户信息

#### 3.2 用户登录

```bash
# 登录并保存 Token
LOGIN_RESULT=$(curl -s -X POST http://localhost:8081/api/user/login \
  -H "Content-Type: application/json" \
  -d '{"username": "qa_test_user", "password": "123456"}')

echo $LOGIN_RESULT | jq .

# 提取 Token 供后续使用
TOKEN=$(echo $LOGIN_RESULT | jq -r '.data.token')
echo "Token: $TOKEN"
```

✅ 预期：`code: 0`，返回 JWT Token

#### 3.3 获取用户信息 (需认证)

```bash
curl -s -X GET http://localhost:8081/api/user/info \
  -H "Authorization: Bearer $TOKEN" | jq .
```

✅ 预期：`code: 0`，返回当前用户信息

#### 3.4 更新用户信息

```bash
curl -s -X PUT http://localhost:8081/api/user/update \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"email": "qa_updated@test.com"}' | jq .
```

✅ 预期：`code: 0`，邮箱已更新

### 四、商品模块测试

#### 4.1 获取商品列表 (无需认证)

```bash
curl -s "http://localhost:8081/api/product/list?page=1&page_size=10" | jq .
```

✅ 预期：返回商品列表，包含预置的 4 个测试商品

#### 4.2 获取商品详情

```bash
curl -s http://localhost:8081/api/product/detail/1 | jq .
```

✅ 预期：返回 ID=1 的商品详细信息

#### 4.3 获取分类列表

```bash
curl -s http://localhost:8081/api/product/category/list | jq .
```

✅ 预期：返回 6 个商品分类

#### 4.4 创建商品 (需认证) - 重点测试项

```bash
curl -s -X POST http://localhost:8081/api/product/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "QA测试商品",
    "description": "质检测试用商品",
    "price": 99.99,
    "image": "http://example.com/test.jpg",
    "category_id": 1,
    "stock": 100
  }' | jq .
```

✅ 预期：`code: 0`，商品创建成功
⚠️ 注意：`price` 字段传递 float 类型 (99.99)，验证类型转换修复

#### 4.5 更新商品

```bash
# 假设新创建的商品 ID 为 5
curl -s -X PUT http://localhost:8081/api/product/update/5 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "QA测试商品-已更新",
    "price": 199.99
  }' | jq .
```

✅ 预期：`code: 0`，商品信息已更新

#### 4.6 删除商品

```bash
curl -s -X DELETE http://localhost:8081/api/product/delete/5 \
  -H "Authorization: Bearer $TOKEN" | jq .
```

✅ 预期：`code: 0`，删除成功

### 五、订单模块测试

#### 5.1 创建订单 (需认证)

```bash
ORDER_RESULT=$(curl -s -X POST http://localhost:8081/api/order/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "items": [
      {"product_id": 1, "quantity": 1},
      {"product_id": 2, "quantity": 2}
    ]
  }')

echo $ORDER_RESULT | jq .

# 提取订单 ID
ORDER_ID=$(echo $ORDER_RESULT | jq -r '.data.order.id')
echo "Order ID: $ORDER_ID"
```

✅ 预期：`code: 0`，订单创建成功，库存已锁定

#### 5.2 获取订单列表

```bash
curl -s "http://localhost:8081/api/order/list?page=1&page_size=10" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

✅ 预期：返回当前用户的订单列表

#### 5.3 获取订单详情

```bash
curl -s http://localhost:8081/api/order/detail/$ORDER_ID \
  -H "Authorization: Bearer $TOKEN" | jq .
```

✅ 预期：返回订单详情，包含订单项

#### 5.4 支付订单

```bash
curl -s -X PUT http://localhost:8081/api/order/pay/$ORDER_ID \
  -H "Authorization: Bearer $TOKEN" | jq .
```

✅ 预期：`code: 0`，订单状态变为已支付，库存已扣减

#### 5.5 取消订单测试

```bash
# 先创建一个新订单用于取消测试
NEW_ORDER=$(curl -s -X POST http://localhost:8081/api/order/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"items": [{"product_id": 3, "quantity": 1}]}')

NEW_ORDER_ID=$(echo $NEW_ORDER | jq -r '.data.order.id')

# 取消订单
curl -s -X PUT http://localhost:8081/api/order/cancel/$NEW_ORDER_ID \
  -H "Authorization: Bearer $TOKEN" | jq .
```

✅ 预期：`code: 0`，订单已取消，库存已释放

### 六、库存模块测试

#### 6.1 查询库存

```bash
curl -s http://localhost:8081/api/inventory/stock/1 \
  -H "Authorization: Bearer $TOKEN" | jq .
```

✅ 预期：返回商品 ID=1 的库存信息

#### 6.2 更新库存

```bash
curl -s -X PUT http://localhost:8081/api/inventory/update \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "product_id": 1,
    "quantity": 500
  }' | jq .
```

✅ 预期：`code: 0`，库存已更新

### 七、异常场景测试

#### 7.1 未认证访问受保护接口

```bash
curl -s http://localhost:8081/api/user/info | jq .
```

✅ 预期：返回 401 或认证失败错误

#### 7.2 无效 Token

```bash
curl -s http://localhost:8081/api/user/info \
  -H "Authorization: Bearer invalid_token_here" | jq .
```

✅ 预期：返回 Token 无效错误

#### 7.3 参数校验 - 缺少必填字段

```bash
curl -s -X POST http://localhost:8081/api/user/register \
  -H "Content-Type: application/json" \
  -d '{"username": "test"}' | jq .
```

✅ 预期：返回参数校验错误，提示缺少 password

#### 7.4 商品不存在

```bash
curl -s http://localhost:8081/api/product/detail/99999 | jq .
```

✅ 预期：返回商品不存在错误

#### 7.5 库存不足创建订单

```bash
curl -s -X POST http://localhost:8081/api/order/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "items": [{"product_id": 1, "quantity": 999999}]
  }' | jq .
```

✅ 预期：返回库存不足错误

#### 7.6 重复支付订单

```bash
# 对已支付的订单再次支付
curl -s -X PUT http://localhost:8081/api/order/pay/$ORDER_ID \
  -H "Authorization: Bearer $TOKEN" | jq .
```

✅ 预期：返回订单状态错误

### 八、自动化测试

#### 8.1 运行集成测试脚本

```bash
bash backend/tests/api_test.sh
```

✅ 预期输出：
```
==========================================
Test Results
==========================================
Passed: 22
Failed: 0
Total: 22

All tests passed!
```

### 九、质检结果记录表

| 测试项 | 预期结果 | 实际结果 | 通过 |
|--------|----------|----------|------|
| 容器启动 | 8 个容器 Up | | ☐ |
| Gateway 健康检查 | status: ok | | ☐ |
| Nacos 服务注册 | 4 个服务 | | ☐ |
| 用户注册 | code: 0 | | ☐ |
| 用户登录 | 返回 Token | | ☐ |
| 获取用户信息 | code: 0 | | ☐ |
| 商品列表 | 返回列表 | | ☐ |
| 商品详情 | 返回详情 | | ☐ |
| 创建商品 (float price) | code: 0 | | ☐ |
| 创建订单 | code: 0 | | ☐ |
| 支付订单 | code: 0 | | ☐ |
| 取消订单 | code: 0 | | ☐ |
| 库存查询 | 返回库存 | | ☐ |
| 未认证访问 | 401 错误 | | ☐ |
| 参数校验 | 校验错误 | | ☐ |
| 自动化测试 | 22/22 通过 | | ☐ |

### 十、一键质检脚本

将以下脚本保存为 `qa_check.sh` 并执行：

```bash
#!/bin/bash
set -e

echo "=========================================="
echo "电商微服务系统 - 质检验收脚本"
echo "=========================================="

echo ""
echo ">>> 1. 检查容器状态"
docker-compose ps

echo ""
echo ">>> 2. Gateway 健康检查"
HEALTH=$(curl -s http://localhost:8081/health)
echo $HEALTH | jq .
if [[ $(echo $HEALTH | jq -r '.status') != "ok" ]]; then
  echo "❌ Gateway 健康检查失败"
  exit 1
fi
echo "✅ Gateway 健康检查通过"

echo ""
echo ">>> 3. Nacos 服务检查"
SERVICES=$(curl -s "http://localhost:8848/nacos/v1/ns/service/list?pageNo=1&pageSize=10")
echo $SERVICES | jq .
SERVICE_COUNT=$(echo $SERVICES | jq -r '.count')
if [[ $SERVICE_COUNT -lt 4 ]]; then
  echo "❌ Nacos 服务注册不完整 (期望 4 个，实际 $SERVICE_COUNT 个)"
  exit 1
fi
echo "✅ Nacos 服务注册正常"

echo ""
echo ">>> 4. 用户注册测试"
REGISTER=$(curl -s -X POST http://localhost:8081/api/user/register \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"qa_$(date +%s)\",\"password\":\"123456\",\"email\":\"qa@test.com\",\"phone\":\"13800138000\"}")
echo $REGISTER | jq .

echo ""
echo ">>> 5. 用户登录测试"
LOGIN=$(curl -s -X POST http://localhost:8081/api/user/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}')
TOKEN=$(echo $LOGIN | jq -r '.data.token')
if [[ -z "$TOKEN" || "$TOKEN" == "null" ]]; then
  echo "❌ 登录失败，无法获取 Token"
  exit 1
fi
echo "✅ 登录成功，Token: ${TOKEN:0:50}..."

echo ""
echo ">>> 6. 商品列表测试"
PRODUCTS=$(curl -s "http://localhost:8081/api/product/list?page=1&page_size=5")
echo $PRODUCTS | jq '.data.list[].name'
echo "✅ 商品列表获取成功"

echo ""
echo ">>> 7. 创建商品测试 (验证 price float 类型)"
CREATE_PRODUCT=$(curl -s -X POST http://localhost:8081/api/product/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"QA测试商品","description":"测试","price":99.99,"image":"","category_id":1,"stock":10}')
echo $CREATE_PRODUCT | jq .
if [[ $(echo $CREATE_PRODUCT | jq -r '.code') != "0" ]]; then
  echo "❌ 创建商品失败"
  exit 1
fi
echo "✅ 创建商品成功 (price float 类型处理正常)"

echo ""
echo ">>> 8. 创建订单测试"
ORDER=$(curl -s -X POST http://localhost:8081/api/order/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"items":[{"product_id":1,"quantity":1}]}')
echo $ORDER | jq .
if [[ $(echo $ORDER | jq -r '.code') != "0" ]]; then
  echo "❌ 创建订单失败"
  exit 1
fi
echo "✅ 创建订单成功"

echo ""
echo ">>> 9. 运行自动化测试"
bash backend/tests/api_test.sh

echo ""
echo "=========================================="
echo "✅ 质检验收完成 - 所有测试通过"
echo "=========================================="
```

### 十一、常见问题排查

| 问题 | 可能原因 | 解决方案 |
|------|----------|----------|
| 容器启动失败 | 端口占用 | `lsof -i :8081` 检查端口 |
| Nacos 服务未注册 | 启动顺序问题 | 等待 90 秒或重启服务 |
| gRPC 调用失败 | 服务未就绪 | 检查目标服务日志 |
| Token 无效 | JWT_SECRET 不一致 | 检查环境变量配置 |
| 数据库连接失败 | MySQL 未就绪 | 等待 MySQL 完全启动 |

```bash
# 查看服务日志
docker-compose logs -f gateway-service
docker-compose logs -f user-service

# 重启单个服务
docker-compose restart gateway-service

# 完全重建
docker-compose down -v && docker-compose up -d --build
```

---

### 旧版 API 功能测试 (CURL 命令)

#### 2.1 用户注册

```bash
curl -X POST http://localhost:8081/api/user/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "password": "123456",
    "email": "test@example.com",
    "phone": "13800138000"
  }'
```

预期返回:
```json
{"code":0,"message":"注册成功","data":{"user":{"id":1,"username":"testuser","email":"test@example.com","phone":"13800138000"}}}
```

#### 2.2 用户登录 (获取 Token)

```bash
curl -X POST http://localhost:8081/api/user/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "password": "123456"
  }'
```

预期返回:
```json
{"code":0,"message":"登录成功","data":{"token":"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...","user":{...}}}
```

**保存返回的 token 用于后续请求**

#### 2.3 获取用户信息 (需认证)

```bash
# 将 YOUR_TOKEN 替换为登录返回的 token
curl -X GET http://localhost:8081/api/user/info \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 2.4 获取商品列表 (无需认证)

```bash
curl -X GET "http://localhost:8081/api/product/list?page=1&page_size=10"
```

预期返回:
```json
{"code":0,"message":"success","data":{"list":[{"id":1,"name":"iPhone 15 Pro","price":"7999.00",...}],"total":10,"page":1,"page_size":10}}
```

#### 2.5 获取商品详情

```bash
curl -X GET http://localhost:8081/api/product/detail/1
```

#### 2.6 获取分类列表

```bash
curl -X GET http://localhost:8081/api/product/category/list
```

#### 2.7 创建订单 (需认证)

```bash
curl -X POST http://localhost:8081/api/order/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "items": [
      {"product_id": 1, "quantity": 1},
      {"product_id": 2, "quantity": 2}
    ]
  }'
```

预期返回:
```json
{"code":0,"message":"订单创建成功","data":{"order":{"id":1,"order_no":"ORD20250127...","status":0,"total_amount":"..."}}}
```

#### 2.8 获取订单列表 (需认证)

```bash
curl -X GET "http://localhost:8081/api/order/list?page=1&page_size=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 2.9 支付订单 (需认证)

```bash
# 将 ORDER_ID 替换为实际订单 ID
curl -X PUT http://localhost:8081/api/order/pay/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 2.10 查询库存 (需认证)

```bash
curl -X GET http://localhost:8081/api/inventory/stock/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 三、自动化测试

```bash
# 运行完整的 API 集成测试 (22 个测试用例)
bash backend/tests/api_test.sh
```

预期输出:
```
==========================================
Test Results
==========================================
Passed: 22
Failed: 0
Total: 22

All tests passed!
```

### 四、一键验收脚本

```bash
#!/bin/bash
# 保存为 verify.sh 并执行

echo "=== 1. 检查容器状态 ==="
docker-compose ps

echo ""
echo "=== 2. Gateway 健康检查 ==="
curl -s http://localhost:8081/health | jq .

echo ""
echo "=== 3. 用户注册测试 ==="
REGISTER_RESULT=$(curl -s -X POST http://localhost:8081/api/user/register \
  -H "Content-Type: application/json" \
  -d '{"username":"qa_test_'$(date +%s)'","password":"123456","email":"qa@test.com","phone":"13900139000"}')
echo $REGISTER_RESULT | jq .

echo ""
echo "=== 4. 用户登录测试 ==="
LOGIN_RESULT=$(curl -s -X POST http://localhost:8081/api/user/login \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"123456"}')
TOKEN=$(echo $LOGIN_RESULT | jq -r '.data.token')
echo "Token: ${TOKEN:0:50}..."

echo ""
echo "=== 5. 商品列表测试 ==="
curl -s "http://localhost:8081/api/product/list?page=1&page_size=3" | jq '.data.list[].name'

echo ""
echo "=== 6. 创建订单测试 ==="
curl -s -X POST http://localhost:8081/api/order/create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"items":[{"product_id":1,"quantity":1}]}' | jq .

echo ""
echo "=== 验收完成 ==="
```

### 五、常见问题排查

```bash
# 查看服务日志
docker-compose logs -f gateway-service
docker-compose logs -f user-service

# 重启单个服务
docker-compose restart gateway-service

# 重建并重启所有服务
docker-compose down && docker-compose up -d --build

# 清理并重新开始
docker-compose down -v && docker-compose up -d --build
```

## 常见问题

### Q: 服务无法连接 Nacos?
检查 Nacos 容器是否健康运行，确保网络配置正确。可以通过以下命令验证：
```bash
# 检查 Nacos 健康状态
curl http://localhost:8848/nacos/v1/console/health/readiness

# 检查服务注册
curl -s "http://localhost:8848/nacos/v1/ns/service/list?pageNo=1&pageSize=10" | jq .
```

### Q: gRPC 调用返回空响应?
检查 Protobuf Message 类是否正确生成，确保 GPBMetadata 初始化正确。

### Q: JWT 认证失败?
确保 `JWT_SECRET` 环境变量在所有服务中一致。

## 数据库初始化

数据库通过 `backend/schema.sql` 自动初始化，包含：

### 表结构

| 表名 | 所属服务 | 说明 |
|------|----------|------|
| users | user-service | 用户表 |
| categories | product-service | 商品分类表 |
| products | product-service | 商品表 |
| inventory | inventory-service | 库存表 |
| orders | order-service | 订单表 |
| order_items | order-service | 订单明细表 |
| operation_logs | gateway-service | 操作日志表 |

### 测试数据

- 2 个测试用户 (admin/test, 密码: password)
- 6 个商品分类 (电子产品、服装及子分类)
- 4 个测试商品 (iPhone, MacBook, 华为, ThinkPad)
- 4 条库存记录

### 手动初始化

如需手动初始化数据库：

```bash
# 进入 MySQL 容器
docker exec -it ecommerce-mysql mysql -uroot -proot123456

# 执行初始化脚本
source /docker-entrypoint-initdb.d/init.sql
```

## 依赖版本管理

详细的依赖版本策略请参考 [docs/dependencies.md](docs/dependencies.md)

### 核心依赖版本

| 依赖 | 版本 | 说明 |
|------|------|------|
| PHP | >= 8.1 | 运行时 |
| Hyperf | ~3.1.0 | 框架 |
| Swoole | >= 5.0 | 协程服务器 |
| google/protobuf | ^3.25 | Protobuf 序列化 |
| firebase/php-jwt | ^6.10 | JWT 认证 |

### 版本一致性策略

1. **Hyperf 组件**: 所有服务统一使用 `~3.1.0`
2. **Protobuf**: 统一使用 `^3.25`
3. **开发依赖**: 统一版本，确保测试环境一致

## 更新日志

### 2026-02-06

- 修复 `ProductService.createProduct()` 价格类型问题：
  - 将 `$price` 参数类型从 `string` 改为 `float`，匹配前端传递的数据类型
  - 在调用 gRPC `CreateProductRequest::setPrice()` 时将 `float` 转换为 `string`
  - `ProductController` 显式将 `$validated['price']` 转换为 `float` 类型

## License

MIT
