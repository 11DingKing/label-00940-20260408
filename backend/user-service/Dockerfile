# Build stage
FROM hyperf/hyperf:8.3-alpine-v3.19-swoole AS builder

WORKDIR /opt/www

# 使用 Alpine 预编译的 grpc 扩展并配置 fork support
RUN apk add --no-cache php83-pecl-grpc \
    && echo "grpc.enable_fork_support=1" >> /etc/php83/conf.d/50_grpc.ini

COPY composer.json composer.lock* ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev

# Production stage
FROM hyperf/hyperf:8.3-alpine-v3.19-swoole

LABEL maintainer="E-commerce Team" \
      description="User Service for E-commerce Microservices (gRPC)"

WORKDIR /opt/www

# 使用 Alpine 预编译的 grpc 扩展并配置 fork support
RUN apk add --no-cache php83-pecl-grpc \
    && echo "grpc.enable_fork_support=1" >> /etc/php83/conf.d/50_grpc.ini

COPY --from=builder /opt/www .

RUN mkdir -p runtime/logs && chmod -R 777 runtime && rm -rf runtime/container

RUN cp .env.example .env

EXPOSE 9502

ENTRYPOINT ["php", "/opt/www/bin/hyperf.php", "start"]
