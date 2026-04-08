#!/bin/bash

# gRPC PHP 代码生成脚本
# 使用 protoc 和 grpc_php_plugin 生成标准 gRPC 代码

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROTO_DIR="$SCRIPT_DIR"
BACKEND_DIR="$SCRIPT_DIR/.."

echo "=== gRPC PHP Code Generator ==="
echo "Proto directory: $PROTO_DIR"

# 检查 protoc 是否安装
if ! command -v protoc &> /dev/null; then
    echo "Error: protoc is not installed"
    echo "Please install protobuf compiler first"
    exit 1
fi

# 查找 grpc_php_plugin
GRPC_PLUGIN=""
if command -v grpc_php_plugin &> /dev/null; then
    GRPC_PLUGIN=$(which grpc_php_plugin)
elif [ -f "/usr/local/bin/grpc_php_plugin" ]; then
    GRPC_PLUGIN="/usr/local/bin/grpc_php_plugin"
elif [ -f "/usr/bin/grpc_php_plugin" ]; then
    GRPC_PLUGIN="/usr/bin/grpc_php_plugin"
fi

echo "Using grpc_php_plugin: $GRPC_PLUGIN"

# 生成函数
generate_for_service() {
    local service_name=$1
    local proto_files=$2
    local output_dir="$BACKEND_DIR/$service_name/grpc"
    
    echo ""
    echo "Generating for $service_name..."
    
    # 清理并创建输出目录
    rm -rf "$output_dir"
    mkdir -p "$output_dir"
    
    # 生成 PHP 消息类
    for proto_file in $proto_files; do
        echo "  Processing: $proto_file"
        
        protoc \
            --proto_path="$PROTO_DIR" \
            --php_out="$output_dir" \
            "$PROTO_DIR/$proto_file"
        
        # 如果有 grpc_php_plugin，生成服务接口
        if [ -n "$GRPC_PLUGIN" ]; then
            protoc \
                --proto_path="$PROTO_DIR" \
                --grpc_out="$output_dir" \
                --plugin=protoc-gen-grpc="$GRPC_PLUGIN" \
                "$PROTO_DIR/$proto_file"
        fi
    done
    
    echo "  Output: $output_dir"
}

# 为各服务生成代码
generate_for_service "user-service" "user.proto"
generate_for_service "product-service" "product.proto"
generate_for_service "order-service" "order.proto"
generate_for_service "inventory-service" "inventory.proto"

# Gateway 需要所有 proto
generate_for_service "gateway-service" "user.proto product.proto order.proto inventory.proto"

echo ""
echo "=== Generation Complete ==="
echo "Generated Protobuf message classes for all services"
