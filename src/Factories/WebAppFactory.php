<?php
/**
 * Created for plugin-core-macros
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\PBX\Factories;


use SalesRender\Plugin\Core\PBX\Components\CDR\CdrParserContainer;
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

        return parent::build();
    }

}