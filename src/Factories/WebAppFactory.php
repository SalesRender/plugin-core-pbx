<?php
/**
 * Created for plugin-core-macros
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\PBX\Factories;


use SalesRender\Plugin\Core\PBX\Components\CDR\CdrParserContainer;
use SalesRender\Plugin\Core\PBX\Components\Webhook\CallByWebhookContainer;
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

        $callByWebhookAction = CallByWebhookContainer::getCallByWebhookAction();
        if ($callByWebhookAction !== null) {
            $this
                ->addCors()
                ->addSpecialRequestAction($callByWebhookAction);
        }

        return parent::build();
    }

}