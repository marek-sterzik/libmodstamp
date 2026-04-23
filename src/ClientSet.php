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

        $functions = [];
        foreach ($this->clients as $i => $client) {
            $functions[] = function() use ($client) {
                return $client->sendModstamps($modstamps);
            };
        }

        $confirmedModstamps = SocketReader::parallel(...$functions);
        
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
                $finalModstamps = $modstamps;
            }
        }

        return $finalModstamps;
    }
}
