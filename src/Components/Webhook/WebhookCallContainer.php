<?php

namespace SalesRender\Plugin\Core\PBX\Components\Webhook;

class WebhookCallContainer
{
    private static ?WebhookCallAction $webhookCallAction = null;

    public static function config(?WebhookCallAction $webhookCallAction)
    {
        self::$webhookCallAction = $webhookCallAction;
    }

    public static function getWebhookCallAction(): ?WebhookCallAction
    {
        return self::$webhookCallAction;
    }

}