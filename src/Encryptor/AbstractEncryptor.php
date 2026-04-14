<?php

namespace Sterzik\ModStamp\Encryptror;

use Sterzik\ModStamp\Permissions;
use Sterzik\ModStamp\Keyring;

class AbstractEncryptor
{
    public static function getSignatures(): array;
    public function encryptData(string $data, string $param): ?string;
    public function decryptData(string $data, string $param): ?string;
    public function getPacketHeaderSize(string $param): int;

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
