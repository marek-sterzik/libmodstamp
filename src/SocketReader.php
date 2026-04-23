<?php

namespace Sterzik\ModStamp;

use Socket;

class SocketReader
{
    public static function parallel(callable ...$functions): array
    {
        $data = [];
        foreach ($functions as $function) {
            $data[] = $function();
        }
        return $data;
    }

    public static function readFromSocket(Socket $socket, ?int $timeoutMilliseconds): ?string
    {
        $data = self::readFromSockets([$socket], $timeoutMilliseconds) ?? [];
        return $data[0] ?? null;
    }

    private static function readFromSockets(array $sockets, ?int $timeoutMilliseconds): ?array
    {
        $w = null;
        $e = null;
        $result = socket_select($sockets, $w, $e, ...self::parseTimeoutForSelect($timeoutMilliseconds));
        if ($result === false) {
            return null;
        }
        $data = [];
        foreach ($sockets as $key => $value) {
            $from = '';
            $port = 0;
            $buf = '';
            if (socket_recvfrom($socket, $buf, 65535, 0, $from, $port) !== false) {
                $data[$key] = [
                    "from" => $from,
                    "port" => $port,
                    "buf" => $buf,
                ];
            } else {
                $data[$key] = null;
            }
        }
        return $data;
    }

    private static function parseTimeoutForSelect(?int $timeoutMillisecconds): array
    {
        if ($timeoutMilliseconds === null) {
            return [null, 0];
        }
        if ($timeoutMilliseconds < 0) {
            $timeoutMilliseconds = 0;
        }
        $seconds = intdiv($timeoutMilliseconds, 1000);
        $microseconds = ($timeoutMilliseconds % 1000) * 1000;
        return [$seconds, $microseconds];
    }
    
}
