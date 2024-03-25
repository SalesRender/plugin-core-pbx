<?php

namespace SalesRender\Plugin\Core\PBX\Components\CDR\Components;

class SipCause
{
    private int $code;
    private string $phrase;

    public function __construct(int $code, string $phrase)
    {
        $this->code = $code;
        $this->phrase = $phrase;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getPhrase(): string
    {
        return $this->phrase;
    }
}