<?php

namespace Sterzik\ModStamp\Message;

use Sterzik\ModStamp\Modstamp;

class QueryModstamp extends AbstractMessage
{
    public static function getDescriptor(): string
    {
        return 'Q';
    }

    public function __construct(private Modstamp $modstamp)
    {
    }

    public function getModstamp(): Modstamp
    {
        return $this->modstamp;
    }

    public function encodeToStrings(): array
    {
        return [$this->modstamp];
    }
}
