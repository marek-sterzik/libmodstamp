<?php

namespace Sterzik\ModStamp;

use Sterzik\ModStamp\Encryptor\AbstractEncryptor;

class Keyring
{
    public function __construct(array $encryptionConfig)
    {
    }

    public function isConfigured(string $encryptorClass): bool
    {
        return true;
    }

    public function getDefaultEncryptionInfo(): string
    {
        return "";
    }
}
