<?php
/**
 * Copyright (c) 2020 Xibo Signage Ltd
 */

namespace Xibo\Support\Exception;

class DuplicateEntityException extends GeneralException
{
    protected $property;
    protected $help;

    /**
     * InvalidArgumentException constructor.
     * @param string $message
     * @param string $property
     * @param string $help
     */
    public function __construct($message = '', $property = null, $help = null)
    {
        $this->property = $property;
        $this->help = $help;

        parent::__construct($message, 409, null);
    }

    /**
     * @return int
     */
    public function getHttpStatusCode()
    {
        return 409;
    }

    /**
     * @return array
     */
    protected function getErrorData()
    {
        return [
            'property' => $this->property,
            'help' => $this->help
        ];
    }
}