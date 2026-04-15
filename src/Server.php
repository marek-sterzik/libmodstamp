<?php

namespace Sterzik\ModStamp;

use Throwable;
use Exception;
use Socket;
use Sterzik\DI\DI;
use Sterzik\ModStamp\Cache\RedisOperations;
use Sterzik\ModStamp\Storage\ServerStorage;

class Server
{
    private DI $di;

    public function __construct(private ServerConfig $serverConfig)
    {
        $this->di = new DI($this->getDIConfig());
        $this->di->setParameters([
            "redisConfig" => $this->serverConfig->getRedisConfig(),
            "modstampDbFile" => $this->serverConfig->getModstampDatabaseFile(),
            "encryptionConfig" => $this->serverConfig->getEncryptionConfig(),
            "maxPacketSize" => $this->serverConfig->getMaxPacketSize(),
            "broadcastRepeat" => $this->serverConfig->getBroadcastRepeat(),
        ]);
    }

    public function serve(): void
    {
        $socket = socket_create($this->serverConfig->isIPv6() ? AF_INET6 : AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            throw new Exception("Cannot create socket");
        }
        if (!socket_bind($socket, $this->serverConfig->getListenIp(), $this->serverConfig->getListenPort())) {
            throw new Exception("Unable to bind socket");
        }

        $processes = $this->serverConfig->getProcesses();

        if ($processes <= 1) {
            $this->getRedisOperations()->clear();
            $this->runWorker($socket);
        } else {
            for ($i = 0; $i < $processes; $i++) {
                $pid = pcntl_fork();
                if ($pid === 0) {
                    $this->spawnChild($socket);
                    exit(1);
                } elseif ($pid < 0) {
                    $processes = $i;
                    break;
                }
            }
            $this->getRedisOperations()->clear();
        }
        for ($i = 0; $i < $processes; $i++) {
            pcntl_wait($status);
        }
    }

    private function spawnChild(Socket $socket): void
    {
        try {
            $this->runWorker($socket);
        } catch (Throwable $e) {
            exit (1);
        }
    }

    private function runWorker(Socket $socket): void
    {
        $packetServer = $this->createPacketServer();
        while(true) {
            $packet = EncryptedPacket::readFromSocket($socket);
            if ($packet === null) {
                return;
            }

            foreach ($packetServer->handlePacket($packet) as $packetToSend) {
                $packetToSend->writeToSocket($socket);
            }
        }
    }

    private function createPacketServer(): PacketServer
    {
        return $this->di->get(PacketServer::class);
    }

    private function getRedisOperations(): RedisOperations
    {
        return $this->di->get(RedisOperations::class);
    }

    private function getDIConfig(): array
    {
        return [
            RedisOperations::class => fn($builder) => $builder
                ->setArguments($builder->parameter("redisConfig")),
            ServerStorage::class => fn($builder) => $builder
                ->setArguments($builder->parameter("modstampDbFile")),
            Keyring::class => fn($builder) => $builder
                ->setArguments($builder->parameter("encryptionConfig")),
            PacketServer::class => fn($builder) => $builder
                ->setArgument("maxPacketSize", $builder->parameter("maxPacketSize"))
                ->setArgument("broadcastRepeat", $builder->parameter("broadcastRepeat"))
        ];
    }
}
