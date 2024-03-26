<?php

namespace SalesRender\Plugin\Core\PBX\Components\CDR\Components;

use XAKEPEHOK\EnumHelper\EnumHelper;

class Direction extends EnumHelper
{
    public const INCOMING = 'incoming';
    public const OUTGOING = 'outgoing';

    public string $value;

    public function __construct(string $value)
    {
        self::guardValidValue($value);
        $this->value = $value;
    }

    public static function values(): array
    {
        return [
            self::INCOMING,
            self::OUTGOING,
        ];
    }
}