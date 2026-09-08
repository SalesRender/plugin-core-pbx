# Plugin Core PBX

> Core framework for SalesRender PBX (telephony) plugins

[Russian version / Русская версия](readme.ru.md)

## Overview

`salesrender/plugin-core-pbx` is a specialized core library that extends `salesrender/plugin-core` to provide the infrastructure for PBX (Private Branch Exchange) telephony plugins in the SalesRender ecosystem.

PBX plugins integrate SalesRender with VoIP/SIP telephony providers, enabling:

- **CDR (Call Detail Records)** -- parsing call records from external telephony APIs or webhooks and forwarding them to the SalesRender backend
- **Config provisioning** -- building and sending SIP gateway configuration (credentials, routing rules, protocol settings) to SalesRender
- **Call initiation via webhook** -- triggering outbound calls through special requests from the SalesRender platform

## Installation

```bash
composer require salesrender/plugin-core-pbx
```

### Requirements

- PHP >= 7.4
- ext-json
- `salesrender/plugin-core` ^0.4.0
- `moneyphp/money` ^3.3
- `salesrender/plugin-component-purpose` ^2.0

## Architecture

### How It Extends plugin-core

`plugin-core-pbx` extends the base `plugin-core` framework by providing two specialized factories:

1. **WebAppFactory** (`SalesRender\Plugin\Core\PBX\Factories\WebAppFactory`) extends the base `WebAppFactory` and automatically:
   - Registers all webhook parsers from `CdrParserContainer` as HTTP routes (each `CdrWebhookParserInterface` defines its own method and URL pattern)
   - Registers the optional `CallByWebhookAction` as a special request action for initiating calls
   - Adds CORS support

2. **ConsoleAppFactory** (`SalesRender\Plugin\Core\PBX\Factories\ConsoleAppFactory`) extends the base console factory, inheriting all standard CLI commands.

### What the Developer Must Implement

| Interface / Class | Purpose |
|-------------------|---------|
| `CdrApiParserInterface` | Parse CDR records by polling the telephony provider API |
| `CdrWebhookParserInterface` | Parse CDR records received via webhook from the provider |
| `ConfigBuilder` | Build SIP gateway configuration from plugin settings |
| `CallByWebhookAction` (optional) | Handle call initiation requests from SalesRender |

### Bootstrap Configuration Steps

The `bootstrap.php` file wires everything together (see `bootstrap.example.php` in the repository):

1. Configure the database connection (`Connector::config`)
2. Set the default language (`Translator::config`)
3. Configure plugin info (`Info::config` with `PluginType::PBX`)
4. Configure the settings form (`Settings::setForm`)
5. Configure autocompletes (optional)
6. Set up `ConfigBuilder` + `ConfigSender` as a settings save handler
7. Define CDR reward pricing function (`CdrPricing::config`)
8. Register CDR parsers (`CdrParserContainer::config`)
9. Register `CallByWebhookAction` (optional, for PBX Webhook plugin type)

## Getting Started: Creating a PBX Plugin

### Step 1: Project Setup

Create `composer.json`:

```json
{
  "name": "your-vendor/plugin-pbx-your-provider",
  "type": "project",
  "autoload": {
    "psr-4": {
      "YourVendor\\Plugin\\PBX\\YourProvider\\": "src/"
    }
  },
  "require": {
    "php": "^7.4.0",
    "ext-json": "*",
    "salesrender/plugin-core-pbx": "^0.4.0"
  }
}
```

```bash
composer install
```

Create the directory structure:

```
your-plugin/
  bootstrap.php
  console.php
  public/
    index.php
  src/
    ConfigBuilder.php
    Forms/
      SettingsForm.php
    Parsers/
      CdrApiParser.php
      CdrWebhookParser.php
  db/
  .env
```

### Step 2: Bootstrap Configuration

Create `bootstrap.php` to wire all containers and configuration:

