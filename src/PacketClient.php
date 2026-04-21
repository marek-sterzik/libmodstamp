<?php

namespace Sterzik\ModStamp;

use Sterzik\ModStamp\Storage\ServerStorage;
use Sterzik\modStamp\Message\AbstractMessage;

class PacketClient
{
    public function __construct(
        private Peer $server,
        private PacketEncryptor $packetEncryptor,
        private int $maxPacketSize = 1000
    ) {
    }

    public function messagesToPackets(array $messages): array
    {
        $maxPacketSize = $this->maxPacketSize - $this->packetEncryptor->getHeaderSizeForPeer($this->server);
        $packets = [];
        $currentPacket = '';

        foreach ($messages as $message) {
            $encodedMessage = $message->encode();
            if ($encodedMessage !== null) {
                if ($currentPacket !== '' && strlen($currentPacket) + strlen($encodedMessage) > $maxPacketSize) {
                    $packets[] = $currentPacket;
                    $currentPacket = '';
                }
                $currentPacket .= $encodedMessage;
            }
        }
        if ($currentPacket !== '') {
            $packets[] = $currentPacket;
        }


        return array_filter(array_map(function($packetData) {
            $packet = new DecryptedPacket($this->server, $packetData);
            return $this->packetEncryptor->encryptPacket($packet);
        }, $packets), fn ($packet) => $packet !== null);
    }

    public function packetToMessages(EncryptedPacket $packet): array
    {
        $packet = $this->packetEncryptor->decryptPacket($packet);
        if ($packet === null) {
            return [];
        }
        return $packet->getMessages() ?? [];
    }
}
