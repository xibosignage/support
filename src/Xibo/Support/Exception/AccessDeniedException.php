<?php
/**
 * Copyright (c) 2020 Xibo Signage Ltd
 */

namespace Xibo\Support\Exception;


class AccessDeniedException extends GeneralException
{
    protected $help;

    /**
     * AccessDeniedException constructor.
     * @param string $message
     * @param string $help
     */
    public function __construct($message = '', $help = null)
    {
        $this->help = $help;

        parent::__construct($message, 403, null);
    }

    public function getHttpStatusCode()
    {
        return 403;
    }

    /**
     * @return array
     */
    protected function getErrorData()
    {
        return [
            'help' => $this->help
        ];
    }
}