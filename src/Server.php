<?php

namespace Sterzik\ModStamp;

use Throwable;
use Exception;
use Socket;
use Sterzik\ModStamp\Cache\RedisOperations;
use Sterzik\ModStamp\Cache\ServerCache;
use Sterzik\ModStamp\Cache\ServerCacheRedis;
use Sterzik\ModStamp\Cache\ServerCacheMemory;
use Sterzik\ModStamp\Storage\ServerStorage;

class Server
{
    private ?ServerCache $serverCache = null;

    public function __construct(private ServerConfig $serverConfig)
    {
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
        
        $redisConfig = $this->serverConfig->getRedisConfig();
        if ($redisConfig === null && $processes > 1) {
            fprintf(
                STDERR,
                "Warning: cannot operate on multiple processes when redis is not configured," .
                "falling back to 1 process\n"
            );
            $processes = 1;
        }

        $this->getServerCache()->clear();
        if ($processes > 1) {
            $this->forgetServerCache();
        }
        if ($processes <= 1) {
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
        return new PacketServer(
            new MessageServer(
                $this->getServerCache(),
                new ServerStorage($this->serverConfig->getModstampDatabaseFile()),
            ),
            new PacketEncryptor(
                new Keyring($this->serverConfig->getEncryptionConfig())
            ),
            $this->serverConfig->getMaxPacketSize(),
            $this->serverConfig->getBroadcastRepeat()
        );
    }

    private function getServerCache(): ServerCache
    {
        if ($this->serverCache === null) {
            $timeout = 300;
            $redisConfig = $this->serverConfig->getRedisConfig();
            if ($redisConfig !== null) {
                $this->serverCache = new ServerCacheRedis(new RedisOperations($redisConfig), $timeout, $timeout);
            } else {
                $this->serverCache = new ServerCacheMemory($timeout, $timeout);
            }
        }
        return $this->serverCache;
    }

    private function forgetSererCache(): self
    {
        $this->serverCache = null;
    }
}
