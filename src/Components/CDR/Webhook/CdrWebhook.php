<?php

namespace SalesRender\Plugin\Core\PBX\Components\CDR\Webhook;

use SalesRender\Plugin\Core\PBX\Components\CDR\CDR;
use SalesRender\Plugin\Core\PBX\Components\CDR\Components\CdrTiming;
use SalesRender\Plugin\Core\PBX\Components\CDR\Components\Reference;
use SalesRender\Plugin\Core\PBX\Components\CDR\Components\SipCause;

class CdrWebhook extends CDR
{
    public CdrTiming $timing;
    public SipCause $sipCause;
    public SipCause $hangupCause;
    public Reference $caller;
    public Reference $callee;
    public Reference $plugin;

    public function jsonSerialize(): array
    {
        return [
            'phone' => $this->phone,
            'uuid' => $this->callId,
            'call_start' => $this->timing->getStart(),
            'early_media_duration' => $this->timing->getEarlyMediaDuration(),
            'bill_sec' => $this->timing->getStart(),
            'call_record_url' => $this->recordUri,
            'record' => empty($this->recordUri),
            'pricing' => $this->pricing,
            'sip_fail_status' => $this->sipCause->getCode(),
            'sip_fail_phrase' => $this->sipCause->getPhrase(),
            'hangup_cause_status' => $this->hangupCause->getCode(),
            'hangup_cause_phrase' => $this->hangupCause->getPhrase(),
            'caller' => $this->caller,
            'callee' => $this->callee,
            'plugin' => $this->plugin,
        ];
    }
}