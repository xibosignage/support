<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Exception;


class InvalidArgumentException extends GeneralException
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

        if (empty($message)) {
            $message = 'Invalid Argument';
            if (!empty($property)) {
                $message .= ' ' . $property;
            }
        }

        parent::__construct($message, 422, null);
    }

    /**
     * @return int
     */
    public function getHttpStatusCode()
    {
        return 422;
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
