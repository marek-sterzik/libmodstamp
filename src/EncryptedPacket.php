<?php

namespace Sterzik\ModStamp;

use Socket;

class EncryptedPacket
{
    public static function readFromSocket(Socket $socket, ?int $timeoutMiliseconds = null): ?self
    {
        if ($timeoutMiliseconds !== null) {
            if ($timeoutMiliseconds < 1) {
                $timeoutMiliseconds = 1;
            }
            $seconds = intdiv($timeoutMiliseconds, 1000);
            $microseconds = ($timeoutMiliseconds % 1000) * 1000;
            $r = [$socket];
            $w = [];
            $e = [];
            $result = socket_select($r, $w, $e, $seconds, $microseconds);
            if ($result === false || $result === 0) {
                return null;
            }
        }
        $from = '';
        $port = 0;
        if (socket_recvfrom($socket, $buf, 65535, 0, $from, $port) === false) {
            return null;
        }
        return new self($from, $port, $buf);
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
