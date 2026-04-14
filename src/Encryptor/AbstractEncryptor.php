<?php

namespace Sterzik\ModStamp\Encryptor;

use Sterzik\ModStamp\Permissions;
use Sterzik\ModStamp\Keyring;

abstract class AbstractEncryptor
{
    abstract public static function getSignatures(): array;
    abstract public function encryptData(string $data, string $param): ?string;
    abstract public function decryptData(string $data, string $param): ?string;
    abstract public function getPacketHeaderSize(string $param): int;

    public function __construct(private Keyring $keyring)
    {
    }

    protected function getKeyring(): Keyring
    {
        return $this->keyring;
    }

    public function getPermissions(string $param): Permissions
    {
        return $this->getKeyring()->getPermissionsFor($this, $param);
    }
}
