<?php

namespace Xibo\Support\Tests\Monolog;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Xibo\Support\Monolog\Handler\RocketChatHandler;

class RocketChatHandlerTest extends TestCase
{
    /**
     * Pass $container by reference so the history middleware and the test share the same array.
     */
    private function makeHandler(array &$container, int $level = Logger::DEBUG): RocketChatHandler
    {
        $mock = new MockHandler(array_fill(0, 20, new Response(200)));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($container));
        $client = new Client(['handler' => $stack]);

        return new RocketChatHandler('https://example.com/webhook', $client, $level);
    }

    private function makeRecord(string $channel, int $level, string $levelName, string $message): array
    {
        return [
            'channel'    => $channel,
            'level'      => $level,
            'level_name' => $levelName,
            'message'    => $message,
            'context'    => [],
            'extra'      => [],
            'datetime'   => new \DateTimeImmutable(),
            'formatted'  => $message,
        ];
    }

    public function testWritePostsToConfiguredUrl(): void
    {
        $container = [];
        $handler = $this->makeHandler($container);
        $handler->handle($this->makeRecord('app', Logger::CRITICAL, 'CRITICAL', 'Test'));

        $this->assertCount(1, $container);
        $this->assertSame('POST', $container[0]['request']->getMethod());
        $this->assertSame('https://example.com/webhook', (string) $container[0]['request']->getUri());
    }

    public function testWriteSendsJsonPayloadWithFormattedText(): void
    {
        $container = [];
        $handler = $this->makeHandler($container);
        $handler->handle($this->makeRecord('app', Logger::CRITICAL, 'CRITICAL', 'Something went wrong'));

        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertArrayHasKey('text', $body);
        $this->assertStringContainsString('*app*', $body['text']);
        $this->assertStringContainsString('*CRITICAL*', $body['text']);
        $this->assertStringContainsString('Something went wrong', $body['text']);
    }

    public function testWriteSendsAttachmentsWithColor(): void
    {
        $container = [];
        $handler = $this->makeHandler($container);
        $handler->handle($this->makeRecord('app', Logger::CRITICAL, 'CRITICAL', 'Test'));

        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertArrayHasKey('attachments', $body);
        $this->assertArrayHasKey('color', $body['attachments']);
    }

    /** @dataProvider colorProvider */
    public function testAlertColors(int $level, string $levelName, string $expectedColor): void
    {
        $container = [];
        $handler = $this->makeHandler($container);
        $handler->handle($this->makeRecord('test', $level, $levelName, 'msg'));

        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertSame($expectedColor, $body['attachments']['color']);
    }

    public static function colorProvider(): array
    {
        return [
            'EMERGENCY' => [Logger::EMERGENCY, 'EMERGENCY', '#f44b42'],
            'ALERT'     => [Logger::ALERT,     'ALERT',     '#f44b42'],
            'CRITICAL'  => [Logger::CRITICAL,  'CRITICAL',  '#f44b42'],
            'ERROR'     => [Logger::ERROR,      'ERROR',     '#f44b42'],
            'WARNING'   => [Logger::WARNING,    'WARNING',   '#f4eb42'],
            'NOTICE'    => [Logger::NOTICE,     'NOTICE',    '#42f44e'], // 250 >= INFO(200) → green
            'INFO'      => [Logger::INFO,       'INFO',      '#42f44e'],
            'DEBUG'     => [Logger::DEBUG,      'DEBUG',     '#848484'],
        ];
    }

    public function testDefaultLevelIsCritical(): void
    {
        $container = [];
        $handler = $this->makeHandler($container, Logger::CRITICAL);

        $handler->handle($this->makeRecord('app', Logger::ERROR, 'ERROR', 'err'));
        $this->assertCount(0, $container); // ERROR(400) < CRITICAL(500), not sent

        $handler->handle($this->makeRecord('app', Logger::CRITICAL, 'CRITICAL', 'crit'));
        $this->assertCount(1, $container); // CRITICAL sent
    }
}
