<?php
/**
 * Created for plugin-core-pbx
 * Date: 09.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\PBX\Components\CDR;

class CdrParserContainer
{

    private static ?CdrApiParserInterface $apiParser;
    private static array $webhookParsers;

    private function __construct() {}

    public static function config(?CdrApiParserInterface $apiParser, CdrWebhookParserInterface ...$webhookParsers): void
    {
        self::$apiParser = $apiParser;
        self::$webhookParsers = $webhookParsers;
    }

    public static function getApiParser(): CdrApiParserInterface
    {
        return self::$apiParser;
    }

    /**
     * @return CdrWebhookParserInterface[]
     */
    public static function getWebhookParsers(): array
    {
        return self::$webhookParsers;
    }

}