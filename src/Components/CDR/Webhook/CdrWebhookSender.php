<?php

namespace SalesRender\Plugin\Core\PBX\Components\CDR\Webhook;

use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Components\SpecialRequest;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Models\SpecialRequestTask;
use XAKEPEHOK\Path\Path;

class CdrWebhookSender
{
    /** @var CdrWebhook[] */
    private array $cdr;

    public function __construct(CdrWebhook ...$cdr)
    {
        $this->cdr = $cdr;
    }

    public function __invoke()
    {
        $registration = Registration::find();
        $uri = (new Path($registration->getClusterUri()))
            ->down('companies')
            ->down(Connector::getReference()->getCompanyId())
            ->down('CRM/plugin/pbx/cdr');

        $ttl = 60 * 60 * 24;
        $request = new SpecialRequest(
            'PUT',
            (string)$uri,
            (string)Registration::find()->getSpecialRequestToken($this->cdr, $ttl),
            time() + $ttl,
            202
        );

        $dispatcher = new SpecialRequestTask($request);
        $dispatcher->save();
    }

}