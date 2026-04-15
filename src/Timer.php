<?php

namespace Sterzik\ModStamp;

use Exception;

class Timer
{
    private ?int $timeoutSeconds = null;
    private ?int $timeoutMicroseconds = null;

    public function start(int $miliseconds): self
    {
        $data = explode(" ", microtime());
        $this->timeoutSeconds = (int)$data[1];
        $this->timeoutMicroseconds = (int)$data[0];
        $this->timeoutMicroseconds += 1000 * $miliseconds;
        $this->timeoutSeconds += intdiv($this->timeoutMicroseconds, 1000000);
        $this->timeoutMicroseconds = $this->timeoutMicroseconds % 1000000;

        return $this;
    }

    public function startSec(int $seconds): self
    {
        $data = explode(" ", microtime());
        $this->timeoutSeconds = (int)$data[1];
        $this->timeoutMicroseconds = (int)$data[0];
        $this->timeoutSeconds += $seconds;

        return $this;
    }

    public function getRemainingMiliseconds(): int
    {
        if ($this->timeoutMicroseconds === null || $this->timeoutSeconds === null) {
            throw new Exception("timer was not started");
        }
        $data = explode(" ", microtime());
        $seconds =  $this->timeoutSeconds - (int)$data[1];
        $microseconds = $this->timeoutMicroseconds - (int)$data[0];
        if ($microseconds < 0) {
            $microseconds += 1000000;
            $seconds--;
        }
        
        return $seconds * 1000 + intdiv($microseconds, 1000);
    }

    public function reached(): bool
    {
        return $this->getRemainingMiliseconds() < 0;
    }
}
