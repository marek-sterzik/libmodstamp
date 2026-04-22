<?php

namespace Sterzik\ModStamp;

class ServerConfig
{
    private bool $ipv6 = true;
    private ?string $listenIp = null;
    private int $listenPort = 1415;

    private int $maxPacketSize = 1300;
    private int $broadcastRepeat = 1;

    private ?string $redisHost = null;
    private int $redisPort = 6379;
    private array $redisConfig = [];

    private ?string $modstampDatabaseFile = null;

    private ?int $processes = 1;

    private int $cacheExpirationSec = 300;
    
    private array $encryptionConfig = [];


    public function setIPv4(): self
    {
        $this->ipv6 = false;
        return $this;
    }

    public function setIPv6(): self
    {
        $this->ipv6 = true;
        return $this;
    }

    public function isIPv6(): bool
    {
        return $this->ipv6;
    }

    public function setListenIp(?string $listenIp): self
    {
        $this->listenIp = $listenIp;
        return $this;
    }

    public function getListenIp(): string
    {
        return $this->listenIp ?? ($this->ipv6 ? '::' : '0.0.0.0');
    }

    public function setListenPort(int $listenPort): self
    {
        $this->listenPort = $listenPort;
        return $this;
    }

    public function getListenPort(): int
    {
        return $this->listenPort;
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

    public function setBroadcastRepeat(int $broadcastRepeat): self
    {
        $this->broadcastRepeat = $broadcastRepeat;
        return $this;
    }

    public function getBroadcastRepeat(): int
    {
        return $this->broadcastRepeat;
    }

    public function setModstampDatabaseFile(?string $file): self
    {
        $this->modstampDatabaseFile = $file;
        return $this;
    }

    public function getModstampDatabaseFile(): string
    {
        return $this->modstampDatabaseFile ?? tempnam("/tmp", "modstamp.db");
    }

    public function setMultiProcess(?int $processes = null): self
    {
        $this->processes = $processes;
        return $this;
    }

    public function setSingleProcess(): self
    {
        $this->processes = 1;
        return $this;
    }

    public function getProcesses(): int
    {
        return $this->processes ?? CPUCoreDetector::detectNumberOfCores();
    }

    public function getEncryptionConfig(): array
    {
        return $this->encryptionConfig;
    }

    public function setMemoryCache(): self
    {
        $this->redisHost = null;
        return $this;
    }

    public function setRedisHost(string $redisHost): self
    {
        $this->redisHost = $redisHost;
        return $this;
    }

    public function setRedisPort(int $redisPort): self
    {
        $this->redisPort = $redisPort;
        return $this;
    }

    public function setRedisConfig(array $redisConfig): self
    {
        $this->redisConfig = $redisConfig;
        return $this;
    }

    public function getRedisConfig(): ?array
    {
        if ($this->redisHost === null) {
            return null;
        } else {
            return array_merge($this->redisConfig, ["host" => $this->redisHost, "port" => $this->redisPort]);
        }
    }

    public function getCacheExpirationSec(): int
    {
        return $this->cacheExprirationSec;
    }

    public function setCacheExpirationSec(int $cacheExpirationSec): self
    {
        $this->cacheExprirationSec = $cacheExpirationSec;
        return $this;
    }
}

