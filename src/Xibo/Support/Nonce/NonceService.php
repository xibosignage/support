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
    public final function create($entityId, $action, $timeOut)
    {
        $nonce = new Nonce();
        $nonce->entityId = $entityId;
        $nonce->action = $action;
        $nonce->expires = time() + $timeOut;
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
}