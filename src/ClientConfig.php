<?php

namespace Sterzik\ModStamp;

class ClientConfig
{
    private bool $ipv6 = false;

    private int $port = 1415;

    private int $maxPacketSize = 1300;

    private int $sendRepeat = 1;

    private ?array $encryptionConfig = null;

    public function __construct(private string $host)
    {
    }

    public function setIPv4(): self
    {
        $tihs->ipv6 = false;
        return $this;
    }

    public function setIPv6(): self
    {
        $tihs->ipv6 = true;
        return $this;
    }


    public function isIPv6(): bool
    {
        return $this->ipv6;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setMaxPacketSize(int $maxPacketSize): self
    {
        $this->maxPacketSize = $maxPacketSize;
        return $this;
    }

    public function getMaxPacketSize(): int
    {
        return $this->maxPacketSize;
    }

    public function setSendRepeat(int $sendRepeat): self
    {
        $this->sendRepeat = $sendRepeat;
        return $this;
    }

    public function getSendRepeat(): int
    {
        return $this->sendRepeat;
    }

    public function getEncryptionConfig(): array
    {
        return isset($this->encryptionConfig) ? [$this->encryptionConfig] : [];
    }
}
