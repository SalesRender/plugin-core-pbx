<?php

namespace SalesRender\Plugin\Core\PBX\Components\CDR\Components;

class CdrTiming
{
    protected int $start;
    protected int $duration;
    protected int $earlyMediaDuration;

    public function __construct(int $start, int $earlyMediaDuration, int $duration)
    {
        $this->start = $start;
        $this->earlyMediaDuration = $earlyMediaDuration;
        $this->duration = $duration;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getEarlyMediaDuration(): int
    {
        return $this->earlyMediaDuration;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }
}