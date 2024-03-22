<?php
/**
 * Created for plugin-core
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Form\Autocomplete\AutocompleteRegistry;
use SalesRender\Plugin\Components\Form\Form;
use SalesRender\Plugin\Components\Info\Developer;
use SalesRender\Plugin\Components\Info\Info;
use SalesRender\Plugin\Components\Info\PluginType;
use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Components\Translations\Translator;
use SalesRender\Plugin\Core\PBX\Components\CDR\CdrParserContainer;
use SalesRender\Plugin\Core\PBX\Components\CDR\CdrPricing;
use SalesRender\Plugin\Core\PBX\Components\Config\ConfigBuilder;
use SalesRender\Plugin\Core\PBX\Components\Config\ConfigSender;
use Medoo\Medoo;
use Money\Money;
use SalesRender\Plugin\Core\PBX\Components\Webhook\WebhookCallContainer;
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
        'currency' => $_ENV['LV_PLUGIN_PBX_PRICING_CURRENCY'],
        'pricing' => [
            'pbx' => 0,
            'record' => 0,
        ]
    ],
    new Developer(
        'Your (company) name',
        'support.for.plugin@example.com',
        'example.com',
    )
);

# 4. Configure settings form
Settings::setForm(fn() => new Form());

# 5. Configure form autocompletes (or remove this block if dont used)
AutocompleteRegistry::config(function (string $name) {
//    switch ($name) {
//        case 'status': return new StatusAutocomplete();
//        case 'user': return new UserAutocomplete();
//        default: return null;
//    }
});

# 6. Configure ConfigBuilder and ConfigSender as Settings::addOnSaveHandler()
Settings::addOnSaveHandler(function (Settings $settings) {
    $builder = new ConfigBuilder($settings);
    $sender = new ConfigSender($builder);
    $sender();
});

# 7. Define CDR reward pricing function
CdrPricing::config(function (Money $money) {
    return $money->divide(100)->multiply(10);
});

# 8. Configure CDR parsers
CdrParserContainer::config(
    new CdrApiParserInterface(),
    new CdrWebhookParserInterface(),
    new CdrWebhookParserInterface(),
);

# 9. Configure webhook call action. Required for Pbx Webhook plugin type
WebhookCallContainer::config(
    new WebhookCallAction(),
);