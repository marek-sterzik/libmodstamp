<?php

namespace Sterzik\ModStamp;

class Keyring
{
    public function isConfigured(string $encryptorClass): bool
    {
        return true;
    }

    public function getPermissionsFor(AbstractEncryptor $encryptor, string $param): Permissions
    {
        return Permissions::ReadWrite;
    }
}
