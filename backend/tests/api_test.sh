#!/bin/bash

# E-commerce API Integration Test Script
# Usage: ./api_test.sh [base_url]

BASE_URL="${1:-http://localhost:8081}"
PASSED=0
FAILED=0
TOKEN=""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=========================================="
echo "E-commerce API Integration Tests"
echo "Base URL: $BASE_URL"
echo "=========================================="
echo ""

# Helper function to make requests
request() {
    local method=$1
    local endpoint=$2
    local data=$3
    local auth=$4
    
    local headers="-H 'Content-Type: application/json'"
    if [ -n "$auth" ]; then
        headers="$headers -H 'Authorization: Bearer $auth'"
    fi
    
    if [ -n "$data" ]; then
        eval "curl -s -X $method '$BASE_URL$endpoint' $headers -d '$data'"
    else
        eval "curl -s -X $method '$BASE_URL$endpoint' $headers"
    fi
}

# Test function
test_api() {
    local name=$1
    local expected_code=$2
    local response=$3
    
    local actual_code=$(echo "$response" | jq -r '.code // empty')
    
    if [ "$actual_code" == "$expected_code" ]; then
        echo -e "${GREEN}✓ PASS${NC}: $name"
        ((PASSED++))
    else
        echo -e "${RED}✗ FAIL${NC}: $name (expected code: $expected_code, got: $actual_code)"
        echo "  Response: $response"
        ((FAILED++))
    fi
}

echo "--- Health Check ---"
response=$(request GET "/health")
if echo "$response" | jq -e '.status == "ok"' > /dev/null 2>&1; then
    echo -e "${GREEN}✓ PASS${NC}: Health check"
    ((PASSED++))
else
    echo -e "${RED}✗ FAIL${NC}: Health check"
    ((FAILED++))
fi

echo ""
echo "--- User API Tests ---"

# Test: Register new user
USERNAME="testuser_$(date +%s)"
response=$(request POST "/api/user/register" "{\"username\":\"$USERNAME\",\"password\":\"password123\",\"email\":\"$USERNAME@test.com\"}")
test_api "Register new user" "0" "$response"

# Test: Register duplicate user
response=$(request POST "/api/user/register" "{\"username\":\"$USERNAME\",\"password\":\"password123\"}")
test_api "Register duplicate user (should fail)" "-1" "$response"

# Test: Login with valid credentials
response=$(request POST "/api/user/login" "{\"username\":\"$USERNAME\",\"password\":\"password123\"}")
test_api "Login with valid credentials" "0" "$response"
TOKEN=$(echo "$response" | jq -r '.data.token // empty')

# Test: Login with wrong password
response=$(request POST "/api/user/login" "{\"username\":\"$USERNAME\",\"password\":\"wrongpassword\"}")
test_api "Login with wrong password (should fail)" "-1" "$response"

# Test: Get user info with token
if [ -n "$TOKEN" ]; then
    response=$(request GET "/api/user/info" "" "$TOKEN")
    test_api "Get user info with token" "0" "$response"
fi

# Test: Get user info without token
response=$(request GET "/api/user/info")
test_api "Get user info without token (should fail)" "401" "$response"

echo ""
echo "--- Product API Tests ---"

# Test: Get product list
response=$(request GET "/api/product/list")
test_api "Get product list" "0" "$response"

# Test: Get product list with pagination
response=$(request GET "/api/product/list?page=1&page_size=5")
test_api "Get product list with pagination" "0" "$response"

# Test: Get product detail
response=$(request GET "/api/product/detail/1")
test_api "Get product detail" "0" "$response"

# Test: Get non-existent product
response=$(request GET "/api/product/detail/999999")
test_api "Get non-existent product (should fail)" "-1" "$response"

# Test: Get category list
response=$(request GET "/api/product/category/list")
test_api "Get category list" "0" "$response"

# Test: Create product without auth
response=$(request POST "/api/product/create" "{\"name\":\"Test\",\"price\":\"99.99\",\"category_id\":1}")
test_api "Create product without auth (should fail)" "401" "$response"

# Test: Create product with auth
if [ -n "$TOKEN" ]; then
    response=$(request POST "/api/product/create" "{\"name\":\"Test Product $(date +%s)\",\"price\":\"99.99\",\"category_id\":3,\"stock\":100}" "$TOKEN")
    test_api "Create product with auth" "0" "$response"
fi

echo ""
echo "--- Order API Tests ---"

# Test: Create order without auth
response=$(request POST "/api/order/create" "{\"items\":[{\"product_id\":1,\"quantity\":1}],\"address\":\"Test\",\"receiver\":\"Test\",\"phone\":\"13800138000\"}")
test_api "Create order without auth (should fail)" "401" "$response"

# Test: Create order with auth
if [ -n "$TOKEN" ]; then
    response=$(request POST "/api/order/create" "{\"items\":[{\"product_id\":1,\"quantity\":1}],\"address\":\"北京市朝阳区测试地址\",\"receiver\":\"测试用户\",\"phone\":\"13800138000\"}" "$TOKEN")
    test_api "Create order with auth" "0" "$response"
    ORDER_ID=$(echo "$response" | jq -r '.data.id // empty')
    
    # Test: Get order list
    response=$(request GET "/api/order/list" "" "$TOKEN")
    test_api "Get order list" "0" "$response"
    
    # Test: Get order detail
    if [ -n "$ORDER_ID" ]; then
        response=$(request GET "/api/order/detail/$ORDER_ID" "" "$TOKEN")
        test_api "Get order detail" "0" "$response"
        
        # Test: Pay order
        response=$(request PUT "/api/order/pay/$ORDER_ID" "{\"payment_method\":\"alipay\"}" "$TOKEN")
        test_api "Pay order" "0" "$response"
    fi
fi

# Test: Get order list without auth
response=$(request GET "/api/order/list")
test_api "Get order list without auth (should fail)" "401" "$response"

echo ""
echo "--- Inventory API Tests ---"

# Test: Get stock without auth
response=$(request GET "/api/inventory/stock/1")
test_api "Get stock without auth (should fail)" "401" "$response"

# Test: Get stock with auth
if [ -n "$TOKEN" ]; then
    response=$(request GET "/api/inventory/stock/1" "" "$TOKEN")
    test_api "Get stock with auth" "0" "$response"
fi

echo ""
echo "=========================================="
echo "Test Results"
echo "=========================================="
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo "Total: $((PASSED + FAILED))"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed!${NC}"
    exit 1
fi
