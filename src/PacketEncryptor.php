<?php

namespace Sterzik\ModStamp;

use ReflectionClass;
use Sterzik\ModStamp\Encryptor\AbstractEncryptor;

class PacketEncryptor
{
    private array $encryptors = [];

    public function __construct(Keyring $keyring)
    {
        foreach ($this->listEncryptors() as $class) {
            if ($keyring->isConfigured($class)) {
                $encryptor = new $class($keyring);
                foreach ($class::getSignatures() as $signature) {
                    $this->encryptors[$signature] = $encryptor;
                }
            }
        }
    }

    private function listEncryptors(): array
    {
        $encryptors = [];
        foreach (glob(__DIR__ . "/Encryptor/*.php") as $file) {
            $class = "Sterzik\\ModStamp\\Encryptor\\" . basename($file, ".php");
            if (class_exists($class) && is_a($class, AbstractEncryptor::class, true)) {
                $rc = new ReflectionClass($class);
                if ($rc->isAbstract()) {
                    continue;
                }
                $encryptors[] = $class;
            }
        }
        return $encryptors;
    }

    public function encryptPacket(DecryptedPacket $packet): ?EncryptedPacket
    {
        $peer = $packet->getPeer();
        $encryptionInfo = $peer->getEncryptionInfo();
        if (strlen($encryptionInfo) > 255) {
            return null;
        }

        $encryptionInfoHeader = chr(strlen($encryptionInfo)) . $encryptionInfo;

        $encryptedData = $this->encryptData($packet->getData(), $peer->getHost(), $encryptionInfo);

        if ($encryptedData === null) {
            return null;
        }

        $encryptedData = $encryptionInfoHeader . $encryptedData;

        return new EncryptedPacket($peer->getHost(), $peer->getPort(), $encryptedData);
    }

    public function decryptPacket(EncryptedPacket $packet): ?DecryptedPacket
    {
        $peerHost = $packet->getPeerHost();
        $data = $packet->getData();
        $reader = new StringReader($data);
        $encryptionInfo = $reader->readVarString();
        if ($encryptionInfo === null) {
            return null;
        }

        $decryptedData = $this->decryptData($reader->getRestOfString(), $peerHost, $encryptionInfo);

        if ($decryptedData === null) {
            return null;
        }

        $permissions = $this->assignPermissions($packet->getPeerHost(), $encryptionInfo);
        
        $peer = new Peer($packet->getPeerHost(), $packet->getPeerPort(), $permissions, $encryptionInfo);


        return new DecryptedPacket($peer, $decryptedData);
    }

    public function getHeaderSizeForPeer(Peer $peer): int
    {
        $encryptionInfo = $peer->getEncryptionInfo();
        return 1 + strlen($encryptionInfo) + $this->getHeaderSizeForEncryption($peer->getHost(), $encryptionInfo);
    }

    private function decryptData(string $data, string $peerHost, string $encryptionInfo): ?string
    {
        return $this->getEncryptor($peerHost, $encryptionInfo)?->decryptData($data);
    }

    private function encryptData(string $data, string $peerHost, string $encryptionInfo): ?string
    {
        return $this->getEncryptor($peerHost, $encryptionInfo)?->encryptData($data);
    }

    private function getHeaderSizeForEncryption(string $peerHost, string $encryptionInfo): int
    {
        return $this->getEncryptor($peerHost, $encryptionInfo)?->getPacketHeaderSize() ?? 0;
    }

    private function assignPermissions(string $peerHost, string $encryptionInfo): Permissions
    {
        return $this->getEncryptor($peerHost, $encryptionInfo)?->getPermissions() ?? Permissions::None;
    }

    private function getEncryptor(string $peerHost, string $encryptionInfo): AbstractEncryptor
    {
        if ($encryptionInfo === "") {
            $encryptor = $this->encryptors[""] ?? null;
            $param = "";
        } else {
            $encryptor = $this->encryptors[substr($encryptionInfo, 0, 1)];
            $param = substr($encryptionInfo, 1);
        }
        return $encryptor?->setup($peerHost, $param);
    }
}
