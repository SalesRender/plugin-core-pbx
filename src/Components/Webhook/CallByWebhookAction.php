<?php

namespace SalesRender\Plugin\Core\PBX\Components\Webhook;

use SalesRender\Plugin\Core\Actions\SpecialRequestAction;

abstract class CallByWebhookAction extends SpecialRequestAction
{
    public function getName(): string
    {
        return 'call';
    }
}