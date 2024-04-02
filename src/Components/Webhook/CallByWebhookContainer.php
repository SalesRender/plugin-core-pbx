<?php

namespace SalesRender\Plugin\Core\PBX\Components\Webhook;

class CallByWebhookContainer
{
    private static ?CallByWebhookAction $callByWebhookAction = null;

    public static function config(?CallByWebhookAction $callByWebhookAction)
    {
        self::$callByWebhookAction = $callByWebhookAction;
    }

    public static function getCallByWebhookAction(): ?CallByWebhookAction
    {
        return self::$callByWebhookAction;
    }

}