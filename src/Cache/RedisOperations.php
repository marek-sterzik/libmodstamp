<?php

namespace Sterzik\ModStamp\Cache;

use Generator;
use Redis;

class RedisOperations
{
    private Redis $redis;
    private ?array $locked = null;
    private string $uuid;

    public function __construct(array $redisConfig)
    {
        $this->uuid = uniqid();
        $this->redis = new Redis($redisConfig);   
    }

    public function clear()
    {
        $this->redis->flushAll();
    }

    public function listKeys(string $idPrefix): Generator
    {
        $keys = $this->redis->keys($idPrefix . "*");
        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value !== null) {
                yield $value;
            }
        }
    }

    public function get(string $id, ?int $expiration = null): ?array
    {
        $data = ($expiration === null) ? $this->redis->get($id) : $this->redis->getEx($id, ['EX' => $expiration]);
        if (!is_string($data)) {
            return null;
        }
        $data = @json_decode($data, true);
        if (!is_array($data)) {
            return null;
        }
        return $data;
    }

    public function set(string $id, ?array $data, ?int $expiration = null): self
    {
        if ($data === null) {
            $this->redis->del($id);
        } else {
            $data = json_encode($data);
            if ($expiration === null) {
                $this->redis->set($id, $data);
            } else {
                $this->redis->set($id, $data, ["EX" => $expiration]);
            }
        }
        return $this;
    }

    public function mod(string $id, callable $modCallback, ?int $expiration = null): ?array
    {
        $data = $this->get($id);
        $data = $modCallback($data);
        $this->set($id, $data, $expiration);
        return $data;
    }
}
