<?php

namespace Xibo\Support\Tests\Fixtures;

use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Nonce\Nonce;
use Xibo\Support\Nonce\NonceService;

class ConcreteNonceService extends NonceService
{
    public array $store = [];

    public function get($lookup): Nonce
    {
        $matches = array_filter($this->store, fn($n) => $n->lookup === $lookup);
        if (count($matches) !== 1) {
            throw new NotFoundException('Nonce not found');
        }
        return array_values($matches)[0];
    }

    public function persist($nonce): void
    {
        $this->store[] = $nonce;
    }

    public function remove($nonce): void
    {
        $this->store = array_filter(
            $this->store,
            fn($n) => $n->lookup !== $nonce->lookup
        );
    }

    public function removeAllForEntity($entityId, $action): void
    {
        $this->store = array_filter(
            $this->store,
            fn($n) => !($n->entityId === $entityId && $n->action === $action)
        );
    }
}
