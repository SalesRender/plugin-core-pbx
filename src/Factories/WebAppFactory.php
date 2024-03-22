<?php
/**
 * Created for plugin-core-macros
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\PBX\Factories;


use SalesRender\Plugin\Core\PBX\Components\CDR\CdrParserContainer;
use SalesRender\Plugin\Core\PBX\Components\Webhook\WebhookCallContainer;
use Slim\App;

class WebAppFactory extends \SalesRender\Plugin\Core\Factories\WebAppFactory
{

    public function build(): App
    {
        $this->addCors();

        foreach (CdrParserContainer::getWebhookParsers() as $webhookParser) {
            $this->app->map(
                [$webhookParser->httpMethod()],
                $webhookParser->getPattern(),
                $webhookParser
            );
        }

        $webhookCallAction = WebhookCallContainer::getWebhookCallAction();
        if ($webhookCallAction !== null) {
            $this
                ->addCors()
                ->addSpecialRequestAction($webhookCallAction);
        }

        return parent::build();
    }

}