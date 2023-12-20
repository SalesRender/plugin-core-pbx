<?php
/**
 * Created for plugin-core-pbx
 * Date: 13.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\PBX\Components\CDR;

use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Components\SpecialRequest;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Models\SpecialRequestTask;
use XAKEPEHOK\Path\Path;

class CdrSender
{

    /** @var CDR[] */
    private array $cdr;

    public function __construct(CDR ...$cdr)
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
            'PATCH',
            (string) $uri,
            (string) Registration::find()->getSpecialRequestToken($this->cdr, $ttl),
            time() + $ttl,
            202
        );

        $dispatcher = new SpecialRequestTask($request);
        $dispatcher->save();
    }

}