<?php
/**
 * Created for plugin-core-pbx
 * Date: 13.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\PBX\Components\CDR;

use Leadvertex\Plugin\Components\Access\Registration\Registration;
use Leadvertex\Plugin\Components\Db\Components\Connector;
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

        Registration::find()->makeSpecialRequest(
            'POST',
            (string) $uri,
            $this->cdr,
            60
        );
    }

}