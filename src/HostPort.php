<?php

namespace Sterzik\ModStamp;

class HostPort
{
    public static function parse(string $hostPort, ?int $defaultPort = null): ?array
    {
        if (preg_match('/^(.*):([0-9]+)$/', $hostPort, $matches)) {
            $host = $matches[1];
            $port = (int)$matches[2];
            if ($port < 1 || $port > 65535) {
                return null;
            }
        } else {
            $host = $hostPort;
            $port = $defaultPort;
        }

        if ($port === null) {
            return null;
        }

        if (preg_match('/^\\[(.*)\\]$/', $host, $matches)) {
            $host = $matches[1];
        }


        if (!filter_var($host, FILTER_VALIDATE_DOMAIN) && !filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        return [
            'host' => $host,
            'port' => $port,
        ];
    }
}
