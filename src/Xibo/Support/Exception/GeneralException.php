<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Exception;


use Psr\Http\Message\ResponseInterface;

class GeneralException extends \Exception
{
    /** @var  int */
    private $httpStatusCode = 500;

    /**
     * Returns the HTTP status code to send when the exceptions is output.
     *
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    /**
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function generateHttpResponse(ResponseInterface $response)
    {
        $payload = [
            'error'   => $this->getCode(),
            'message' => $this->getMessage(),
        ];

        $payload = array_merge($payload, $this->getErrorData());

        $response->getBody()->write(json_encode($payload));
        $response = $response->withAddedHeader('Content-Type', 'application/json');

        return $response->withStatus($this->getHttpStatusCode());
    }

    /**
     * @return array
     */
    protected function getErrorData()
    {
        return [];
    }
}