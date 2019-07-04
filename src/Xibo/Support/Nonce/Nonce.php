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

    /**
     * A hashed version of the nonce to be persisted
     * @var string
     */
    private $hashed;

    /**
     * Nonce constructor.
     * @param string $json JSON representing the Nonce to create
     */
    public function __construct($json = null)
    {
        if ($json !== null) {
            if (is_string($json)) {
                $json = json_decode($json);
            }

            if (is_array($json)) {
                $this->entityId = $json['entityId'];
                $this->hashed = $json['hashed'];
                $this->lookup = $json['lookup'];
                $this->action = $json['action'];
                $this->expires = $json['expires'];
            }
        }

        if ($this->hashed == null) {
            $nonce = bin2hex(random_bytes(20));
            $hashedNonce = password_hash($nonce, PASSWORD_DEFAULT);

            $this->hashed = $hashedNonce;
            $this->nonce = $nonce;
            $this->lookup = bin2hex(random_bytes(10));
        }
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
        if (!password_verify($nonce, $this->nonce)) {
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
        return json_encode([
            'entityId' => $this->entityId,
            'hashed' => $this->hashed,
            'lookup' => $this->lookup,
            'action' => $this->action,
            'expires' => $this->expires
        ]);
    }
}