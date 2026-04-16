<?php

namespace Sterzik\ModStamp;

class ClientSet
{
    public function __construct(private array $clients)
    {
    }

    public function sendModstamps(array $modstamps): array
    {
        foreach ($this->clients as $client) {
            $modstamps = $client->sendModstamps($modstamps);
            if (empty($modstamps)) {
                break;
            }
        }
        return $modstamps;
    }
}
