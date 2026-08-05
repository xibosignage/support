<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Monolog\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class ProxyIpProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['clientIp'] = self::getIp();

        return $record;
    }

    /**
     * Get the IP address
     * @return string
     */
    public static function getIp()
    {
        $clientIp = null;

        $keys = ['X_FORWARDED_FOR', 'HTTP_X_FORWARDED_FOR', 'CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (isset($_SERVER[$key])) {
                $clientIp = $_SERVER[$key];
                break;
            }
        }

        return $clientIp;
    }
}