```php
<?php

use Dotenv\Dotenv;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Info\Developer;
use SalesRender\Plugin\Components\Info\Info;
use SalesRender\Plugin\Components\Info\PluginType;
use SalesRender\Plugin\Components\Purpose\PbxPluginClass;
use SalesRender\Plugin\Components\Purpose\PluginEntity;
use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Components\Translations\Translator;
use SalesRender\Plugin\Core\PBX\Components\CDR\CdrParserContainer;
use SalesRender\Plugin\Core\PBX\Components\CDR\CdrPricing;
use SalesRender\Plugin\Core\PBX\Components\Config\ConfigSender;
use YourVendor\Plugin\PBX\YourProvider\ConfigBuilder;
use YourVendor\Plugin\PBX\YourProvider\Forms\SettingsForm;
use YourVendor\Plugin\PBX\YourProvider\Parsers\CdrApiParser;
use YourVendor\Plugin\PBX\YourProvider\Parsers\CdrWebhookParser;
use Medoo\Medoo;
use Money\Money;
use XAKEPEHOK\Path\Path;

# 0. Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

# 1. Configure DB (for SQLite *.db file and parent directory should be writable)
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database' => Path::root()->down('db/database.db')
]));

# 2. Set default language
Translator::config('ru_RU');

# 3. Configure plugin info
Info::config(
    new PluginType(PluginType::PBX),
    fn() => Translator::get('info', 'Your Plugin Name'),
    fn() => Translator::get('info', 'Plugin description in **markdown**'),
    [
        'class'    => PbxPluginClass::CLASS_SIP,
        'entity'   => PluginEntity::ENTITY_UNSPECIFIED,
        'currency' => $_ENV['LV_PLUGIN_PBX_PRICING_CURRENCY'],
        'pricing'  => [
            'pbx'    => 0,
            'record' => 0,
        ],
        'codename' => 'SR_PBX_YOUR_PROVIDER'
    ],
    new Developer(
        'Your Company',
        'support@example.com',
        'example.com',
    )
);

# 4. Configure settings form
Settings::setForm(fn() => new SettingsForm());

# 5. Configure ConfigBuilder and ConfigSender as Settings::addOnSaveHandler()
Settings::addOnSaveHandler(function (Settings $settings) {
    $builder = new ConfigBuilder($settings);
    $sender = new ConfigSender($builder);
    $sender();
});

# 6. Define CDR reward pricing function
CdrPricing::config(function (Money $money) {
    $percent = $_ENV['LV_PLUGIN_PBX_PRICING_REWARD'];
    return $money->multiply($percent)->divide(100);
});

# 7. Configure CDR parsers
CdrParserContainer::config(
    new CdrApiParser(),
    new CdrWebhookParser()
);
```

### Step 3: Implement Required Interfaces

#### 3a. ConfigBuilder

Build the SIP gateway `Config` object from plugin settings:

```php
<?php

namespace YourVendor\Plugin\PBX\YourProvider;

use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Core\PBX\Components\Config\Config;
use SalesRender\Plugin\Core\PBX\Components\Config\ConfigBuilder as ConfigBuilderInterface;

class ConfigBuilder implements ConfigBuilderInterface
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function __invoke(): Config
    {
        $config = new Config();
        $config->username = $this->settings->getData()->get('main.login');
        $config->password = $this->settings->getData()->get('main.password');
        $config->from     = $this->settings->getData()->get('main.from');
        $config->prefix   = $this->settings->getData()->get('main.prefix');
        $config->protocol = $this->settings->getData()->get('advanced.protocol.0');
        $config->domain   = $this->settings->getData()->get('advanced.domain');
        $config->realm    = $this->settings->getData()->get('advanced.realm');
        $config->proxy    = $this->settings->getData()->get('advanced.proxy');
        $config->expires  = $this->settings->getData()->get('advanced.expires');
        $config->register = $this->settings->getData()->get('advanced.register');
        $config->number_format_with_plus = $this->settings->getData()->get('advanced.number_format_with_plus');
        $config->send_additional_data_via_x_headers = $this->settings->getData()->get('advanced.send_additional_data_via_x_headers');
        $config->header_for_DID = $this->settings->getData()->get('advanced.header_for_DID');
        return $config;
    }
}
```

#### 3b. CdrApiParserInterface

Implement CDR polling from the telephony provider API. The `__invoke()` method must return an array of `CDR` objects:

