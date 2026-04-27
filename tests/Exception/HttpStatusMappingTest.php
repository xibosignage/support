<?php

namespace Xibo\Support\Tests\Exception;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\AuthenticationRequiredException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\DeadLockException;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\ExpiredException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InstallationError;
use Xibo\Support\Exception\InstanceSuspendedException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\InvalidNonceException;
use Xibo\Support\Exception\LibraryFullException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Exception\TaskRunException;
use Xibo\Support\Exception\UpgradePendingException;
use Xibo\Support\Exception\ValueTooLargeException;

class HttpStatusMappingTest extends TestCase
{
    /** @dataProvider statusCodeProvider */
    public function testHttpStatusCode(GeneralException $exception, int $expected): void
    {
        $this->assertSame($expected, $exception->getHttpStatusCode());
    }

    /** @dataProvider statusCodeProvider */
    public function testGenerateHttpResponseSetsCorrectStatus(GeneralException $exception, int $expected): void
    {
        $response = $exception->generateHttpResponse(new Response());
        $this->assertSame($expected, $response->getStatusCode());
    }

    public static function statusCodeProvider(): array
    {
        return [
            'GeneralException'                   => [new GeneralException('e'), 500],
            'ConfigurationException'             => [new ConfigurationException('e'), 500],
            'ControllerNotImplemented'           => [new ControllerNotImplemented('e'), 500],
            'DeadLockException'                  => [new DeadLockException('e'), 500],
            'ExpiredException'                   => [new ExpiredException('e'), 500],
            'InstallationError'                  => [new InstallationError('e'), 500],
            'InvalidNonceException'              => [new InvalidNonceException(), 500],
            'LibraryFullException'               => [new LibraryFullException('e'), 500],
            'TaskRunException'                   => [new TaskRunException('e'), 500],
            'ValueTooLargeException'             => [new ValueTooLargeException('e'), 500],
            'AuthenticationRequiredException'    => [new AuthenticationRequiredException('e'), 401],
            'AccessDeniedException'              => [new AccessDeniedException('e'), 403],
            'InstanceSuspendedException'         => [new InstanceSuspendedException(), 403],
            'UpgradePendingException'            => [new UpgradePendingException(), 403],
            'NotFoundException'                  => [new NotFoundException(), 404],
            'DuplicateEntityException'           => [new DuplicateEntityException('e'), 409],
            'InvalidArgumentException'           => [new InvalidArgumentException('e'), 422],
        ];
    }

    // ------------------------------------------------------------------
    // Default messages
    // ------------------------------------------------------------------

    public function testNotFoundExceptionDefaultMessage(): void
    {
        $this->assertSame('Not Found', (new NotFoundException())->getMessage());
    }

    public function testUpgradePendingExceptionDefaultMessage(): void
    {
        $this->assertSame('Upgrade Pending', (new UpgradePendingException())->getMessage());
    }

    public function testInstanceSuspendedExceptionDefaultMessage(): void
    {
        $this->assertSame('Instance Suspended', (new InstanceSuspendedException())->getMessage());
    }

    public function testInvalidNonceExceptionDefaultMessage(): void
    {
        $this->assertSame('Token Expired', (new InvalidNonceException())->getMessage());
    }

    public function testInvalidNonceExceptionPreservesPrevious(): void
    {
        $prev = new \RuntimeException('root cause');
        $e = new InvalidNonceException('msg', 0, $prev);
        $this->assertSame($prev, $e->getPrevious());
    }

    // ------------------------------------------------------------------
    // InvalidArgumentException message synthesis
    // ------------------------------------------------------------------

    public function testInvalidArgumentSynthesizesMessageFromProperty(): void
    {
        $e = new InvalidArgumentException('', 'username');
        $this->assertSame('Invalid Argument username', $e->getMessage());
    }

    public function testInvalidArgumentEmptyMessageAndNoProperty(): void
    {
        $e = new InvalidArgumentException();
        $this->assertSame('Invalid Argument', $e->getMessage());
    }

    public function testInvalidArgumentCustomMessagePreserved(): void
    {
        $e = new InvalidArgumentException('Custom error', 'field');
        $this->assertSame('Custom error', $e->getMessage());
    }

    // ------------------------------------------------------------------
    // Error data payloads
    // ------------------------------------------------------------------

    public function testAccessDeniedExceptionIncludesHelpInPayload(): void
    {
        $e = new AccessDeniedException('denied', 'https://help.example.com');
        $body = $this->decodeResponse($e);
        $this->assertSame('https://help.example.com', $body['help']);
    }

    public function testInvalidArgumentExceptionIncludesPropertyAndHelp(): void
    {
        $e = new InvalidArgumentException('bad input', 'myField', 'https://docs.example.com');
        $body = $this->decodeResponse($e);
        $this->assertSame('myField', $body['property']);
        $this->assertSame('https://docs.example.com', $body['help']);
    }

    public function testNotFoundExceptionIncludesPropertyAndHelp(): void
    {
        $e = new NotFoundException('Not found', 'itemId', 'https://docs.example.com');
        $body = $this->decodeResponse($e);
        $this->assertSame('itemId', $body['property']);
        $this->assertSame('https://docs.example.com', $body['help']);
    }

    public function testDuplicateEntityExceptionIncludesPropertyAndHelp(): void
    {
        $e = new DuplicateEntityException('duplicate', 'email', 'use a different email');
        $body = $this->decodeResponse($e);
        $this->assertSame('email', $body['property']);
        $this->assertSame('use a different email', $body['help']);
    }

    public function testUpgradePendingExceptionIncludesPropertyAndHelp(): void
    {
        $e = new UpgradePendingException('pending', 'version', 'please upgrade');
        $body = $this->decodeResponse($e);
        $this->assertSame('version', $body['property']);
        $this->assertSame('please upgrade', $body['help']);
    }

    public function testInstanceSuspendedExceptionIncludesPropertyAndHelp(): void
    {
        $e = new InstanceSuspendedException('suspended', 'instanceId', 'contact support');
        $body = $this->decodeResponse($e);
        $this->assertSame('instanceId', $body['property']);
        $this->assertSame('contact support', $body['help']);
    }

    public function testNullHelpAndPropertyAreIncludedAsNull(): void
    {
        $e = new InvalidArgumentException('err');
        $body = $this->decodeResponse($e);
        $this->assertNull($body['property']);
        $this->assertNull($body['help']);
    }

    private function decodeResponse(GeneralException $e): array
    {
        $response = $e->generateHttpResponse(new Response());
        return json_decode((string) $response->getBody(), true);
    }
}
