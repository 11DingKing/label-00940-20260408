# Build stage
FROM hyperf/hyperf:8.3-alpine-v3.19-swoole AS builder

WORKDIR /opt/www

# 使用 Alpine 预编译的 grpc 扩展并配置 fork support
RUN apk add --no-cache php83-pecl-grpc \
    && echo "grpc.enable_fork_support=1" >> /etc/php83/conf.d/50_grpc.ini

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy source code
COPY . .

# Generate autoloader
RUN composer dump-autoload --optimize --no-dev

# Production stage
FROM hyperf/hyperf:8.3-alpine-v3.19-swoole

LABEL maintainer="E-commerce Team" \
      description="Gateway Service for E-commerce Microservices"

WORKDIR /opt/www

# 使用 Alpine 预编译的 grpc 扩展并配置 fork support
RUN apk add --no-cache php83-pecl-grpc \
    && echo "grpc.enable_fork_support=1" >> /etc/php83/conf.d/50_grpc.ini

# Copy from builder
COPY --from=builder /opt/www .

# Create runtime directory and clear cache
RUN mkdir -p runtime/logs && chmod -R 777 runtime && rm -rf runtime/container

# Copy env file
RUN cp .env.example .env

EXPOSE 9501

ENTRYPOINT ["php", "/opt/www/bin/hyperf.php", "start"]