```php
<?php

namespace YourVendor\Plugin\PBX\YourProvider\Parsers;

use SalesRender\Plugin\Core\PBX\Components\CDR\CDR;
use SalesRender\Plugin\Core\PBX\Components\CDR\CdrApiParserInterface;
use SalesRender\Plugin\Core\PBX\Components\CDR\CdrPricing;
use Money\Currency;
use Money\Money;

class CdrApiParser implements CdrApiParserInterface
{
    /**
     * @return CDR[]
     */
    public function __invoke(): array
    {
        // Fetch CDR data from your provider's API
        $providerRecords = $this->fetchFromProviderApi();

        $result = [];
        foreach ($providerRecords as $record) {
            $cdr = new CDR($record['phone']);
            $cdr->callId    = $record['callId'];
            $cdr->timestamp = $record['timestamp'];
            $cdr->duration  = $record['duration'];
            $cdr->recordUri = $record['recordUrl'];
            $cdr->pricing   = new CdrPricing(new Money(
                $record['cost'],
                new Currency($_ENV['LV_PLUGIN_PBX_PRICING_CURRENCY'])
            ));
            $result[] = $cdr;
        }
        return $result;
    }
}
```

#### 3c. CdrWebhookParserInterface

Handle incoming CDR data via webhook. This also acts as a Slim route handler. The `httpMethod()` and `getPattern()` methods define how the route is registered:

```php
<?php

namespace YourVendor\Plugin\PBX\YourProvider\Parsers;

use SalesRender\Plugin\Components\Access\Token\GraphqlInputToken;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Core\PBX\Components\CDR\CDR;
use SalesRender\Plugin\Core\PBX\Components\CDR\CdrPricing;
use SalesRender\Plugin\Core\PBX\Components\CDR\CdrSender;
use SalesRender\Plugin\Core\PBX\Components\CDR\CdrWebhookParserInterface;
use Money\Currency;
use Money\Money;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class CdrWebhookParser implements CdrWebhookParserInterface
{
    public function httpMethod(): string
    {
        return 'POST';
    }

    public function getPattern(): string
    {
        return '/protected/cdr-webhook';
    }

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $result = [];
        foreach ($request->getParsedBody() as $uuid => $data) {
            $cdr = new CDR($data['phone']);
            $cdr->callId    = $uuid;
            $cdr->timestamp = $data['timestamp'];
            $cdr->duration  = $data['duration'];
            $cdr->recordUri = $data['recordUrl'];
            $cdr->pricing   = new CdrPricing(new Money(
                $data['cost'],
                new Currency($_ENV['LV_PLUGIN_PBX_PRICING_CURRENCY'])
            ));
            $result[] = $cdr;
        }

        // Authenticate via X-PLUGIN-TOKEN header
        $jwt = $request->getHeader('X-PLUGIN-TOKEN')[0] ?? '';
        $token = new GraphqlInputToken($jwt);
        Connector::setReference($token->getPluginReference());

        if (Registration::find() === null) {
            throw new \Slim\Exception\HttpException($request, 'Plugin was not registered', 403);
        }

        // Send CDRs to SalesRender backend
        $cdrSender = new CdrSender(...$result);
        $cdrSender();

        return $response->withJson($result);
    }
}
```

### Step 4: Web & Console Entry Points

**`public/index.php`** (HTTP entry point):

```php
<?php

use SalesRender\Plugin\Core\PBX\Factories\WebAppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new WebAppFactory();
$application = $factory->build();
$application->run();
```

**`console.php`** (CLI entry point):

```php
#!/usr/bin/env php
<?php

use SalesRender\Plugin\Core\PBX\Factories\ConsoleAppFactory;

require __DIR__ . '/vendor/autoload.php';

$factory = new ConsoleAppFactory();
$application = $factory->build();
$application->run();
```

### Step 5: .env Configuration

Create a `.env` file in the project root:

```env
LV_PLUGIN_PBX_PRICING_CURRENCY=RUB
LV_PLUGIN_PBX_PRICING_PBX=0
LV_PLUGIN_PBX_PRICING_RECORD=0
LV_PLUGIN_PBX_PRICING_REWARD=20
```

