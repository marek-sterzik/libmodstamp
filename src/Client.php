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

    public function listenForChange(array $modstamps, callable $changeCallback, bool $once = false): bool
    {
        $unconfirmedModstamps = [];

        $run = true;

        $noResponseTimer = new Timer();
        $timer = new Timer();
        $packetClient = $this->getPacketClient();
        $socket = $this->getSocket();
        $this->setupNoResponseTimer($noResponseTimer);

        $sender = function () use (&$unconfirmedModstamps) {
            $messages = [];
            foreach (array_keys($unconfirmedModstamps) as $modstamp) {
                $modstamp = new Modstamp((string)$modstamp);
                $messages[] = new QueryModstamp($modstamp);
            }
            return $messages;
        };

        $receiver = function ($message) use (&$modstamps, &$unconfirmedModstamps, &$run, $once, $noResponseTimer, $changeCallback) {
            $this->setupNoResponseTimer($noResponseTimer);
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
                    if ($once) {
                        $run = false;
                        return true;
                    }
                }
            }
            return $ret;
        };

        while ($run && !$noResponseTimer->startedAndReached()) {
            $timer->startSec($this->clientConfig->getQueryIntervalSec());
            $unconfirmedModstamps = array_fill_keys(array_keys($modstamps), true);
            $this->request($sender, $receiver);
            while (!$timer->reached()) {
                $packet = EncryptedPacket::readFromSocket($socket, $timer->getRemainingMiliseconds());
                if ($packet !== null) {
                    $this->setupNoResponseTimer($noResponseTimer);
                    $messages = $packetClient->packetToMessages($packet);
                    foreach ($messages as $message) {
                        $receiver($message);
                    }
                }
            }
        }

        if ($noResponseTimer->startedAndReached()) {
            return false;
        }
        return true;
    }

    private function setupNoResponseTimer(Timer $timer): void
    {
        $timeout = $this->clientConfig->getNoResponseTimeoutSec();
        if ($timeout !== null) {
            $timer->startSec($timeout);
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
            $timer->start($this->clientConfig->getResendTimeoutMs());
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
        $host = $this->resolveHost($this->clientConfig->getHost());
        if ($host === null) {
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

    private function resolveHost(string $host): ?string
    {
        $ipv6 = $this->clientConfig->isIPv6();
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($ipv6 && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return null;
            }
            return $host;
        }
        if (!$ipv6) {
            $ip = gethostbyname($host);
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return null;
            }
            return $ip;
        }
        $result = dns_get_record("a.milimetr.org", DNS_A | DNS_AAAA, $authns, $addtl);
        $ipv6Addr = null;
        if (is_array($result)) {
            foreach($result as $record) {
                if ($record['class'] ?? null !== 'IN') {
                    continue;
                }
                $ip = $record['ip'] ?? null;
                if (
                    ($record['type'] ?? null) === 'A' &&
                    $ip !== null &&
                    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                ) {
                    return "::ffff:" . $ip;
                } elseif(
                    $ipv6Addr === null &&
                    ($record['type'] ?? null) === 'AAAA' &&
                    $ip !== null &&
                    filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
                ) {
                    $ipv6Addr = $ip;
                }
                
            }
        }
        return $ipv6Addr;
    }
}
