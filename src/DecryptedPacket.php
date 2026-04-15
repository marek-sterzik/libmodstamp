<?php

namespace Sterzik\ModStamp;

class DecryptedPacket
{
    public function __construct(private Peer $peer, private string $data)
    {
    }

    public function getPeer(): Peer
    {
        return $this->peer;
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
