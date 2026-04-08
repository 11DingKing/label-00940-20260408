<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use Hyperf\Testing\TestCase;

abstract class AbstractTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function getAuthHeader(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    protected function assertSuccessResponse(array $response): void
    {
        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(0, $response['code']);
    }

    protected function assertErrorResponse(array $response, int $expectedCode = -1): void
    {
        $this->assertArrayHasKey('code', $response);
        $this->assertNotEquals(0, $response['code']);
        if ($expectedCode !== -1) {
            $this->assertEquals($expectedCode, $response['code']);
        }
    }
}
