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
     * @param int $nonceLength The length of the nonce, default to 20 characters
     * @param int $lookupLength The length of the lookup, default to 10 characters
     * @return \Xibo\Support\Nonce\Nonce
     * @throws \Exception
     */
    public function create($entityId, $action, $timeOut, $nonceLength = 20, $lookupLength = 10);

    /**
     * Hydrate a nonce with JSON/Array
     * @param string|array $json
     * @return \Xibo\Support\Nonce\Nonce
     */
    public function hydrate($json);

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
     * Get a nonce, split it and verify
     * @param $nonce
     * @param $action
     * @param string $delimiter
     * @return \Xibo\Support\Nonce\Nonce
     * @throws \Xibo\Support\Exception\InvalidNonceException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getSplitVerified($nonce, $action, $delimiter = ':::');

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