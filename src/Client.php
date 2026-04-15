<?php

namespace Sterzik\ModStamp;

use Exception;
use Socket;
use Sterzik\ModStamp\Message\ChangeModstamp;
use Sterzik\ModStamp\Message\QueryModstamp;
use Sterzik\ModStamp\Message\ModstampModified;
use Sterzik\ModStamp\Message\ReplyModstamp;
use Sterzik\ModStamp\Message\SignalModstamp;


class Client
{
    private ?Socket $socket = null;
    private ?PacketClient $packetClient = null;

    public function __construct(private ClientConfig $clientConfig)
    {
    }

    public function sendModstamps(array $modstamps, int $timeoutMs = 5000, int $resendIntervalMs = 100): array
    {
        $packetClient = $this->getPacketClient();
        $socket = $this->getSocket();

        $this->sendPackets($packetClient, $socket, $modstamps);

        $overallTimer = new Timer();
        $timer = new Timer();
        $overallTimer->start($timeoutMs);
        $timer->start($resendIntervalMs);
        $confirmedModstamps = [];
        while (!empty($modstamps) && !$overallTimer->reached()) {
            while (!empty($modstamps)  && !$timer->reached() && !$overallTimer->reached()) {
                $timeout = min($timer->getRemainingMiliseconds(), $overallTimer->getRemainingMiliseconds());
                $packet = EncryptedPacket::readFromSocket($socket, $timeout);
                if ($packet !== null) {
                    $messages = $packetClient->packetToMessages($packet);
                    foreach ($messages as $message) {
                        if (!$message instanceof ModstampModified) {
                            continue;
                        }
                        $key = $message->getModstamp()->getId();

                        if (
                            array_key_exists($key, $modstamps) &&
                            $message->getModstampValue() === (string)$modstamps[$key]
                        ) {
                            unset($modstamps[$key]);
                            $confirmedModstamps[$key] = $message->getModstampValue();
                        }
                    }
                }
            }
            $this->sendPackets($packetClient, $socket, $modstamps);
        }

        return $confirmedModstamps;
    }

    public function sendPackets(PacketClient $packetClient, Socket $socket, array $modstamps): void
    {
        $messages = [];
        foreach ($modstamps as $modstamp => $modstampValue) {
            $modstamp = new Modstamp((string)$modstamp);
            $modstampValue = (string)$modstampValue;
            $messages[] = new ChangeModstamp($modstamp, $modstampValue);
        }

        $packets = $packetClient->messagesToPackets($messages);
        foreach ($packets as $packet) {
            for ($i = 0; $i < $this->clientConfig->getSendRepeat(); $i++) {
                $packet->writeToSocket($socket);
            }
        }
    }

    private function getPacketClient(): PacketClient
    {
        $keyring = new Keyring($this->clientConfig->getEncryptionConfig());

        if ($this->packetClient === null) {
            $this->packetClient = new PacketClient(
                new Peer(
                    $this->clientConfig->getHost(),
                    $this->clientConfig->getPort(),
                    Permissions::None,
                    $keyring->getClientEncryptorId($this->clientConfig->getHost())
                ),
                new PacketEncryptor(
                    $keyring
                ),
                $this->clientConfig->getMaxPacketSize()
            );
        }
        return $this->packetClient;
    }

    private function getSocket(): Socket
    {
        if ($this->socket === null) {
            $this->socket = socket_create($this->clientConfig->isIPv6() ? AF_INET6 : AF_INET, SOCK_DGRAM, SOL_UDP);
            if (!$this->socket) {
                throw new Exception("Cannot create socket");
            }
        }
        return $this->socket;
    }
}
