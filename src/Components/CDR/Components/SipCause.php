<?php

namespace SalesRender\Plugin\Core\PBX\Components\CDR\Components;

class SipCause
{
    public int $code;
    public string $phrase;

    public function __construct(int $code, string $phrase)
    {
        $this->code = $code;
        $this->phrase = $phrase;
    }

}