| Variable | Description |
|----------|-------------|
| `LV_PLUGIN_PBX_PRICING_CURRENCY` | Currency code for call pricing (e.g. `RUB`, `USD`) |
| `LV_PLUGIN_PBX_PRICING_PBX` | PBX encryption pricing flag |
| `LV_PLUGIN_PBX_PRICING_RECORD` | Call recording pricing flag |
| `LV_PLUGIN_PBX_PRICING_REWARD` | Reward percentage for CDR pricing calculation |

### Step 6: Deploy

Deploy the plugin as a standard SalesRender plugin:

1. Point the web server document root to `public/`
2. Ensure the `db/` directory is writable for SQLite storage
3. Configure the `.env` file with production values
4. Set up a cron job to run `php console.php cron` every minute

## HTTP Routes

### Inherited from plugin-core

| Method | Path | Description |
|--------|------|-------------|
| POST | `/protected/info` | Plugin information |
| POST | `/protected/settings` | Get/save settings |
| POST | `/protected/registration` | Plugin registration |

### CDR Webhook Routes

Dynamically registered based on `CdrWebhookParserInterface` implementations. Each parser defines its own HTTP method and URL pattern via `httpMethod()` and `getPattern()`.

For example, a parser returning `'POST'` and `'/protected/cdr-webhook'` will register a `POST /protected/cdr-webhook` route.

Multiple webhook parsers can be registered at once (they are passed as variadic arguments to `CdrParserContainer::config()`).

### Special Request Routes (Optional)

If `CallByWebhookAction` is configured via `CallByWebhookContainer::config()`, the core registers:

| Method | Path | Description |
|--------|------|-------------|
| POST | `/protected/special-request/call` | Initiate a call via webhook from the SalesRender backend |

## CLI Commands

The PBX console factory inherits all base commands from `plugin-core`:

| Command | Description |
|---------|-------------|
| `cron` | Run scheduled cron tasks |
| `special-request:send` | Process queued special requests |
| `db:migrate` | Run database migrations |

## Key Classes & Interfaces

### CDR (Call Detail Record)

#### `CDR`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\CDR`

The base call record model. Implements `JsonSerializable`.

| Property | Type | Description |
|----------|------|-------------|
| `$phone` | `string` | Phone number (required, set in constructor) |
| `$callId` | `?string` | Unique call identifier |
| `$timestamp` | `int` | Unix timestamp of the call |
| `$duration` | `int` | Call duration in seconds |
| `$recordUri` | `?string` | URL to the call recording file |
| `$pricing` | `?CdrPricing` | Call pricing information |

#### `CdrPricing`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\CdrPricing`

Handles call cost calculation with provider pricing and reward markup. Uses `moneyphp/money` for monetary values. Implements `JsonSerializable`.

| Method | Returns | Description |
|--------|---------|-------------|
| `__construct(Money $providerPricing)` | -- | Set the provider's cost for the call |
| `getProviderPricing()` | `Money` | Get the provider cost |
| `getRewardPricing()` | `Money` | Get the calculated reward (uses the callable set via `config()`) |
| `static config(callable $rewardCalc)` | `void` | Set the reward calculation function `fn(Money): Money` |

JSON output includes both `provider` and `reward` pricing objects.

#### `CdrTiming`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\Components\CdrTiming`

Detailed call timing information.

| Property | Type | Description |
|----------|------|-------------|
| `$start` | `int` | Call start time (unix timestamp) |
| `$duration` | `int` | Total call duration in seconds |
| `$earlyMediaDuration` | `int` | Early media (ringing) duration in seconds |

#### `Direction`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\Components\Direction`

Call direction enum. Extends `EnumHelper`.

| Constant | Value |
|----------|-------|
| `Direction::INCOMING` | `'incoming'` |
| `Direction::OUTGOING` | `'outgoing'` |

#### `Reference`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\Components\Reference`

Identifies a call party (caller, callee, or plugin). Implements `JsonSerializable`.

| Property | Type | Description |
|----------|------|-------------|
| `$alias` | `string` | Human-readable alias |
| `$id` | `string` | Unique identifier |

#### `SipCause`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\Components\SipCause`

