<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Nonce;

use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Nonce
 * @package Xibo\Support\Nonce
 */
class Nonce implements \JsonSerializable
{
    public $entityId;
    public $nonce;
    public $action;
    public $expires;
    public $lookup;
    public $meta;

    /**
     * A hashed version of the nonce to be persisted
     * @var string
     */
    private $hashed;

    /**
     * Set the nonce
     * @param int $nonceLength
     * @param int $lookupLength
     * @return $this
     * @throws \Exception
     */
    public function setNonce($nonceLength = 20, $lookupLength = 10)
    {
        if ($this->hashed == null) {
            $nonce = bin2hex(random_bytes($nonceLength));
            $hashedNonce = password_hash($nonce, PASSWORD_DEFAULT);

            $this->hashed = $hashedNonce;
            $this->nonce = $nonce;
            $this->lookup = bin2hex(random_bytes($lookupLength));
        }

        return $this;
    }

    /**
     * Gets the nonce and lookup joined
     * @param string $delimiter
     * @return string
     */
    public function getCompleteNonce($delimiter = ':::')
    {
        return $this->nonce . $delimiter . $this->lookup;
    }

    /**
     * Get the hashed copy of the nonce for storage
     * @return mixed
     */
    public function getHashed()
    {
        return $this->hashed;
    }

    /**
     * @param string $hashed The hashed nonce, usually set when hydrating from existing data
     * @return $this
     */
    public function setHashed($hashed)
    {
        $this->hashed = $hashed;
        return $this;
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return $this->nonce;
    }

    /**
     * Verify a nonce
     * @param string $nonce The nonce to verify (this will be the un-hashed nonce which we will compare to the hashed
     *                      one which we persisted.)
     * @return bool
     */
    public function verify($nonce)
    {
        if (!password_verify($nonce, $this->hashed)) {
            return false;
        }

        // Check to see if the expires time is less than the current time
        return ($this->expires >= time());
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'entityId' => $this->entityId,
            'hashed' => $this->hashed,
            'lookup' => $this->lookup,
            'action' => $this->action,
            'expires' => $this->expires,
            'meta' => $this->meta
        ];
    }
}