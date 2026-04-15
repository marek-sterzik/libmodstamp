<?php

namespace Sterzik\ModStamp\Encryptor;

use Exception;
use Sterzik\ModStamp\Permissions;
use Sterzik\ModStamp\Keyring;

abstract class AbstractEncryptor
{
    abstract public static function getSignatures(): array;
    abstract public static function getIdentifier(): string;
    abstract public function encryptData(string $data): ?string;
    abstract public function decryptData(string $data): ?string;
    abstract public function getPacketHeaderSize(): int;
    abstract public function isProtected(): bool;

    public function __construct(private array $config)
    {
    }

    public function matchSignature(string $signature): bool
    {
        foreach (static::getSignatures() as $sig) {
            if ($signature === $sig) {
                return true;
            }
        }
        return false;
    }

    public function getEncryptionInfo(): string
    {
        $param = $this->config['id'] ?? '';
        $foundSignature = null;
        $foundEmpty = false;
        foreach (static::getSignatures() as $signature) {
            if ($param === "" || $signature !== "") {
                return $signature . $param;
            }
        }
        throw new Exception("Cannot generate encryption info (a bug occured?)");
    }

    public function getPermissions(): Permissions
    {
        $permission = $this->config['permission'] ?? null;

        if ($permission === null) {
            return $this->isProtected() ? Permissions::ReadWrite : Permissions::Read;
        }
        $permission = Permissions::tryFrom($permission);
        if ($permission === null) {
            return Permission::None;
        }
        return $permission;
    }
}