SIP response code with human-readable phrase.

| Property | Type | Description |
|----------|------|-------------|
| `$code` | `int` | SIP status code (e.g. 200, 486, 503) |
| `$phrase` | `string` | Status phrase (e.g. "OK", "Busy Here") |

### CDR Webhook

#### `CdrWebhook`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\Webhook\CdrWebhook`

Extended CDR model for webhook-based call records. Extends `CDR` with additional SIP-specific fields.

| Property | Type | Description |
|----------|------|-------------|
| `$timing` | `CdrTiming` | Detailed timing information |
| `$direction` | `Direction` | Call direction (incoming/outgoing) |
| `$sipCause` | `SipCause` | SIP failure cause |
| `$hangupCause` | `SipCause` | Hangup cause |
| `$caller` | `Reference` | Caller reference |
| `$callee` | `Reference` | Callee reference |
| `$plugin` | `Reference` | Plugin reference |

JSON serialization includes all timing, SIP, and party fields.

#### `CdrWebhookSender`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\Webhook\CdrWebhookSender`

Sends `CdrWebhook` records to the SalesRender backend via special request (HTTP PUT to `/CRM/plugin/pbx/cdr`).

```php
$sender = new CdrWebhookSender($cdrWebhook1, $cdrWebhook2);
$sender(); // Queues a SpecialRequestTask
```

### Parser Interfaces & Container

#### `CdrApiParserInterface`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\CdrApiParserInterface`

| Method | Returns | Description |
|--------|---------|-------------|
| `__invoke()` | `CDR[]` | Fetch and parse CDR records from the provider API |

#### `CdrWebhookParserInterface`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\CdrWebhookParserInterface`

| Method | Returns | Description |
|--------|---------|-------------|
| `httpMethod()` | `string` | HTTP method for the webhook route (e.g. `'POST'`, `'PUT'`) |
| `getPattern()` | `string` | URL pattern for the webhook route (e.g. `'/protected/cdr-webhook'`) |
| `__invoke(ServerRequest, Response, array)` | `Response` | Handle the webhook request and return an HTTP response |

#### `CdrParserContainer`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\CdrParserContainer`

Static container that holds CDR parser instances. Must be configured in `bootstrap.php`.

| Method | Description |
|--------|-------------|
| `static config(?CdrApiParserInterface, CdrWebhookParserInterface ...)` | Register an API parser and zero or more webhook parsers |
| `static getApiParser(): CdrApiParserInterface` | Get the registered API parser |
| `static getWebhookParsers(): CdrWebhookParserInterface[]` | Get all registered webhook parsers |

### CDR Sender

#### `CdrSender`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\CDR\CdrSender`

Sends basic `CDR` records to SalesRender backend via special request (HTTP PATCH to `/CRM/plugin/pbx/cdr`). The CDR data is signed with a JWT token (24-hour TTL).

```php
$sender = new CdrSender($cdr1, $cdr2, $cdr3);
$sender(); // Creates and saves a SpecialRequestTask
```

### Config

#### `Config`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\Config\Config`

SIP gateway configuration model. Implements `JsonSerializable`. All properties are public and writable.

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$username` | `string` | `''` | SIP account username |
| `$password` | `string` | `''` | SIP account password |
| `$from` | `string` | `''` | Outgoing caller number |
| `$prefix` | `string` | `''` | Dialing prefix |
| `$protocol` | `string` | `'udp'` | SIP transport protocol (`udp` or `tcp`) |
| `$domain` | `string` | `''` | SIP domain |
| `$realm` | `string` | `''` | SIP realm |
| `$proxy` | `string` | `''` | SIP proxy address |
| `$header_for_DID` | `string` | `''` | Header name where the provider passes the DID (A-number) |
| `$expires` | `int` | `600` | Registration expiry time in seconds |
| `$register` | `bool` | `false` | Whether SIP registration is required |
| `$number_format_with_plus` | `bool` | `true` | Prepend `+` to phone numbers |
| `$send_additional_data_via_x_headers` | `bool` | `false` | Send extra data in SIP X-headers |
| `$record` | `bool` | `true` | Enable call recording |
| `$map_phone_to_gateway` | `bool` | `false` | Map phone numbers to gateway |
| `$originate_timeout` | `int` | `60` | Call originate timeout in seconds |

JSON serialization also includes `company_id`, `plugin_id`, and `plugin_alias` from the current plugin reference.

#### `ConfigBuilder`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\Config\ConfigBuilder`

