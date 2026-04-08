#!/bin/bash

# Run all unit tests for each service
# Usage: ./run_tests.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$(dirname "$SCRIPT_DIR")"

echo "=========================================="
echo "Running Unit Tests for All Services"
echo "=========================================="
echo ""

SERVICES=("gateway-service" "user-service" "product-service" "order-service" "inventory-service")
TOTAL_PASSED=0
TOTAL_FAILED=0

for service in "${SERVICES[@]}"; do
    SERVICE_DIR="$BACKEND_DIR/$service"
    
    if [ -d "$SERVICE_DIR" ] && [ -f "$SERVICE_DIR/phpunit.xml" ]; then
        echo "--- Testing $service ---"
        cd "$SERVICE_DIR"
        
        if [ -d "vendor" ]; then
            if ./vendor/bin/co-phpunit --colors=always 2>/dev/null; then
                echo "✓ $service tests passed"
            else
                echo "✗ $service tests failed"
                ((TOTAL_FAILED++))
            fi
        else
            echo "⚠ Skipping $service (vendor not installed)"
        fi
        
        echo ""
    fi
done

echo "=========================================="
echo "Test Summary"
echo "=========================================="

if [ $TOTAL_FAILED -eq 0 ]; then
    echo "All service tests completed!"
else
    echo "Some services had test failures: $TOTAL_FAILED"
    exit 1
fi
