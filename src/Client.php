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

    public function sendModstamps(array $modstamps): array
    {
        $confirmedModstamps = [];

        $sender = function () use (&$modstamps) {
            $messages = [];
            foreach ($modstamps as $modstamp => $modstampValue) {
                $modstamp = new Modstamp((string)$modstamp);
                $modstampValue = (string)$modstampValue;
                $messages[] = new ChangeModstamp($modstamp, $modstampValue);
            }
            return $messages;
        };

        $receiver = function ($message) use (&$modstamps, &$confirmedModstamps) {
            if (!$message instanceof ModstampModified) {
                return false;
            }

            $key = $message->getModstamp()->getId();

            if (
                array_key_exists($key, $modstamps) &&
                $message->getModstampValue() === (string)$modstamps[$key]
            ) {
                unset($modstamps[$key]);
                $confirmedModstamps[$key] = $message->getModstampValue();
            }

            return empty($modstamps);
        };

        $this->request($sender, $receiver);

        return $confirmedModstamps;
    }

    public function listenForChange(array $modstamps, callable $changeCallback): void
    {
        $unconfirmedModstamps = [];

        $sender = function () use (&$unconfirmedModstamps) {
            $messages = [];
            foreach (array_keys($unconfirmedModstamps) as $modstamp) {
                $modstamp = new Modstamp((string)$modstamp);
                $messages[] = new QueryModstamp($modstamp);
            }
            return $messages;
        };

        $receiver = function ($message) use (&$modstamps, &$unconfirmedModstamps, $changeCallback) {
            if (!$message instanceof ReplyModstamp && !$message instanceof SignalModstamp) {
                return false;
            }
            $modstamp = $message->getModstamp()->getId();
            $ret = false;
            if (array_key_exists($modstamp, $modstamps)) {
                $modstampValue = $message->getModstampValue();
                $notify = false;
                if ($modstamps[$modstamp] !== null && $modstamps[$modstamp] !== $modstampValue) {
                    $notify = true;
                }
                $modstamps[$modstamp] = $modstampValue ?? '';
                if ($message instanceof ReplyModstamp) {
                    unset($unconfirmedModstamps[$modstamp]);
                }
                if (empty($unconfirmedModstamps)) {
                    $ret = true;
                }
                if ($notify) {
                    $changeCallback($modstamp, $modstampValue);
                }
            }
            return $ret;
        };

        $timer = new Timer();
        $packetClient = $this->getPacketClient();
        $socket = $this->getSocket();

        while (true) {
            $timer->startSec($this->clientConfig->getQueryIntervalSec());
            $unconfirmedModstamps = array_fill_keys(array_keys($modstamps), true);
            $this->request($sender, $receiver);
            while (!$timer->reached()) {
                $packet = EncryptedPacket::readFromSocket($socket, $timer->getRemainingMiliseconds());
                if ($packet !== null) {
                    $messages = $packetClient->packetToMessages($packet);
                    foreach ($messages as $message) {
                        $receiver($message);
                    }
                }
            }
        }
    }

    private function request(callable $sender, callable $receiver): void
    {
        $packetClient = $this->getPacketClient();
        $socket = $this->getSocket();

        $this->sendPackets($packetClient, $socket, $sender);

        $overallTimer = new Timer();
        $timer = new Timer();
        $overallTimer->start($this->clientConfig->getRequestTimeoutMs());
        $timer->start($this->clientConfig->getResendTimeoutMs());
        $confirmedModstamps = [];
        while (!$overallTimer->reached()) {
            while (!$timer->reached() && !$overallTimer->reached()) {
                $timeout = min($timer->getRemainingMiliseconds(), $overallTimer->getRemainingMiliseconds());
                $packet = EncryptedPacket::readFromSocket($socket, $timeout);
                if ($packet !== null) {
                    $messages = $packetClient->packetToMessages($packet);
                    foreach ($messages as $message) {
                        if ($receiver($message)) {
                            return;
                        }
                    }
                }
            }
            $this->sendPackets($packetClient, $socket, $sender);
        }
    }

    private function sendPackets(PacketClient $packetClient, Socket $socket, callable $sender): void
    {
        $messages = $sender();
        $packets = $packetClient->messagesToPackets($messages);
        foreach ($packets as $packet) {
            for ($i = 0; $i < $this->clientConfig->getSendRepeat(); $i++) {
                $packet->writeToSocket($socket);
            }
        }
    }

    private function getPacketClient(): PacketClient
    {
        $securityProfile = $this->clientConfig->getSecurityProfile();
        $host = gethostbyname($this->clientConfig->getHost());
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            throw new Exception(sprintf("Cannot resolve hostname: %s", $this->clientConfig->getHost()));
        }
        Log::log(Log::MSG, "using ip: %s", $host);
        if ($this->packetClient === null) {
            $securityProfileId = $securityProfile->matchFirstForHost($host);
            Log::log(Log::DBG, "matched security profile record: %s", $securityProfileId ?? 'none');
            $this->packetClient = new PacketClient(
                new Peer(
                    $host,
                    $this->clientConfig->getPort(),
                    Permissions::None,
                    $securityProfileId
                ),
                new PacketEncryptor($securityProfile),
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
