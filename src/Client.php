<?php

namespace Sterzik\ModStamp;

use Exception;
use Socket;
use Sterzik\DI\DI;
use Sterzik\ModStamp\Message\ChangeModstamp;
use Sterzik\ModStamp\Message\ModstampModified;


class Client
{
    private DI $di;

    public function __construct(private ClientConfig $clientConfig)
    {
        $this->di = new DI($this->getDIConfig());
        $this->di->setParameters([
            "encryptionConfig" => $this->clientConfig->getEncryptionConfig(),
            "maxPacketSize" => $this->clientConfig->getMaxPacketSize(),
            "host" => $this->clientConfig->getHost(),
            "port" => $this->clientConfig->getPort(),
            "socketFamily" => $this->clientConfig->isIPv6() ? AF_INET6 : AF_INET,
        ]);
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
        return $this->di->get(PacketClient::class);
    }

    private function getSocket(): Socket
    {
        return $this->di->get(Socket::class);
    }

    private function getDIConfig(): array
    {
        return [
            Keyring::class => fn($builder) => $builder
                ->setArguments($builder->parameter("encryptionConfig")),
            PacketClient::class => fn($builder) => $builder
                ->setArgument("maxPacketSize", $builder->parameter("maxPacketSize")),
            Peer::class => fn($builder) => $builder
                ->setArgument("host", $builder->parameter("host"))
                ->setArgument("port", $builder->parameter("port"))
                ->setArgument("permissions", Permissions::None)
                ->setArgument("encryptionInfo", $builder->get(Keyring::class)->getDefaultEncryptionInfo()),
            Socket::class => function ($builder) {
                $socket = socket_create($builder->parameter("socketFamily"), SOCK_DGRAM, SOL_UDP);
                if (!$socket) {
                    throw new Exception("Cannot create socket");
                }
                return $socket;
            }
        ];
    }
}
