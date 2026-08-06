<?php

namespace Xibo\Support\Tests\Monolog;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Xibo\Support\Monolog\Processor\ProxyIpProcessor;

class ProxyIpProcessorTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        foreach (['X_FORWARDED_FOR', 'HTTP_X_FORWARDED_FOR', 'CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            unset($_SERVER[$key]);
        }
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    public function testGetIpReturnsNullWhenNoServerKeysPresent(): void
    {
        $this->assertNull(ProxyIpProcessor::getIp());
    }

    public function testGetIpReadsXForwardedForFirst(): void
    {
        $_SERVER['X_FORWARDED_FOR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.2';
        $_SERVER['CLIENT_IP'] = '10.0.0.3';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.4';
        $this->assertSame('10.0.0.1', ProxyIpProcessor::getIp());
    }

    public function testGetIpFallsBackToHttpXForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.2';
        $_SERVER['CLIENT_IP'] = '10.0.0.3';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.4';
        $this->assertSame('10.0.0.2', ProxyIpProcessor::getIp());
    }

    public function testGetIpFallsBackToClientIp(): void
    {
        $_SERVER['CLIENT_IP'] = '10.0.0.3';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.4';
        $this->assertSame('10.0.0.3', ProxyIpProcessor::getIp());
    }

    public function testGetIpFallsBackToRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $this->assertSame('192.168.1.1', ProxyIpProcessor::getIp());
    }

    private function makeRecord(array $extra): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: $extra,
        );
    }

    public function testInvokeAttachesClientIpToExtra(): void
    {
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';
        $processor = new ProxyIpProcessor();
        $record = $processor($this->makeRecord([]));
        $this->assertSame('1.2.3.4', $record->extra['clientIp']);
    }

    public function testInvokeReturnsModifiedRecord(): void
    {
        $processor = new ProxyIpProcessor();
        $record = $processor($this->makeRecord(['existing' => 'value']));
        $this->assertArrayHasKey('clientIp', $record->extra);
        $this->assertArrayHasKey('existing', $record->extra);
    }

    public function testGetIpIsStaticallyCallable(): void
    {
        $_SERVER['REMOTE_ADDR'] = '5.5.5.5';
        $this->assertSame('5.5.5.5', ProxyIpProcessor::getIp());
    }
}
