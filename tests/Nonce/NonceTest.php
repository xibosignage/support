<?php

namespace Xibo\Support\Tests\Nonce;

use PHPUnit\Framework\TestCase;
use Xibo\Support\Nonce\Nonce;

class NonceTest extends TestCase
{
    public function testSetNonceGeneratesHexNonceOfRequestedLength(): void
    {
        $nonce = (new Nonce())->setNonce(20, 10);
        // bin2hex doubles byte count: 20 bytes → 40 hex chars
        $this->assertSame(40, strlen($nonce->nonce));
    }

    public function testSetNonceGeneratesHexLookupOfRequestedLength(): void
    {
        $nonce = (new Nonce())->setNonce(20, 10);
        // 10 bytes → 20 hex chars
        $this->assertSame(20, strlen($nonce->lookup));
    }

    public function testSetNonceIsIdempotent(): void
    {
        $nonce = (new Nonce())->setNonce(20, 10);
        $originalNonce = $nonce->nonce;
        $originalLookup = $nonce->lookup;
        $originalHashed = $nonce->getHashed();

        $nonce->setNonce(20, 10);

        $this->assertSame($originalNonce, $nonce->nonce);
        $this->assertSame($originalLookup, $nonce->lookup);
        $this->assertSame($originalHashed, $nonce->getHashed());
    }

    public function testGetHashedReturnsBcryptHash(): void
    {
        $nonce = (new Nonce())->setNonce();
        $info = password_get_info($nonce->getHashed());
        $this->assertSame(PASSWORD_BCRYPT, $info['algo']);
    }

    public function testGetHashedVerifiesAgainstPlaintext(): void
    {
        $nonce = (new Nonce())->setNonce();
        $this->assertTrue(password_verify($nonce->nonce, $nonce->getHashed()));
    }

    public function testSetHashedAllowsManualRehydration(): void
    {
        $nonce = (new Nonce())->setNonce();
        $plaintext = $nonce->nonce;
        $hashed = $nonce->getHashed();

        $rehydrated = new Nonce();
        $rehydrated->setHashed($hashed);

        $this->assertTrue(password_verify($plaintext, $rehydrated->getHashed()));
    }

    public function testGetCompleteNonceUsesDefaultDelimiter(): void
    {
        $nonce = (new Nonce())->setNonce();
        $complete = $nonce->getCompleteNonce();
        $this->assertStringContainsString(':::', $complete);
        $parts = explode(':::', $complete);
        $this->assertSame($nonce->nonce, $parts[0]);
        $this->assertSame($nonce->lookup, $parts[1]);
    }

    public function testGetCompleteNonceUsesCustomDelimiter(): void
    {
        $nonce = (new Nonce())->setNonce();
        $complete = $nonce->getCompleteNonce('|');
        $parts = explode('|', $complete);
        $this->assertSame($nonce->nonce, $parts[0]);
        $this->assertSame($nonce->lookup, $parts[1]);
    }

    public function testToStringReturnsPlaintextNonce(): void
    {
        $nonce = (new Nonce())->setNonce();
        $this->assertSame($nonce->nonce, (string) $nonce);
    }

    public function testVerifyReturnsTrueForCorrectNonceAndFutureExpiry(): void
    {
        $nonce = (new Nonce())->setNonce();
        $nonce->expires = time() + 3600;
        $this->assertTrue($nonce->verify($nonce->nonce));
    }

    public function testVerifyReturnsFalseForWrongNonce(): void
    {
        $nonce = (new Nonce())->setNonce();
        $nonce->expires = time() + 3600;
        $this->assertFalse($nonce->verify('wrong-value'));
    }

    public function testVerifyReturnsFalseWhenExpired(): void
    {
        $nonce = (new Nonce())->setNonce();
        $nonce->expires = time() - 60;
        $this->assertFalse($nonce->verify($nonce->nonce));
    }

    public function testVerifyReturnsTrueAtExactExpiryBoundary(): void
    {
        $nonce = (new Nonce())->setNonce();
        $nonce->expires = time(); // >= semantics: exactly now is still valid
        $this->assertTrue($nonce->verify($nonce->nonce));
    }

    public function testJsonSerializeIncludesExpectedFields(): void
    {
        $nonce = (new Nonce())->setNonce();
        $nonce->entityId = 42;
        $nonce->action = 'login';
        $nonce->expires = time() + 3600;
        $nonce->meta = ['key' => 'value'];

        $data = $nonce->jsonSerialize();

        $this->assertSame(42, $data['entityId']);
        $this->assertSame($nonce->getHashed(), $data['hashed']);
        $this->assertSame($nonce->lookup, $data['lookup']);
        $this->assertSame('login', $data['action']);
        $this->assertSame($nonce->expires, $data['expires']);
        $this->assertSame(['key' => 'value'], $data['meta']);
    }

    public function testJsonSerializeOmitsPlaintextNonce(): void
    {
        $nonce = (new Nonce())->setNonce();
        $data = $nonce->jsonSerialize();
        $this->assertArrayNotHasKey('nonce', $data);
    }
}
