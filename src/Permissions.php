<?php

namespace Sterzik\ModStamp;

enum Permissions: string
{
    case None = 'none';
    case Read = 'ro';
    case ReadWrite = 'rw';

    public function accessGranted(): bool
    {
        return $this !== self::None;
    }

    public function writeAccessGranted(): bool
    {
        return $this === self::ReadWrite;
    }
}
