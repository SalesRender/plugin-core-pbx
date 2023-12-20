<?php
/**
 * Created for plugin-core-pbx
 * Date: 09.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\PBX\Components\CDR;

interface CdrApiParserInterface
{

    /**
     * @return CDR[]
     */
    public function __invoke(): array;

}