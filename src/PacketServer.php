<?php

namespace Sterzik\ModStamp;

use Sterzik\ModStamp\Cache\ServerCache;
use Sterzik\ModStamp\Storage\ServerStorage;
use Sterzik\modStamp\Message\AbstractMessage;

class PacketServer
{
    public function __construct(
        private MessageServer $messageServer,
        private PacketEncryptor $packetEncryptor,
        private int $maxPacketSize = 1000,
        private int $broadcastRepeat = 2,
    ) {
    }

    public function handlePacket(EncryptedPacket $packet): array
    {
        $packet = $this->packetEncryptor->decryptPacket($packet);
        if ($packet === null) {
            return [];
        }
        $messages = $packet->getMessages();
        if ($messages === null || empty($messages)) {
            return [];
        }
        $client = $packet->getClient();
        $broadcastPackets = [];
        $broadcast = function (Client $client, AbstractMessage $message) use (&$broadcastPackets) {
            $encodedMessage = $message->encode();
            if ($encodedMessage !== null) {
                for ($i = 0; $i < $this->broadcastRepeat; $i++) {
                    $broadcastPackets[] = new DecryptedPacket($client, $encodedMessage);
                }
            }
        };

        $maxPacketSize = $this->maxPacketSize - $this->packetEncryptor->getHeaderSizeForClient($client);
        $replyPackets = [];
        $currentPacket = '';

        foreach ($messages as $message) {
            $replyMessage = $this->messageServer->handleMessage($client, $message, $broadcast);
            if ($replyMessage !== null) {
                $encodedMessage = $replyMessage->encode();
                if ($encodedMessage !== null) {
                    if ($currentPacket !== '' && strlen($currentPacket) + strlen($encodedMessage) > $maxPacketSize) {
                        $replyPackets[] = $currentPacket;
                        $currentPacket = '';
                    }
                    $currentPacket .= $encodedMessage;
                }
            }
        }
        if ($currentPacket !== '') {
            $replyPackets[] = $currentPacket;
        }
        $replyPackets = array_map(function ($packetData) use ($client) {
            return new DecryptedPacket($client, $packetData);
        }, $replyPackets);

        return array_map(function ($packet) {
            return $this->packetEncryptor->encryptPacket($packet);
        }, array_merge($broadcastPackets, $replyPackets));
    }
}
