<?php

namespace Sterzik\ModStamp;

use Socket;

class EncryptedPacket
{
    public static function readFromSocket(Socket $socket, ?int $timeoutMilliseconds = null): ?self
    {
        $data = SocketReader::readFromSocket($socket, $timeoutMilliseconds);
        if ($data === null) {
            return null;
        }
        return new self($data['from'], $data['port'], $data['buf']);
    }

    public function __construct(private string $peerHost, private string $peerPort, private string $data)
    {
    }

    public function getPeerHost(): string
    {
        return $this->peerHost;
    }

    public function getPeerPort(): string
    {
        return $this->peerPort;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function writeToSocket(Socket $socket): void
    {
        socket_sendto($socket, $this->data, strlen($this->data), 0, $this->peerHost, $this->peerPort);
    }
}
