<?php

namespace Sterzik\ModStamp\Cache;

use Redis;
use Exception;
use Sterzik\ModStamp\Modstamp;
use Sterzik\ModStamp\Peer;

class ServerCache
{
    public function __construct(
        private RedisOperations $redis,
        private int $expirationSec = 300,
        private int $valueExpirationSec = 300
    ) {
    }

    public function getPeersForModstamp(Modstamp $modstamp): array
    {
        $peers = [];
        foreach ($this->redis->listKeys($modstamp->getPeerIdPrefix()) as $value) {
            $peers[] = Peer::fromData($value);
        }
        return $peers;
    }

    public function assignPeerToModstamp(Peer $peer, Modstamp $modstamp): void
    {
        $this->redis->set($modstamp->getPeerId($peer), $peer->getData(), $this->expirationSec);
    }

    public function getModstampValue(Modstamp $modstamp): ?string
    {
        $data = $this->redis->get($modstamp->getValueId(), $this->valueExpirationSec);
        return $data['m'] ?? null;
    }

    public function setModstampValue(Modstamp $modstamp, ?string $value): void
    {
        $this->redis->set($modstamp->getValueId(), isset($value) ? ['m' => $value] : null, $this->valueExpirationSec);
    }

    public function clear()
    {
        $this->redis->clear();
    }
}
