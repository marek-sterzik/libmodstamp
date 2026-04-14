<?php

namespace Sterzik\ModStamp;

class Packet
{
    public static function createFromData(string $data): ?self
    {
    }

    public static function queryModstamp(string $modstampId): ?self
    {
        return (new self(["Q", $modstampId]))->getValidPacket();
    }

    public static function replyModstamp(string $modstampId, string $modstampValue): ?self
    {
        return (new self(["R", $modstampId, $modstampValue]))->getValidPacket();
    }

    public static function signalModstamp(string $modstampId, string $modstampValue): ?self
    {
        return (new self(["S", $modstampId, $modstampValue]))->getValidPacket();
    }

    public static function changeModstamp(string $modstampId, string $modstampValue): ?self
    {
        return (new self(["C", $modstampId, $modstampValue]))->getValidPacket();
    }

    public static function modifiedModstamp(string $modstampId, string $modstampValue): ?self
    {
        return (new self(["M", $modstampId, $modstampValue]))->getValidPacket();
    }

    public function __construct(array $strings)
    {
    }

    public function isValid(): bool
    {
        return true;
    }

    public function getValidPackt(): ?self
    {
        return ($this->isValid()) ? $this : null;
    }
}
