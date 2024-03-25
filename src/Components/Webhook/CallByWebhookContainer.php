<?php

namespace SalesRender\Plugin\Core\PBX\Components\Webhook;

class CallByWebhookContainer
{
    private static ?CallByWebhookAction $webhookCallAction = null;

    public static function config(?CallByWebhookAction $webhookCallAction)
    {
        self::$webhookCallAction = $webhookCallAction;
    }

    public static function getWebhookCallAction(): ?CallByWebhookAction
    {
        return self::$webhookCallAction;
    }

}