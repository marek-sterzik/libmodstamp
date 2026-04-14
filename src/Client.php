<?php

namespace Sterzik\ModStamp;

class Client
{
    public static function fromData(array $data): Client
    {
        return new self($data['host'], $data['port']);
    }

    public function __construct(private string $host, private int $port)
    {
    }

    public function getId(): string
    {
        return sprintf("%s:%d", $this->host, $this->port);
    }

    public function getData(): array
    {
        return [
            "host" => $this->host,
            "port" => $this->port,
        ];
    }
}
