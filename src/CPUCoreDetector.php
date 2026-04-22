<?php

namespace Sterzik\ModStamp;

class CPUCoreDetector
{
    public function detectNumberOfCores(): int
    {
        $cpuinfo = @file_get_contents("/proc/cpuinfo");
        if (!is_string($cpuinfo)) {
            Log::log(Log::MSG, "cannot detect number of CPU cores, falling back to 1 CPU core");
            return 1;
        }
        preg_match_all('/^processor/m', $cpuinfo, $matches);
        $ncpu = count($matches[0]);

        if ($ncpu === 0) {
            Log::log(Log::MSG, "cannot detect number of CPU cores, falling back to 1 CPU core");
        }

        Log::log(Log::MSG, "number of CPU cores detected: %d", $ncpu);
        return $ncpu;
    }
}
