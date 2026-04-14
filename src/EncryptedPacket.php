<?php

namespace Sterzik\ModStamp;

use Socket;

class EncryptedPacket
{
    public static function readFromSocket(Socket $socket): ?self
    {
        $from = '';
        $port = 0;
        if (socket_recvfrom($socket, $buf, 65535, 0, $from, $port) === false) {
            return null;
        }
        $packet = new self($from, $port, $buf);
    }

    public function __construct(private string $clientHost, private string $clientPort, private string $data)
    {
    }

    public function getClientHost(): string
    {
        return $this->clientHost;
    }

    public function getClientPort(): string
    {
        return $this->clientPort;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function writeToSocket(Socket $socket): void
    {
        socket_sendto($socket, $this->data, strlen($this->data), 0, $this->clientHost, $this->clientPort);
    }
}
