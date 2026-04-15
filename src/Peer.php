<?php

namespace Sterzik\ModStamp;

class Peer
{
    public static function fromData(array $data): self
    {
        return new self(
            $data['host'],
            $data['port'],
            Permissions::tryFrom($data['perm']) ?? Permissions::None,
            $data['enc'],
        );
    }

    public function __construct(
        private string $host,
        private int $port,
        private Permissions $permissions,
        private string $encryptionInfo
    ) {
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getPermissions(): Permissions
    {
        return $this->permissions;
    }

    public function getEncryptionInfo(): string
    {
        return $this->encryptionInfo;
    }

    public function getId(): string
    {
        return sprintf("%s:%d", $this->host, $this->port);
    }

    public function getData(): array
    {
        return [
            "host" => $this->host,
            "port" => $this->port,
            "perm" => $this->permissions->value,
            "enc" => $this->encryptionInfo,
        ];
    }
}

