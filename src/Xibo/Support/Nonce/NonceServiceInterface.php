<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Nonce;


interface NonceServiceInterface
{
    /**
     * Create a nonce
     * @param $entityId
     * @param $action
     * @param $timeOut
     * @return \Xibo\Support\Nonce\Nonce
     */
    public function create($entityId, $action, $timeOut);

    /**
     * Verify a Nonce
     * @param string $nonce
     * @param string $lookup
     * @param string $action
     * @return \Xibo\Support\Nonce\Nonce
     * @throws \Xibo\Support\Exception\InvalidNonceException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getVerified($nonce, $lookup, $action);

    /**
     * Get Nonce
     *  we look up nonce by their lookup, if we find 0 or >1 then we throw a NotFoundException
     * @param string $lookup The lookup associated with the nonce
     * @return \Xibo\Support\Nonce\Nonce
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function get($lookup);

    /**
     * Persist a nonce
     * @param \Xibo\Support\Nonce\Nonce $nonce
     * @return mixed
     */
    public function persist($nonce);

    /**
     * Remove a nonce
     * @param \Xibo\Support\Nonce\Nonce $nonce
     */
    public function remove($nonce);

    /**
     * Delete all nonces for an entity
     * @param $action
     * @param $entityId
     */
    public function removeAllForEntity($entityId, $action);
}