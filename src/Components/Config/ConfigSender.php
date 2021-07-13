<?php
/**
 * Created for plugin-core-pbx
 * Date: 13.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\PBX\Components\Config;

use Leadvertex\Plugin\Components\Access\Registration\Registration;
use Leadvertex\Plugin\Components\Db\Components\Connector;
use XAKEPEHOK\Path\Path;

class ConfigSender
{

    private ConfigBuilder $builder;

    public function __construct(ConfigBuilder $builder)
    {
        $this->builder = $builder;
    }

    public function __invoke()
    {
        $config = ($this->builder)();
        $registration = Registration::find();
        $uri = (new Path($registration->getClusterUri()))
            ->down('companies')
            ->down(Connector::getReference()->getCompanyId())
            ->down('CRM/plugin/pbx/settings');

        Registration::find()->makeSpecialRequest(
            'POST',
            (string) $uri,
            $config->jsonSerialize(),
            60
        );
    }

}