Interface that the plugin developer must implement to build a `Config` from current plugin `Settings`.

| Method | Returns | Description |
|--------|---------|-------------|
| `__construct(Settings $settings)` | -- | Receive current plugin settings |
| `__invoke()` | `Config` | Build and return the Config object |

#### `ConfigSender`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\Config\ConfigSender`

Sends the gateway configuration to the SalesRender backend via special request (HTTP PUT to `/CRM/plugin/pbx/gateway`). Typically used inside `Settings::addOnSaveHandler()`.

```php
Settings::addOnSaveHandler(function (Settings $settings) {
    $builder = new ConfigBuilder($settings);
    $sender = new ConfigSender($builder);
    $sender(); // Builds config, signs it with JWT, and queues the request
});
```

### Webhook (Call Initiation)

#### `CallByWebhookAction`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\Webhook\CallByWebhookAction`

Abstract class extending `SpecialRequestAction`. Implement this to handle call initiation requests from the SalesRender backend. The special request action name is `'call'`.

Your implementation must override the `handle()` method to perform the actual call via the telephony provider API.

#### `CallByWebhookContainer`

**Namespace:** `SalesRender\Plugin\Core\PBX\Components\Webhook\CallByWebhookContainer`

Optional static container for the call-by-webhook action. If not configured, no call initiation route is registered.

| Method | Description |
|--------|-------------|
| `static config(?CallByWebhookAction)` | Register the action (pass `null` to disable) |
| `static getCallByWebhookAction(): ?CallByWebhookAction` | Get the registered action or `null` |

## Special Requests

PBX plugins use special requests to communicate asynchronously with the SalesRender backend:

| Sender Class | HTTP Method | Backend Endpoint | TTL | Description |
|-------------|-------------|----------|-----|-------------|
| `CdrSender` | PATCH | `/CRM/plugin/pbx/cdr` | 24h | Send basic CDR records |
| `CdrWebhookSender` | PUT | `/CRM/plugin/pbx/cdr` | 24h | Send extended webhook CDR records |
| `ConfigSender` | PUT | `/CRM/plugin/pbx/gateway` | 24h | Send SIP gateway configuration |

All special requests are signed with a JWT token and queued as `SpecialRequestTask` entries in the database, then dispatched by the `special-request:send` CLI command.

## Example Plugin

A complete working example is available at [`salesrender/plugin-pbx-example`](https://github.com/SalesRender/plugin-pbx-example).

The example demonstrates:

- **`CdrApiParser`** -- generates random CDR records for testing, with pricing using `CdrPricing` and `Money`
- **`CdrWebhookParser`** -- parses CDR data from a POST webhook at `/protected/cdr-webhook`, authenticates via `X-PLUGIN-TOKEN` header, sends CDRs through `CdrSender`
- **`ConfigBuilder`** -- maps all settings form fields to the SIP `Config` properties
- **`SettingsForm`** -- complete settings form with main fields (login, password, from, prefix) and advanced fields (protocol, domain, realm, proxy, expires, register, number format, X-headers, DID header)

## Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| [`salesrender/plugin-core`](https://github.com/SalesRender/plugin-core) | ^0.4.0 | Base plugin framework |
| `moneyphp/money` | ^3.3 | Monetary value handling for CDR pricing |
| [`salesrender/plugin-component-purpose`](https://github.com/SalesRender/plugin-component-purpose) | ^2.0 | Plugin type and purpose definitions (`PbxPluginClass`, `PluginEntity`) |

## See Also

- [salesrender/plugin-core](https://github.com/SalesRender/plugin-core) -- Base plugin framework
- [salesrender/plugin-pbx-example](https://github.com/SalesRender/plugin-pbx-example) -- Complete PBX plugin example
- [salesrender/plugin-core-logistic](https://github.com/SalesRender/plugin-core-logistic) -- Logistic plugin core
