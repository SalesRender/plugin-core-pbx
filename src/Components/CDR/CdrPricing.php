<?php
/**
 * Created for plugin-core-pbx
 * Date: 13.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\PBX\Components\CDR;

use Money\Money;

class CdrPricing
{

    /** @var callable */
    private static $rewardCals;

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
        return (self::$rewardCals)($this->providerPricing);
    }

    public static function config(callable $rewardCalc): void
    {
        self::$rewardCals = $rewardCalc;
    }


}