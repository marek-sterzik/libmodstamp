<?php

namespace Sterzik\ModStamp\Cache;

use Sterzik\ModStamp\Modstamp;
use Sterzik\ModStamp\Peer;

class ServerCacheMemory implements ServerCache
{
    private array $modstampValues = [];
    private array $modstampPeers = [];

    public function __construct(
        private int $expirationSec = 300,
        private int $valueExpirationSec = 300
    ) {
    }

    public function getPeersForModstamp(Modstamp $modstamp): array
    {
        $modstampId = $modstamp->getId();
        $this->cleanupModstampPeers($modstampId);
        return array_map(fn ($x) => $x['p'], array_values($this->modstampPeers[$modstampId] ?? []));
    }

    public function assignPeerToModstamp(Peer $peer, Modstamp $modstamp): void
    {
        $modstampId = $modstamp->getId();
        $this->cleanupModstampPeers($modstampId);
        if (!isset($this->modstampPeers[$modstampId])) {
            $this->modstampPeers[$modstampId] = [];
        }
        $this->modstampPeers[$modstampId][$peer->getId()] = [
            "t" => time() + $this->expirationSec,
            "p" => $peer,
        ];
    }

    public function getModstampValue(Modstamp $modstamp): ?string
    {
        $modstampId = $modstamp->getId();
        if (!isset($this->modstampValues[$modstampId])) {
            return null;
        }
        if ($this->modstampValues[$modstampId]['t'] < time()) {
            unset($this->modstampValues[$modstampId]);
            return null;
        }

        $this->modstampValues[$modstampId]['t'] = time() + $this->valueExpirationSec;
        return $this->modstampValues[$modstampId]['v'];
    }

    public function setModstampValue(Modstamp $modstamp, ?string $value): void
    {
        $this->modstampValues[$modstamp->getId()] = [
            "t" => time() + $this->valueExpirationSec,
            "v" => $value,
        ];
    }

    public function clear()
    {
        $this->modstampValues = [];
        $this->modstampPeers = [];
    }

    private function cleanupModstampPeers(string $modstampId): void
    {
        if (isset($this->modstampPeers[$modstampId])) {
            $now = time();
            $this->modstampPeers[$modstampId] = array_filter(
                $this->modstampPeers[$modstampId],
                fn ($x) => $x['t'] >= $now
            );
            if (empty($this->modstampPeers[$modstampId])) {
                unset($this->modstampPeers[$modstampId]);
            }
        }
    }
}
