<?php

namespace Sterzik\ModStamp\Encryptror;

use Sterzik\ModStamp\Permissions;
use Sterzik\ModStamp\Keyring;

class PlainEncryptor extends AbstractEncryptor
{
    public static function getSignatures(): array
    {
        return ['', 'p'];
    }

    public function encryptData(string $data, string $param): ?string
    {
        return $data;
    }

    public function decryptData(string $data, string $param): ?string
    {
        return $data;
    }

    public function getPacketHeaderSize(string $param): int
    {
        return 0;
    }
}
