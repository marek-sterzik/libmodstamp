<?php

namespace Sterzik\ModStamp;

use Sterzik\ModStamp\Encryptor\AbstractEncryptor;

class PacketEncryptor
{
    public function __construct(private SecurityProfile $securityProfile)
    {
    }

    public function encryptPacket(DecryptedPacket $packet): ?EncryptedPacket
    {
        $peer = $packet->getPeer();
        $encryptorId = $peer->getEncryptorId();

        $encryptor = $this->securityProfile->getEncryptor($encryptorId);

        if ($encryptor === null) {
            return null;
        }

        $encryptionInfo = $encryptor->getEncryptionInfo();

        if (strlen($encryptionInfo) > 255) {
            return null;
        }

        $encryptionInfoHeader = chr(strlen($encryptionInfo)) . $encryptionInfo;

        $encryptedData = $encryptor->encryptData($packet->getData());

        if ($encryptedData === null) {
            return null;
        }

        return new EncryptedPacket($peer->getHost(), $peer->getPort(), $encryptionInfoHeader . $encryptedData);
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

        $encryptedData = $reader->getRestOfString();

        foreach ($this->securityProfile->matchEncryptors($peerHost, $encryptionInfo) as $encryptorId) {
            $encryptor = $this->securityProfile->getEncryptor($encryptorId);
            if ($encryptor === null) {
                Log::log(Log::DBG2, "not decrypting packet using profile with id=$encryptorId, cannot find encryptor");
                continue;
            }
            $decryptedData = $encryptor->decryptData($encryptedData);
            if ($decryptedData === null) {
                Log::log(Log::DBG2, "not decrypting packet using profile with id=$encryptorId, decryption failed");
                continue;
            }
            Log::log(Log::DBG, "packet decrypted using profile with id=$encryptorId");
            $permissions = $encryptor->getPermissions();
            $peer = new Peer($packet->getPeerHost(), $packet->getPeerPort(), $permissions, $encryptorId);
            return new DecryptedPacket($peer, $decryptedData);
        }
        Log::log(Log::DBG, "not decrypting packet, no suitable encryptor found");

        return null;
    }

    public function getHeaderSizeForPeer(Peer $peer): int
    {
        $encryptor = $this->securityProfile->getEncryptor($peer->getEncryptorId());
        if ($encryptor === null) {
            return 0;
        }

        $encryptionInfo = $encryptor->getEncryptionInfo();

        return 1 + strlen($encryptionInfo) + $encryptor->getPacketHeaderSize();
    }
}
