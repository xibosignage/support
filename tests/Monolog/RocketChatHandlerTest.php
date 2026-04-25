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
    private function makeHandler(int $level = Logger::DEBUG): array
    {
        $container = [];
        $mock = new MockHandler([new Response(200)]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($container));
        $client = new Client(['handler' => $stack]);

        $handler = new RocketChatHandler('https://example.com/webhook', $client, $level);
        return [$handler, &$container];
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
        [$handler, $container] = $this->makeHandler();
        $handler->handle($this->makeRecord('app', Logger::CRITICAL, 'CRITICAL', 'Test'));

        $this->assertCount(1, $container);
        $this->assertSame('POST', $container[0]['request']->getMethod());
        $this->assertSame('https://example.com/webhook', (string) $container[0]['request']->getUri());
    }

    public function testWriteSendsJsonPayloadWithFormattedText(): void
    {
        [$handler, $container] = $this->makeHandler();
        $handler->handle($this->makeRecord('app', Logger::CRITICAL, 'CRITICAL', 'Something went wrong'));

        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertArrayHasKey('text', $body);
        $this->assertStringContainsString('*app*', $body['text']);
        $this->assertStringContainsString('*CRITICAL*', $body['text']);
        $this->assertStringContainsString('Something went wrong', $body['text']);
    }

    public function testWriteSendsAttachmentsWithColor(): void
    {
        [$handler, $container] = $this->makeHandler();
        $handler->handle($this->makeRecord('app', Logger::CRITICAL, 'CRITICAL', 'Test'));

        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertArrayHasKey('attachments', $body);
        $this->assertArrayHasKey('color', $body['attachments']);
    }

    /** @dataProvider colorProvider */
    public function testAlertColors(int $level, string $levelName, string $expectedColor): void
    {
        // Add enough mock responses
        $container = [];
        $mock = new MockHandler(array_fill(0, 10, new Response(200)));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($container));
        $client = new Client(['handler' => $stack]);

        $handler = new RocketChatHandler('https://example.com/webhook', $client, Logger::DEBUG);
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
            'INFO'      => [Logger::INFO,       'INFO',      '#42f44e'],
            'DEBUG'     => [Logger::DEBUG,      'DEBUG',     '#848484'],
            'NOTICE'    => [Logger::NOTICE,     'NOTICE',    '#3c55e0'],
        ];
    }

    public function testDefaultLevelIsCritical(): void
    {
        $container = [];
        $mock = new MockHandler([new Response(200), new Response(200)]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($container));
        $client = new Client(['handler' => $stack]);

        $handler = new RocketChatHandler('https://example.com/webhook', $client);

        // ERROR should be handled (>= CRITICAL? No, ERROR < CRITICAL)
        // Only CRITICAL and above pass default threshold
        $handler->handle($this->makeRecord('app', Logger::ERROR, 'ERROR', 'err'));
        $this->assertCount(0, $container); // ERROR < CRITICAL, not sent

        $handler->handle($this->makeRecord('app', Logger::CRITICAL, 'CRITICAL', 'crit'));
        $this->assertCount(1, $container); // CRITICAL sent
    }
}
