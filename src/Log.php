<?php

namespace Sterzik\ModStamp;

class Log
{
    const ERR = "error";
    const WARN = "warning";
    const MSG = "message";
    const DBG = "debug";

    const LEVELS = [
        self::ERR => 0,
        self::WARN => 1,
        self::MSG => 2,
        self::DBG => 3,
    ];

    static int $currentLevel = 1;

    public static function log(string $level, string $message, string ...$args): void
    {
        if (self::enabled($level)) {
            fprintf(STDERR, "%s %s: %s\n", (new DateTime())->format("c"), $level, sprintf($message, ...$args));
        }
    }

    public static function setLevel(string $level): void
    {
        if (isset(self::LEVELS[$level])) {
            self::$currentLevel = self::LEVELS[$level];
        }
    }

    public static function enabled(string $level): bool
    {
        $levelNum = self::LEVELS[$level] ?? null;
        if ($levelNum !== null && $levelNum <= self::$currentLevel) {
            return true;
        }
        return false;
    }
}
