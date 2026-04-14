<?php

namespace Sterzik\ModStamp\Cache;

use Redis;
use Exception;
use Sterzik\ModStamp\Modstamp;
use Sterzik\ModStamp\Client;

class ServerCache
{
    const EXPIRATION = 10;
    const VALUE_EXPIRATION = 10;

    public function __construct(private RedisOperations $redis)
    {
    }

    public function getClientsForModstamp(Modstamp $modstamp): array
    {
        $clients = [];
        foreach ($this->redis->listKeys($modstamp->getClientIdPrefix()) as $value) {
            $clients[] = Client::fromData($value);
        }
        return $clients;
    }

    public function assignClientToModstamp(Client $client, Modstamp $modstamp): void
    {
        $this->redis->set($modstamp->getClientId($client), $client->getData(), self::EXPIRATION);
    }

    public function getModstampValue(Modstamp $modstamp): ?string
    {
        $data = $this->redis->get($modstamp->getValueId(), self::VALUE_EXPIRATION);
        return $data['m'] ?? null;
    }

    public function setModstampValue(Modstamp $modstamp, ?string $value): void
    {
        $this->redis->set($modstamp->getValueId(), isset($value) ? ['m' => $value] : null, self::VALUE_EXPIRATION);
    }

    public function clear()
    {
        $this->redis->clear();
    }
}
