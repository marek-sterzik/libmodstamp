<?php

namespace Sterzik\ModStamp;

class CPUCoreDetector
{
    public function detectNumberOfCores(): int
    {
        $cpuinfo = @file_get_contents("/proc/cpuinfo");
        if (!is_string($cpuinfo)) {
            return 1;
        }
        preg_match_all('/^processor/m', $cpuinfo, $matches);
        $ncpu = count($matches[0]);
        return ($ncpu > 0) ? $ncpu : 1;
    }
}
