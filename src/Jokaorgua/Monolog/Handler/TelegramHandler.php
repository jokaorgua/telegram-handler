<?php

namespace Jokaorgua\Monolog\Handler;

use Monolog\Handler\MissingExtensionException;
use Monolog\Handler\SocketHandler;
use Monolog\Logger;

/**
 * Sends notifications through Telegram API.
 *
 * @author Rafael Mello <merorafael@gmail.com>
 *
 * @see    https://core.telegram.org/bots/api
 */
class TelegramHandler extends SocketHandler
{
    /**
     * @var string Telegram API token
     */
    private $token;

    /**
     * @var int Chat identifier
     */
    private $chatId;

    /**
     * @var int How long we have to sleep between messages to avoid hitting telegram api limits
     */
    private $sleepTimeBetweenMessages = 1;
    /**
     * @param string $token  Telegram API token
     * @param int    $chatId Chat identifier
     * @param int    $level  The minimum logging level at which this handler will be triggered
     * @param bool   $bubble Whether the messages that are handled can bubble up the stack or not
     *
     * @throws MissingExtensionException If no OpenSSL PHP extension configured
     */
    public function __construct($token, $chatId, $level = Logger::CRITICAL, $bubble = true, $sleepTimeBetweenMessages = 1)
    {
        if (!extension_loaded('openssl')) {
            throw new MissingExtensionException('The OpenSSL PHP extension is required to use the TelegramHandler');
        }

        parent::__construct('ssl://api.telegram.org:443', $level, $bubble);

        $this->token = $token;
        $this->chatId = $chatId;
        $this->sleepTimeBetweenMessages = $sleepTimeBetweenMessages;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $record
     *
     * @return string
     */
    protected function generateDataStream($record)
    {
        $content = $this->buildContent($record);

        return $this->buildHeader($content).$content;
    }

    /**
     * Builds the body of API call.
     *
     * @param array $record
     *
     * @return string
     */
    private function buildContent($record)
    {
        $dataArray = [
            'chat_id' => $this->chatId,
            'text' => $record['formatted'],
            'parse_mode' => 'HTML'
        ];

        return json_encode($dataArray);
    }

    /**
     * Builds the header of the API Call.
     *
     * @param string $content
     *
     * @return string
     */
    private function buildHeader($content)
    {
        $header = "POST /bot{$this->token}/sendMessage HTTP/1.1\r\n";

        $header .= "Host: api.telegram.org\r\n";
        $header .= "Content-Type: application/json\r\n";
        $header .= 'Content-Length: '.strlen($content)."\r\n";
        $header .= "\r\n";

        return $header;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $record
     */
    protected function write(array $record)
    {
        parent::write($record);
        sleep($this->sleepTimeBetweenMessages);
        $this->closeSocket();
    }
}
