<?php

namespace Sterzik\ModStamp;

class ClientConfig
{
    private string $host;
    private int $port = 1415;

    private int $maxPacketSize = 1300;

    private array $encryptionConfig = null;

    public function getEncryptionConfig(): array
    {
        return isset($this->encryptionConfig) ? [$this->encryptionConfig] : [];
    }
}
