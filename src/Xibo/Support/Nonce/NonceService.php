<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Nonce;


use Xibo\Support\Exception\InvalidNonceException;

/**
 * Class NonceService
 * @package Xibo\Support\Nonce
 */
abstract class NonceService implements NonceServiceInterface
{
    /** @inheritDoc */
    public final function create($entityId, $action, $timeOut, $nonceLength = 20, $lookupLength = 10)
    {
        $nonce = (new Nonce())->setNonce($nonceLength, $lookupLength);
        $nonce->entityId = $entityId;
        $nonce->action = $action;
        $nonce->expires = time() + $timeOut;
        $nonce->meta = [];
        return $nonce;
    }

    /** @inheritDoc */
    public final function hydrate($json)
    {
        $nonce = new Nonce();

        if (is_string($json)) {
            $json = json_decode($json, true);
        }

        if (is_array($json)) {
            $nonce->entityId = $json['entityId'];
            $nonce->lookup = $json['lookup'];
            $nonce->action = $json['action'];
            $nonce->expires = $json['expires'];
            $nonce->meta = $json['meta'] ?? [];
            $nonce->setHashed($json['hashed']);
        }

        return $nonce;
    }

    /** @inheritDoc */
    public final function getVerified($nonce, $lookup, $action)
    {
        $verifyNonce = $this->get($lookup);

        // Verify the nonce
        if (!$verifyNonce->verify($nonce)) {
            throw new InvalidNonceException();
        }

        // Verify the action
        if (!$verifyNonce->action === $action) {
            throw new InvalidNonceException();
        }

        return $verifyNonce;
    }

    /** @inheritDoc */
    public final function getSplitVerified($nonce, $action, $delimiter = ':::')
    {
        $parts = explode(':::', $nonce);
        return $this->getVerified($parts[0], $parts[1], $action);
    }
}