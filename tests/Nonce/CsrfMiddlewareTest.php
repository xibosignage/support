<?php

namespace Xibo\Support\Tests\Nonce;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Xibo\Support\Exception\InvalidNonceException;
use Xibo\Support\Nonce\CsrfMiddleware;

class CsrfMiddlewareTest extends TestCase
{
    private array|null $originalSession;

    protected function setUp(): void
    {
        $this->originalSession = $_SESSION ?? null;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if ($this->originalSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->originalSession;
        }
    }

    private function next(): callable
    {
        return fn($request, $response) => $response;
    }

    private function middleware(string $key = 'csrfToken'): CsrfMiddleware
    {
        return new CsrfMiddleware($key);
    }

    // ------------------------------------------------------------------
    // Constructor validation
    // ------------------------------------------------------------------

    public function testConstructorRejectsEmptyKey(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        new CsrfMiddleware('');
    }

    public function testConstructorRejectsKeyWithSpaces(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        new CsrfMiddleware('foo bar');
    }

    public function testConstructorRejectsKeyWithSpecialChars(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        new CsrfMiddleware('foo!');
    }

    public function testConstructorAcceptsAlphanumericKey(): void
    {
        $mw = new CsrfMiddleware('csrfToken');
        $this->assertInstanceOf(CsrfMiddleware::class, $mw);
    }

    public function testConstructorAcceptsKeyWithDashAndUnderscore(): void
    {
        $mw = new CsrfMiddleware('csrf-token_v2');
        $this->assertInstanceOf(CsrfMiddleware::class, $mw);
    }

    // ------------------------------------------------------------------
    // Session token management
    // ------------------------------------------------------------------

    public function testGeneratesSessionTokenWhenAbsent(): void
    {
        $mw = $this->middleware();
        $request = new ServerRequest('GET', '/');
        $mw($request, new Response(), $this->next());
        $this->assertArrayHasKey('csrfToken', $_SESSION);
        $this->assertSame(40, strlen($_SESSION['csrfToken'])); // bin2hex(20 bytes)
    }

    public function testReusesExistingSessionToken(): void
    {
        $_SESSION['csrfToken'] = 'existing-token-value';
        $mw = $this->middleware();
        $request = new ServerRequest('GET', '/');
        $mw($request, new Response(), $this->next());
        $this->assertSame('existing-token-value', $_SESSION['csrfToken']);
    }

    // ------------------------------------------------------------------
    // Safe methods pass through without CSRF check
    // ------------------------------------------------------------------

    /** @dataProvider safeMethods */
    public function testSafeMethodsPassThrough(string $method): void
    {
        $mw = $this->middleware();
        $request = new ServerRequest($method, '/');
        $response = $mw($request, new Response(), $this->next());
        $this->assertInstanceOf(Response::class, $response);
    }

    public static function safeMethods(): array
    {
        return [['GET'], ['HEAD'], ['OPTIONS']];
    }

    // ------------------------------------------------------------------
    // POST/PUT/DELETE require valid CSRF token
    // ------------------------------------------------------------------

    /** @dataProvider unsafeMethods */
    public function testUnsafeMethodsThrowWithoutToken(string $method): void
    {
        $this->expectException(InvalidNonceException::class);
        $_SESSION['csrfToken'] = 'expected-token';
        $mw = $this->middleware();
        $request = new ServerRequest($method, '/');
        $mw($request, new Response(), $this->next());
    }

    /** @dataProvider unsafeMethods */
    public function testUnsafeMethodsPassWithMatchingHeader(string $method): void
    {
        $_SESSION['csrfToken'] = 'abc123';
        $mw = $this->middleware();
        $request = (new ServerRequest($method, '/'))
            ->withHeader('X-XSRF-TOKEN', 'abc123');
        $response = $mw($request, new Response(), $this->next());
        $this->assertInstanceOf(Response::class, $response);
    }

    /** @dataProvider unsafeMethods */
    public function testUnsafeMethodsThrowWithMismatchedHeader(string $method): void
    {
        $this->expectException(InvalidNonceException::class);
        $_SESSION['csrfToken'] = 'abc123';
        $mw = $this->middleware();
        $request = (new ServerRequest($method, '/'))
            ->withHeader('X-XSRF-TOKEN', 'wrong-token');
        $mw($request, new Response(), $this->next());
    }

    public static function unsafeMethods(): array
    {
        return [['POST'], ['PUT'], ['DELETE']];
    }

    public function testPostPassesWithMatchingBodyParam(): void
    {
        $_SESSION['csrfToken'] = 'abc123';
        $mw = $this->middleware();
        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['csrfToken' => 'abc123']);
        $response = $mw($request, new Response(), $this->next());
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testPostThrowsWithMismatchedBodyParam(): void
    {
        $this->expectException(InvalidNonceException::class);
        $_SESSION['csrfToken'] = 'abc123';
        $mw = $this->middleware();
        $request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['csrfToken' => 'wrong']);
        $mw($request, new Response(), $this->next());
    }

    public function testBodyParamOverridesHeader(): void
    {
        // Header is present with correct value, but body param overwrites $userToken with wrong value
        $this->expectException(InvalidNonceException::class);
        $_SESSION['csrfToken'] = 'correct';
        $mw = $this->middleware();
        $request = (new ServerRequest('POST', '/'))
            ->withHeader('X-XSRF-TOKEN', 'correct')
            ->withParsedBody(['csrfToken' => 'wrong']); // body overwrites userToken last
        $mw($request, new Response(), $this->next());
    }

    // ------------------------------------------------------------------
    // Request attributes
    // ------------------------------------------------------------------

    public function testRequestAttributesCsrfKeyAndTokenAreSet(): void
    {
        $_SESSION['csrfToken'] = 'my-token';
        $mw = $this->middleware();

        $capturedRequest = null;
        $next = function ($request, $response) use (&$capturedRequest) {
            $capturedRequest = $request;
            return $response;
        };

        $request = new ServerRequest('GET', '/');
        $mw($request, new Response(), $next);

        $this->assertSame('csrfToken', $capturedRequest->getAttribute('csrfKey'));
        $this->assertSame('my-token', $capturedRequest->getAttribute('csrfToken'));
    }
}
