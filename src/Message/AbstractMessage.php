<?php

namespace Sterzik\ModStamp\Message;

use Sterzik\ModStamp\Modstamp;
use Sterzik\ModStamp\MessageEncoder;

abstract class AbstractMessage
{
    abstract public static function getDescriptor(): string;
    abstract protected function encodeToStrings(): ?array;

    public function encode(): ?string
    {
        $strings = $this->encodeToStrings();
        return MessageEncoder::encodeMessage(static::getDescriptor(), $this->encodeToStrings());
    }
}
