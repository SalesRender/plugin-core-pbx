<?php
/**
 * Created for plugin-core-macros
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\PBX\Factories;


use Leadvertex\Plugin\Components\SpecialRequestDispatcher\Commands\SpecialRequestDispatcherCommand;
use Symfony\Component\Console\Application;

class ConsoleAppFactory extends \Leadvertex\Plugin\Core\Factories\ConsoleAppFactory
{

    public function build(): Application
    {
        $app = parent::build();
        $app->add(new SpecialRequestDispatcherCommand());
        return $app;
    }

}