<?php
/**
 * Created for plugin-core-pbx
 * Date: 09.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\PBX\Components\CDR;

interface CdrWebhookParserInterface
{

    public function httpMethod(): string;

    public function getPattern(): string;

    /**
     * @return CDR[]
     */
    public function __invoke(): array;

}