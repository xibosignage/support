<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Nonce;

use Xibo\Support\Exception\InvalidNonceException;

class CsrfMiddleware
{
    /**
     * CSRF token key name.
     * @var string
     */
    protected $key;

    /**
     * Constructor.
     * @param string $key The CSRF token key name.
     */
    public function __construct($key = 'csrfToken')
    {
        if (! is_string($key) || empty($key) || preg_match('/[^a-zA-Z0-9\-\_]/', $key)) {
            throw new \OutOfBoundsException('Invalid CSRF token key "' . $key . '"');
        }

        $this->key = $key;
    }

    /**
     * invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function __invoke($request, $response, $next)
    {
        // Set a CSRF key if we don't have one already
        if (!isset($_SESSION[$this->key])) {
            $_SESSION[$this->key] = bin2hex(random_bytes(20));
        }

        // Always test CSRF for methods that can update data
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'])) {
            $userToken = null;

            // See if we have a header
            if ($request->hasHeader('X-XSRF-TOKEN')) {
                $userTokens = $request->getHeader('X-XSRF-TOKEN');

                if (count($userTokens) > 0) {
                    $userToken = $userTokens[0];
                }
            }

            // See if we have a param
            $body = $request->getParsedBody();
            if (is_array($body) && array_key_exists($this->key, $body)) {
                $userToken = $body[$this->key];
            }

            // Check it matches
            if ($userToken === null || $userToken !== $_SESSION[$this->key]) {
                throw new InvalidNonceException();
            }
        }

        // Pass along so that we can add to the view layer
        $request = $request
            ->withAttribute('csrfKey', $this->key)
            ->withAttribute('csrfToken', $_SESSION[$this->key]);

        // Next middleware
        $response = $next($request, $response);

        return $response;
    }
}