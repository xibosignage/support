<?php

namespace Xibo\Support\Tests\Exception;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Xibo\Support\Exception\GeneralException;

class GeneralExceptionTest extends TestCase
{
    public function testDefaultHttpStatusCodeIs500(): void
    {
        $e = new GeneralException('oops');
        $this->assertSame(500, $e->getHttpStatusCode());
    }

    public function testGenerateHttpResponseWritesJsonBody(): void
    {
        $e = new GeneralException('something broke', 99);
        $response = $e->generateHttpResponse(new Response());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(99, $body['error']);
        $this->assertSame('something broke', $body['message']);
    }

    public function testGenerateHttpResponseAddsContentTypeHeader(): void
    {
        $e = new GeneralException('err');
        $response = $e->generateHttpResponse(new Response());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testGenerateHttpResponseSetsStatusCode(): void
    {
        $e = new GeneralException('err');
        $response = $e->generateHttpResponse(new Response());
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testGetErrorDataReturnsEmptyArrayByDefault(): void
    {
        $e = new GeneralException('err');
        $response = $e->generateHttpResponse(new Response());
        $body = json_decode((string) $response->getBody(), true);
        // Only 'error' and 'message' keys — no extra keys from getErrorData
        $this->assertArrayHasKey('error', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertCount(2, $body);
    }
}
