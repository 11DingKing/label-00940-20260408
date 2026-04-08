# 依赖版本管理策略

本文档说明各微服务的依赖版本一致性策略，确保服务间兼容性和可维护性。

## 核心依赖版本

所有服务统一使用以下核心依赖版本：

### 运行时环境

| 依赖 | 版本 | 说明 |
|------|------|------|
| PHP | >= 8.1 | 最低 PHP 版本要求 |
| Swoole | >= 5.0 | 协程 HTTP/gRPC 服务器 |
| MySQL | 8.0 | 数据库 |
| Redis | 7.x | 缓存和会话存储 |

### Hyperf 框架组件 (统一 ~3.1.0)

| 组件 | 版本 | 用途 |
|------|------|------|
| hyperf/framework | ~3.1.0 | 核心框架 |
| hyperf/config | ~3.1.0 | 配置管理 |
| hyperf/db-connection | ~3.1.0 | 数据库连接 |
| hyperf/redis | ~3.1.0 | Redis 客户端 |
| hyperf/cache | ~3.1.0 | 缓存抽象层 |
| hyperf/logger | ~3.1.0 | 日志组件 |
| hyperf/command | ~3.1.0 | 命令行工具 |
| hyperf/process | ~3.1.0 | 进程管理 |
| hyperf/memory | ~3.1.0 | 内存管理 |

### gRPC 相关组件

| 组件 | 版本 | 用途 |
|------|------|------|
| hyperf/grpc-server | ~3.1.0 | gRPC 服务端 |
| hyperf/grpc-client | ~3.1.0 | gRPC 客户端 |
| google/protobuf | ^3.25 | Protobuf 序列化 |

### 服务治理组件

| 组件 | 版本 | 用途 |
|------|------|------|
| hyperf/nacos | ~3.1.0 | Nacos 客户端 |
| hyperf/config-nacos | ~3.1.0 | Nacos 配置中心 |
| hyperf/service-governance-nacos | ~3.1.0 | Nacos 服务治理 |

### 其他依赖

| 组件 | 版本 | 用途 | 服务 |
|------|------|------|------|
| firebase/php-jwt | ^6.10 | JWT 认证 | gateway, user |
| hyperf/http-server | ~3.1.0 | HTTP 服务 | gateway |
| hyperf/validation | ~3.1.0 | 请求验证 | gateway |

## 各服务依赖矩阵

| 服务 | grpc-server | grpc-client | http-server | jwt |
|------|-------------|-------------|-------------|-----|
| gateway-service | ❌ | ✅ | ✅ | ✅ |
| user-service | ✅ | ❌ | ❌ | ✅ |
| product-service | ✅ | ✅ | ❌ | ❌ |
| order-service | ✅ | ✅ | ❌ | ❌ |
| inventory-service | ✅ | ❌ | ❌ | ❌ |

说明：
- gateway-service: 作为 API 网关，提供 HTTP 接口，通过 gRPC 调用后端服务
- user-service: 提供 gRPC 服务，处理用户认证
- product-service: 提供 gRPC 服务，调用 inventory-service 获取库存
- order-service: 提供 gRPC 服务，调用 product-service 和 inventory-service
- inventory-service: 提供 gRPC 服务，独立管理库存数据

## 开发依赖 (统一版本)

| 组件 | 版本 | 用途 |
|------|------|------|
| hyperf/devtool | ~3.1.0 | 开发工具 |
| hyperf/testing | ~3.1.0 | 测试框架 |
| friendsofphp/php-cs-fixer | ^3.0 | 代码风格检查 |
| phpstan/phpstan | ^1.0 | 静态分析 |
| mockery/mockery | ^1.0 | Mock 测试 |
| swoole/ide-helper | ^5.0 | IDE 支持 |

## 版本升级策略

### 1. 统一升级原则

所有 Hyperf 组件应同时升级到相同的次版本号，例如：
- 当前: ~3.1.0
- 升级: ~3.2.0 (所有服务同时升级)

### 2. 升级步骤

```bash
# 1. 更新所有服务的 composer.json
# 2. 在每个服务目录执行
composer update

# 3. 运行测试
./backend/tests/run_tests.sh

# 4. 重新构建 Docker 镜像
docker-compose build
```

### 3. 兼容性检查

升级前检查：
- [ ] Hyperf 官方升级指南
- [ ] Swoole 版本兼容性
- [ ] PHP 版本要求
- [ ] 第三方包兼容性

## Docker 镜像版本

| 镜像 | 版本 | 说明 |
|------|------|------|
| php | 8.3-cli-alpine | PHP 运行环境 |
| mysql | 8.0 | 数据库 |
| redis | 7-alpine | 缓存 |
| nacos/nacos-server | v2.2.3-slim | 服务注册中心 |

## 版本锁定

建议在生产环境中使用 `composer.lock` 文件锁定依赖版本：

```bash
# 生成 lock 文件
composer install

# 提交 lock 文件到版本控制
git add composer.lock
git commit -m "Lock dependency versions"
```

## 常见问题

### Q: 为什么使用 ~3.1.0 而不是 ^3.1.0？

A: 使用 `~3.1.0` 可以限制升级范围在 3.1.x 内，避免自动升级到 3.2.0 可能带来的不兼容问题。

### Q: 如何处理服务间的依赖冲突？

A: 由于各服务独立部署，依赖冲突的风险较低。但建议：
1. 保持核心框架版本一致
2. 定期同步升级所有服务
3. 使用 CI/CD 进行兼容性测试

### Q: Protobuf 类如何保持同步？

A: 本项目将生成的 Protobuf 类提交到版本控制，确保：
1. 所有服务使用相同的 .proto 定义
2. 修改 proto 后重新生成并同步到各服务
3. 参考 `backend/proto/` 目录下的源文件
