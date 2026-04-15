<?php

namespace Sterzik\ModStamp\Encryptor;

use Sterzik\ModStamp\Permissions;
use Sterzik\ModStamp\Keyring;

class PlainEncryptor extends AbstractEncryptor
{
    public static function getSignatures(): array
    {
        return ['', 'p'];
    }

    public static function getIdentifier(): string
    {
        return "plain";
    }

    public function encryptData(string $data): ?string
    {
        return $data;
    }

    public function decryptData(string $data): ?string
    {
        return $data;
    }

    public function getPacketHeaderSize(): int
    {
        return 0;
    }

    public function isProtected(): bool
    {
        return false;
    }
}
