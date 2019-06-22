<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Exception;


class AccessDeniedException extends GeneralException
{
    public function getHttpStatusCode()
    {
        return 401;
    }
}