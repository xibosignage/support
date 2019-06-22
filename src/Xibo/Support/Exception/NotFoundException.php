<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Exception;


class NotFoundException extends GeneralException
{
    protected $property;
    protected $help;

    /**
     * InvalidArgumentException constructor.
     * @param string $message
     * @param string $property
     * @param string $help
     */
    public function __construct($message = 'Not Found', $property = null, $help = null)
    {
        $this->property = $property;
        $this->help = $help;

        parent::__construct($message, 404, null);
    }

    /**
     * @return int
     */
    public function getHttpStatusCode()
    {
        return 404;
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