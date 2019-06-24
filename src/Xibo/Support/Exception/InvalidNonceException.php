<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Exception;


class InvalidNonceException extends GeneralException
{
    public function __construct($message = "Token Expired", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}