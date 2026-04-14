<?php

namespace Sterzik\ModStamp;

class EncryptedPacket
{
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
}
