<?php

namespace Sterzik\ModStamp\Message;

use Sterzik\ModStamp\Modstamp;

class ModstampModified extends AbstractMessage
{
    public static function getDescriptor(): string
    {
        return 'M';
    }

    public function __construct(private Modstamp $modstamp, private ?string $modstampValue)
    {
        if ($this->modstampValue === '') {
            $this->modtampValue = null;
        }
    }

    public function getModstamp(): Modstamp
    {
        return $this->modstamp;
    }

    public function getModstampValue(): ?string
    {
        return $this->modstampValue;
    }

    public function encodeToStrings(): array
    {
        return [$this->modstamp, $this->modstampValue];
    }
}
