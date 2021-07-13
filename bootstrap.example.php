<?php
/**
 * Created for plugin-core
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Components\Form\Autocomplete\AutocompleteRegistry;
use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Components\Info\Developer;
use Leadvertex\Plugin\Components\Info\Info;
use Leadvertex\Plugin\Components\Info\PluginType;
use Leadvertex\Plugin\Components\Settings\Settings;
use Leadvertex\Plugin\Components\Translations\Translator;
use Leadvertex\Plugin\Core\PBX\Components\CDR\CdrParserContainer;
use Leadvertex\Plugin\Core\PBX\Components\CDR\CdrPricing;
use Leadvertex\Plugin\Core\PBX\Components\Config\ConfigBuilder;
use Leadvertex\Plugin\Core\PBX\Components\Config\ConfigSender;
use Medoo\Medoo;
use Money\Money;
use XAKEPEHOK\Path\Path;

# 0. Configure environment variable in .env file, that placed into root of app

# 1. Configure DB (for SQLite *.db file and parent directory should be writable)
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Set plugin default language
Translator::config('ru_RU');

# 3. Configure info about plugin
Info::config(
    new PluginType(PluginType::PBX),
    fn() => Translator::get('info', 'Plugin name'),
    fn() => Translator::get('info', 'Plugin markdown description'),
    [
        'pricing' => [
            'pbx' => 0,
            'record' => 0,
        ],
    ],
    new Developer(
        'Your (company) name',
        'support.for.plugin@example.com',
        'example.com',
    )
);

# 4. Configure settings form
Settings::setForm(fn() => new Form());

# 5 Configure ConfigBuilder and ConfigSender as Settings::addOnSaveHandler()
Settings::addOnSaveHandler(function (Settings $settings) {
    $builder = new ConfigBuilder($settings);
    $config = $builder();
    $sender = new ConfigSender($config);
    $sender();
});

# 5. Configure form autocompletes (or remove this block if dont used)
AutocompleteRegistry::config(function (string $name) {
//    switch ($name) {
//        case 'status': return new StatusAutocomplete();
//        case 'user': return new UserAutocomplete();
//        default: return null;
//    }
});

# 6. Define CDR reward pricing function
CdrPricing::config(function (Money $money) {
    return $money->divide(100)->multiply(10);
});

# 6. Configure CDR parsers
CdrParserContainer::config(
    new CdrApiParserInterface(),
    new CdrWebhookParserInterface(),
    new CdrWebhookParserInterface(),
);