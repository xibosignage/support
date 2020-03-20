<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Monolog\Handler;


use GuzzleHttp\Client;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class RocketChatHandler extends AbstractProcessingHandler
{
    /** @var string URL for Rocket.Chat inbound web hook */
    private $url;

    /** @var Client */
    private $client;

    /**
     * RocketChatHandler constructor.
     * @param string $url
     * @param Client|null $client
     * @param int $level
     * @param bool $bubble
     */
    public function __construct($url, $client = null, $level = Logger::CRITICAL, $bubble = true)
    {
        $this->url = $url;

        if ($client === null) {
            $this->client = new Client();
        } else {
            $this->client = $client;
        }

        parent::__construct($level, $bubble);
    }

    /** @inheritdoc */
    protected function write(array $record)
    {
        $formattedMessage = sprintf(
            "Log channel: *%s*\nLog level: *%s*\n```%s```",
            $record['channel'], $record['level_name'], $record['message']
        );

        $this->client->request('POST', $this->url, [
            'json' => [
                'text' => $formattedMessage,
                'attachments' => [
                    'color' => $this->getAlertColor($record['level']),
                ]
            ]
        ]);
    }

    /**
     * Assigns a color to each level of log records.
     *
     * @param  int $level
     * @return string
     */
    private function getAlertColor($level)
    {
        switch (true) {
            case $level >= Logger::ERROR:
                return '#f44b42';
            case $level >= Logger::WARNING:
                return '#f4eb42';
            case $level >= Logger::INFO:
                return '#42f44e';
            case $level == Logger::DEBUG:
                return '#848484';
            default:
                return '#3c55e0';
        }
    }
}