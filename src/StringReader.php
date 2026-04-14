<?php

namespace Sterzik\ModStamp;

class StringReader
{
    private int $currentOffset = 0;
    private int $length = 0;

    public function __construct(private string $string)
    {
        $this->length = strlen($string);
    }

    public function isEof(): bool
    {
        return $this->currentOffset === $this->length;
    }

    public function readSingleMessage(): ?array
    {
        $descriptor = $this->readString(1);
        if ($descriptor === null) {
            return null;
        }
        $message = [$descriptor];
        $nArgs = $this->readLength();
        if ($nArgs === null) {
            return null;
        }
        for ($i = 0; $i < $nArgs; $i++) {
            $str = $this->readVarString();
            if ($str === null) {
                return null;
            }
            $message[] = $str;
        }
        return $message;
    }

    private function readVarString(): ?string
    {
        $length = $this->readLength();
        if ($length === null) {
            return null;
        }
        return $this->readString($length);
    }

    private function readLength(): ?int
    {
        $char = $this->readString(1);
        if ($char === null) {
            return null;
        }
        return ord($char);
    }

    private function readString(int $length): ?string
    {
        if ($this->currentOffset + $length > $this->length) {
            return null;
        }
        $string = substr($this->string, $this->currentOffset, $length);
        $this->currentOffset += $length;
        return $string;
    }
}
