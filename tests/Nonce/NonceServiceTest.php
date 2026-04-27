<?php

namespace Xibo\Support\Tests\Nonce;

use PHPUnit\Framework\TestCase;
use Xibo\Support\Exception\InvalidNonceException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Nonce\Nonce;
use Xibo\Support\Tests\Fixtures\ConcreteNonceService;

class NonceServiceTest extends TestCase
{
    private ConcreteNonceService $service;

    protected function setUp(): void
    {
        $this->service = new ConcreteNonceService();
    }

    public function testCreateProducesNonceWithCorrectEntityIdAndAction(): void
    {
        $nonce = $this->service->create(7, 'login', 300);
        $this->assertSame(7, $nonce->entityId);
        $this->assertSame('login', $nonce->action);
    }

    public function testCreateSetsEmptyMeta(): void
    {
        $nonce = $this->service->create(1, 'test', 60);
        $this->assertSame([], $nonce->meta);
    }

    public function testCreateSetsExpiryInFuture(): void
    {
        $before = time();
        $nonce = $this->service->create(1, 'test', 300);
        $after = time();
        $this->assertGreaterThanOrEqual($before + 300, $nonce->expires);
        $this->assertLessThanOrEqual($after + 300, $nonce->expires);
    }

    public function testHydrateFromArrayPopulatesAllFields(): void
    {
        $original = $this->service->create(5, 'upload', 3600);
        $data = $original->jsonSerialize();

        $hydrated = $this->service->hydrate($data);

        $this->assertSame($data['entityId'], $hydrated->entityId);
        $this->assertSame($data['lookup'], $hydrated->lookup);
        $this->assertSame($data['action'], $hydrated->action);
        $this->assertSame($data['expires'], $hydrated->expires);
        $this->assertSame($data['hashed'], $hydrated->getHashed());
    }

    public function testHydrateFromJsonStringDecodesAndPopulates(): void
    {
        $original = $this->service->create(5, 'upload', 3600);
        $json = json_encode($original->jsonSerialize());

        $hydrated = $this->service->hydrate($json);

        $this->assertSame($original->entityId, $hydrated->entityId);
        $this->assertSame($original->action, $hydrated->action);
    }

    public function testHydrateMissingMetaDefaultsToEmptyArray(): void
    {
        $data = [
            'entityId' => 1,
            'lookup'   => 'abc',
            'action'   => 'test',
            'expires'  => time() + 3600,
            'hashed'   => 'hash',
        ];

        $hydrated = $this->service->hydrate($data);
        $this->assertSame([], $hydrated->meta);
    }

    public function testGetVerifiedReturnsNonceWhenValid(): void
    {
        $nonce = $this->service->create(1, 'action', 3600);
        $this->service->store[] = $nonce;

        $verified = $this->service->getVerified($nonce->nonce, $nonce->lookup, 'action');
        $this->assertSame($nonce, $verified);
    }

    public function testGetVerifiedThrowsOnWrongPlaintext(): void
    {
        $this->expectException(InvalidNonceException::class);
        $nonce = $this->service->create(1, 'action', 3600);
        $this->service->store[] = $nonce;

        $this->service->getVerified('wrong-plaintext', $nonce->lookup, 'action');
    }

    public function testGetVerifiedThrowsWhenExpired(): void
    {
        $this->expectException(InvalidNonceException::class);
        $nonce = $this->service->create(1, 'action', -1);
        $this->service->store[] = $nonce;

        $this->service->getVerified($nonce->nonce, $nonce->lookup, 'action');
    }

    public function testGetVerifiedThrowsNotFoundWhenLookupMissing(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->getVerified('nonce', 'nonexistent-lookup', 'action');
    }

    public function testGetVerifiedThrowsInvalidNonceExceptionOnActionMismatch(): void
    {
        $this->expectException(InvalidNonceException::class);
        $nonce = $this->service->create(1, 'correct-action', 3600);
        $this->service->store[] = $nonce;

        $this->service->getVerified($nonce->nonce, $nonce->lookup, 'wrong-action');
    }

    public function testGetSplitVerifiedSplitsOnDefaultDelimiter(): void
    {
        $nonce = $this->service->create(1, 'action', 3600);
        $this->service->store[] = $nonce;

        $complete = $nonce->getCompleteNonce(':::');
        $verified = $this->service->getSplitVerified($complete, 'action');
        $this->assertSame($nonce, $verified);
    }

    public function testGetSplitVerifiedUsesCustomDelimiter(): void
    {
        $nonce = $this->service->create(1, 'action', 3600);
        $this->service->store[] = $nonce;

        $complete = $nonce->getCompleteNonce('|');
        $verified = $this->service->getSplitVerified($complete, 'action', '|');
        $this->assertSame($nonce, $verified);
    }
}
