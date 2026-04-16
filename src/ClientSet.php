<?php

namespace Sterzik\ModStamp;

class ClientSet
{
    public function __construct(private array $clients)
    {
    }

    public function sendModstamps(array $modstamps): array
    {
        $confirmedModstamps = [];
        
        foreach ($this->clients as $i => $client) {
            $confirmedModstamps[$i] = $client->sendModstamps($modstamps);
            if (empty($modstamps)) {
                break;
            }
        }
        
        if (empty($confirmedModstamps)) {
            return [];
        }
        
        $finalModstamps = array_shift($confirmedModstamps);

        while (!empty($confirmedModstamps)) {
            $modstamps = array_shift($confirmedModstamps);
            foreach (array_keys($modstamps) as $modstamp) {
                if (!array_key_exists($modstamp, $finalModstamps)) {
                    unset($modstamps[$modstamp]);
                }
                $finalmodstamps = $modstamps;
            }
        }

        return $finalModstamps;
    }
}
