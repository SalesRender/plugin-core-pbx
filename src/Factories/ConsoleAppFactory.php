<?php
/**
 * Created for plugin-core-pbx
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\PBX\Factories;


use Symfony\Component\Console\Application;

class ConsoleAppFactory extends \SalesRender\Plugin\Core\Factories\ConsoleAppFactory
{

    public function build(): Application
    {
        return parent::build();
    }

}