<?php

namespace Sterzik\ModStamp\Cache;

use Sterzik\ModStamp\Modstamp;
use Sterzik\ModStamp\Peer;

interface ServerCache
{
    public function getPeersForModstamp(Modstamp $modstamp): array;
    public function assignPeerToModstamp(Peer $peer, Modstamp $modstamp): void;
    public function getModstampValue(Modstamp $modstamp): ?string;
    public function setModstampValue(Modstamp $modstamp, ?string $value): void;
    public function clear();
}
