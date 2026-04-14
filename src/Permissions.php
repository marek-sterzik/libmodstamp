<?php

namespace Sterzik\ModStamp;

enum Permissions: string
{
    case None = 'N';
    case Read = 'R';
    case ReadWrite = 'W';

    public function accessGranted(): bool
    {
        return $this !== self::None;
    }

    public function writeAccessGranted(): bool
    {
        return $this === self::ReadWrite;
    }
}
