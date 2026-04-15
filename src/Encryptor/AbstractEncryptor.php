<?php

namespace Sterzik\ModStamp\Encryptor;

use Sterzik\ModStamp\Permissions;
use Sterzik\ModStamp\Keyring;

abstract class AbstractEncryptor
{
    abstract public static function getSignatures(): array;
    abstract public function encryptData(string $data): ?string;
    abstract public function decryptData(string $data): ?string;
    abstract public function getPacketHeaderSize(): int;

    public function __construct(private Keyring $keyring)
    {
    }

    public function setup(string $peerHost, string $param): self
    {
        return $this;
    }

    protected function getKeyring(): Keyring
    {
        return $this->keyring;
    }

    public function getPermissions(): Permissions
    {
        return Permissions::ReadWrite;
    }
}
