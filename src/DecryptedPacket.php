<?php

namespace Sterzik\ModStamp;

class DecryptedPacket
{
    public function __construct(private Client $client, private string $data)
    {
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getMessages(): ?array
    {
        return MessageEncoder::decodeMessages($this->data);
    }
}
