<?php
/**
 * Created for plugin-core-pbx
 * Date: 13.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\PBX\Components\Config;

use Leadvertex\Plugin\Components\Access\Registration\Registration;
use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Components\SpecialRequestDispatcher\Components\SpecialRequest;
use Leadvertex\Plugin\Components\SpecialRequestDispatcher\Models\SpecialRequestDispatcher;
use XAKEPEHOK\Path\Path;

class ConfigSender
{

    private ConfigBuilder $builder;

    public function __construct(ConfigBuilder $builder)
    {
        $this->builder = $builder;
    }

    public function __invoke(): void
    {
        $config = ($this->builder)();
        $registration = Registration::find();
        $uri = (new Path($registration->getClusterUri()))
            ->down('companies')
            ->down(Connector::getReference()->getCompanyId())
            ->down('CRM/plugin/pbx/gateway');

        $ttl = 60 * 60 * 24;
        $request = new SpecialRequest(
            'PUT',
            (string) $uri,
            (string) Registration::find()->getSpecialRequestToken($config->jsonSerialize(), $ttl),
            time() + $ttl,
            202
        );

        $dispatcher = new SpecialRequestDispatcher($request);
        $dispatcher->save();
    }

}