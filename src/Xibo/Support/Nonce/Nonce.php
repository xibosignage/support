<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Nonce;

/**
 * Class Nonce
 * @package Xibo\Support\Nonce
 */
class Nonce
{
    public $nonceId;
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
     */
    public function __construct()
    {
        $nonce = bin2hex(random_bytes(20));
        $hashedNonce = password_hash($nonce, PASSWORD_DEFAULT);

        $this->hashed = $hashedNonce;
        $this->nonce = $nonce;
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
}