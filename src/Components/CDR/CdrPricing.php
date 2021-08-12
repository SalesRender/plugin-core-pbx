<?php
/**
 * Created for plugin-core-pbx
 * Date: 13.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\PBX\Components\CDR;

use JsonSerializable;
use Money\Money;

class CdrPricing implements JsonSerializable
{

    /** @var callable */
    private static $rewardCalc;

    private Money $providerPricing;

    public function __construct(Money $providerPricing)
    {
        $this->providerPricing = $providerPricing;
    }

    public function getProviderPricing(): Money
    {
        return $this->providerPricing;
    }

    public function getRewardPricing(): Money
    {
        return (self::$rewardCalc)($this->providerPricing);
    }

    public static function config(callable $rewardCalc): void
    {
        self::$rewardCalc = $rewardCalc;
    }


    public function jsonSerialize(): array
    {
        return [
            'provider' => $this->getProviderPricing()->jsonSerialize(),
            'reward' => $this->getRewardPricing()->jsonSerialize(),
        ];
    }
}