<?php

namespace Sterzik\ModStamp;

class Modstamp implements Validable
{
    public function __construct(private string $id)
    {
    }

    public function isValid(): bool
    {
        return (strlen($this->id) < 256 && preg_match('/^[0-9a-zA-Z_\\-\\.:]+$/', $this->id)) ? true : false;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getValueId(): string
    {
        return 'v:' . $this->id;
    }

    public function getPeerId(Peer $peer): string
    {
        return sprintf("c:%s:%s", $this->id, $peer->getId());
    }

    public function getPeerIdPrefix(): string
    {
        return sprintf("c:%s:", $this->id);
    }

    public function __tostring(): string
    {
        return $this->id;
    }
}
