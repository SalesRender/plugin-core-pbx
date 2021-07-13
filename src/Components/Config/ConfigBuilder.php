<?php
/**
 * Created for plugin-core-pbx
 * Date: 09.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\PBX\Components\Config;

use Leadvertex\Plugin\Components\Settings\Settings;

interface ConfigBuilder
{

    public function __construct(Settings $settings);

    public function __invoke(): Config;

}