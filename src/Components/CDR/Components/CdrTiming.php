<?php

namespace SalesRender\Plugin\Core\PBX\Components\CDR\Components;

class CdrTiming
{
    public int $start;
    public int $duration;
    public int $earlyMediaDuration;

    public function __construct(int $start, int $earlyMediaDuration, int $duration)
    {
        $this->start = $start;
        $this->earlyMediaDuration = $earlyMediaDuration;
        $this->duration = $duration;
    }

}