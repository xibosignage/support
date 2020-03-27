<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Exception;


class AuthenticationRequiredException extends GeneralException
{
    public function getHttpStatusCode()
    {
        return 401;
    }
}