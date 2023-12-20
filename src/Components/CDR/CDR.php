<?php
/**
 * Created for plugin-core-pbx
 * Date: 13.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\PBX\Components\CDR;

use JsonSerializable;

class CDR implements JsonSerializable
{

    public string $phone;
    public ?string $callId = null;
    public int $timestamp;
    public int $duration;
    public ?string $recordUri = null;
    public ?CdrPricing $pricing = null;

    public function __construct(string $phone)
    {
        $this->phone = $phone;
    }

    public function jsonSerialize(): array
    {
        return [
            'phone' => $this->phone,
            'callId' => $this->callId,
            'timestamp' => $this->timestamp,
            'duration' => $this->duration,
            'recordUri' => $this->recordUri,
            'pricing' => $this->pricing,
        ];
    }
}