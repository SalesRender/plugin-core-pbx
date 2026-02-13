# Plugin Core PBX

> Базовый фреймворк для PBX-плагинов (телефонии) SalesRender

[English version](README.MD)

## Обзор

`salesrender/plugin-core-pbx` -- специализированная библиотека, расширяющая `salesrender/plugin-core` для разработки PBX-плагинов (Private Branch Exchange) в экосистеме SalesRender.

PBX-плагины обеспечивают интеграцию SalesRender с VoIP/SIP-провайдерами телефонии и реализуют:

- **CDR (Call Detail Records)** -- разбор записей о звонках из внешнего API провайдера или через webhook с последующей передачей в бэкенд SalesRender
- **Конфигурация шлюза** -- построение и отправка конфигурации SIP-шлюза (учётные данные, маршрутизация, настройки протокола) в SalesRender
- **Инициация звонков через webhook** -- запуск исходящих звонков по запросу от платформы SalesRender через механизм special requests

## Установка

```bash
composer require salesrender/plugin-core-pbx
```

### Требования

- PHP >= 7.4
- ext-json
- `salesrender/plugin-core` ^0.4.0
- `moneyphp/money` ^3.3
- `salesrender/plugin-component-purpose` ^2.0

## Архитектура

### Расширение plugin-core

`plugin-core-pbx` расширяет базовый фреймворк `plugin-core`, предоставляя две специализированные фабрики:

1. **WebAppFactory** (`SalesRender\Plugin\Core\PBX\Factories\WebAppFactory`) расширяет базовую `WebAppFactory` и автоматически:
   - Регистрирует все webhook-парсеры из `CdrParserContainer` как HTTP-маршруты (каждый `CdrWebhookParserInterface` определяет собственный метод и URL)
   - Регистрирует опциональный `CallByWebhookAction` как special request action для инициации звонков
   - Добавляет поддержку CORS

2. **ConsoleAppFactory** (`SalesRender\Plugin\Core\PBX\Factories\ConsoleAppFactory`) расширяет базовую консольную фабрику, наследуя все стандартные CLI-команды.

### Что должен реализовать разработчик

| Интерфейс / Класс | Назначение |
|--------------------|------------|
| `CdrApiParserInterface` | Разбор CDR-записей при поллинге API провайдера телефонии |
| `CdrWebhookParserInterface` | Разбор CDR-записей, полученных через webhook от провайдера |
| `ConfigBuilder` | Построение конфигурации SIP-шлюза из настроек плагина |
| `CallByWebhookAction` (опционально) | Обработка запросов на инициацию звонков от SalesRender |

### Порядок конфигурации в bootstrap

Файл `bootstrap.php` связывает все компоненты (см. `bootstrap.example.php` в репозитории):

1. Настройка подключения к БД (`Connector::config`)
2. Установка языка по умолчанию (`Translator::config`)
3. Настройка информации о плагине (`Info::config` с `PluginType::PBX`)
4. Настройка формы настроек (`Settings::setForm`)
5. Настройка autocomplete (опционально)
6. Настройка `ConfigBuilder` + `ConfigSender` как обработчика сохранения настроек
7. Определение функции расчёта вознаграждения CDR (`CdrPricing::config`)
8. Регистрация CDR-парсеров (`CdrParserContainer::config`)
9. Регистрация `CallByWebhookAction` (опционально, для PBX Webhook-типа плагина)

## Начало работы: Создание PBX-плагина

### Шаг 1: Настройка проекта

Создайте `composer.json`:

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

Создайте структуру каталогов:

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

### Шаг 2: Конфигурация bootstrap

Создайте `bootstrap.php` для связывания всех контейнеров и конфигурации:

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

# 0. Загрузка переменных окружения
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

# 1. Настройка БД (для SQLite файл *.db и родительская директория должны быть доступны для записи)
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Установка языка по умолчанию
Translator::config('ru_RU');

# 3. Информация о плагине
Info::config(
    new PluginType(PluginType::PBX),
    fn() => Translator::get('info', 'Название вашего плагина'),
    fn() => Translator::get('info', 'Описание плагина в **markdown**'),
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
        'Ваша компания',
        'support@example.com',
        'example.com',
    )
);

# 4. Форма настроек
Settings::setForm(fn() => new SettingsForm());

# 5. Отправка конфигурации при сохранении настроек
Settings::addOnSaveHandler(function (Settings $settings) {
    $builder = new ConfigBuilder($settings);
    $sender = new ConfigSender($builder);
    $sender();
});

# 6. Функция расчёта вознаграждения за CDR
CdrPricing::config(function (Money $money) {
    $percent = $_ENV['LV_PLUGIN_PBX_PRICING_REWARD'];
    return $money->multiply($percent)->divide(100);
});

# 7. Регистрация CDR-парсеров
CdrParserContainer::config(
    new CdrApiParser(),
    new CdrWebhookParser()
);
```

### Шаг 3: Реализация обязательных интерфейсов

#### 3a. ConfigBuilder

Построение объекта `Config` SIP-шлюза из настроек плагина:

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

Реализация получения CDR через поллинг API провайдера. Метод `__invoke()` должен возвращать массив объектов `CDR`:

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
        // Получение данных CDR из API вашего провайдера
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

Обработка входящих CDR-данных через webhook. Класс одновременно является обработчиком маршрута Slim. Методы `httpMethod()` и `getPattern()` определяют параметры HTTP-маршрута:

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

        // Аутентификация через заголовок X-PLUGIN-TOKEN
        $jwt = $request->getHeader('X-PLUGIN-TOKEN')[0] ?? '';
        $token = new GraphqlInputToken($jwt);
        Connector::setReference($token->getPluginReference());

        if (Registration::find() === null) {
            throw new \Slim\Exception\HttpException($request, 'Plugin was not registered', 403);
        }

        // Отправка CDR в бэкенд SalesRender
        $cdrSender = new CdrSender(...$result);
        $cdrSender();

        return $response->withJson($result);
    }
}
```

### Шаг 4: Точки входа Web и Console

**`public/index.php`** (HTTP-точка входа):

```php
<?php

use SalesRender\Plugin\Core\PBX\Factories\WebAppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new WebAppFactory();
$application = $factory->build();
$application->run();
```

**`console.php`** (CLI-точка входа):

```php
#!/usr/bin/env php
<?php

use SalesRender\Plugin\Core\PBX\Factories\ConsoleAppFactory;

require __DIR__ . '/vendor/autoload.php';

$factory = new ConsoleAppFactory();
$application = $factory->build();
$application->run();
```

### Шаг 5: Настройка .env

Создайте файл `.env` в корне проекта:

```env
LV_PLUGIN_PBX_PRICING_CURRENCY=RUB
LV_PLUGIN_PBX_PRICING_PBX=0
LV_PLUGIN_PBX_PRICING_RECORD=0
LV_PLUGIN_PBX_PRICING_REWARD=20
```

| Переменная | Описание |
|------------|----------|
| `LV_PLUGIN_PBX_PRICING_CURRENCY` | Код валюты для тарификации звонков (например, `RUB`, `USD`) |
| `LV_PLUGIN_PBX_PRICING_PBX` | Флаг тарификации шифрования PBX |
| `LV_PLUGIN_PBX_PRICING_RECORD` | Флаг тарификации записи звонков |
| `LV_PLUGIN_PBX_PRICING_REWARD` | Процент вознаграждения при расчёте CDR pricing |

### Шаг 6: Развёртывание

Развёртывание плагина выполняется стандартным образом:

1. Укажите document root веб-сервера на директорию `public/`
2. Убедитесь, что директория `db/` доступна для записи (для SQLite)
3. Настройте `.env`-файл с продакшен-значениями
4. Добавьте cron-задачу для запуска `php console.php cron` каждую минуту

## HTTP-маршруты

### Унаследованные от plugin-core

| Метод | Путь | Описание |
|-------|------|----------|
| POST | `/protected/info` | Информация о плагине |
| POST | `/protected/settings` | Получение/сохранение настроек |
| POST | `/protected/registration` | Регистрация плагина |

### Маршруты CDR Webhook

Регистрируются динамически на основе реализаций `CdrWebhookParserInterface`. Каждый парсер определяет собственный HTTP-метод и URL-паттерн через методы `httpMethod()` и `getPattern()`.

Например, парсер, возвращающий `'POST'` и `'/protected/cdr-webhook'`, зарегистрирует маршрут `POST /protected/cdr-webhook`.

Допускается регистрация нескольких webhook-парсеров одновременно (передаются как variadic-аргументы в `CdrParserContainer::config()`).

### Маршруты Special Request (опционально)

Если `CallByWebhookAction` настроен через `CallByWebhookContainer::config()`, ядро регистрирует:

| Метод | Путь | Описание |
|-------|------|----------|
| POST | `/protected/special-request/call` | Инициация звонка через webhook из бэкенда SalesRender |

## CLI-команды

Консольная фабрика PBX наследует все базовые команды из `plugin-core`:

| Команда | Описание |
|---------|----------|
| `cron` | Запуск плановых cron-задач |
| `special-request:send` | Обработка очереди special requests |
| `db:migrate` | Выполнение миграций базы данных |

## Основные классы и интерфейсы

### CDR (Call Detail Record)

#### `CDR`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\CDR`

Базовая модель записи о звонке. Реализует `JsonSerializable`.

| Свойство | Тип | Описание |
|----------|-----|----------|
| `$phone` | `string` | Номер телефона (обязательный, передаётся в конструкторе) |
| `$callId` | `?string` | Уникальный идентификатор звонка |
| `$timestamp` | `int` | Unix-время звонка |
| `$duration` | `int` | Длительность звонка в секундах |
| `$recordUri` | `?string` | URL файла записи звонка |
| `$pricing` | `?CdrPricing` | Информация о тарификации звонка |

#### `CdrPricing`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\CdrPricing`

Расчёт стоимости звонка с учётом тарифа провайдера и надбавки вознаграждения. Использует `moneyphp/money`. Реализует `JsonSerializable`.

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `__construct(Money $providerPricing)` | -- | Установка стоимости звонка у провайдера |
| `getProviderPricing()` | `Money` | Получить стоимость провайдера |
| `getRewardPricing()` | `Money` | Получить рассчитанное вознаграждение (используется callable из `config()`) |
| `static config(callable $rewardCalc)` | `void` | Установить функцию расчёта вознаграждения `fn(Money): Money` |

JSON-вывод содержит объекты `provider` и `reward`.

#### `CdrTiming`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\Components\CdrTiming`

Детальная информация о тайминге звонка.

| Свойство | Тип | Описание |
|----------|-----|----------|
| `$start` | `int` | Время начала звонка (unix timestamp) |
| `$duration` | `int` | Общая длительность звонка в секундах |
| `$earlyMediaDuration` | `int` | Длительность early media (гудки) в секундах |

#### `Direction`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\Components\Direction`

Перечисление направления звонка. Расширяет `EnumHelper`.

| Константа | Значение |
|-----------|----------|
| `Direction::INCOMING` | `'incoming'` |
| `Direction::OUTGOING` | `'outgoing'` |

#### `Reference`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\Components\Reference`

Идентификация участника звонка (вызывающий, вызываемый или плагин). Реализует `JsonSerializable`.

| Свойство | Тип | Описание |
|----------|-----|----------|
| `$alias` | `string` | Человекочитаемый псевдоним |
| `$id` | `string` | Уникальный идентификатор |

#### `SipCause`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\Components\SipCause`

SIP-код ответа с текстовым описанием.

| Свойство | Тип | Описание |
|----------|-----|----------|
| `$code` | `int` | SIP-код статуса (например, 200, 486, 503) |
| `$phrase` | `string` | Текстовое описание статуса (например, "OK", "Busy Here") |

### CDR Webhook

#### `CdrWebhook`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\Webhook\CdrWebhook`

Расширенная модель CDR для записей, полученных через webhook. Наследует `CDR` и добавляет SIP-специфичные поля.

| Свойство | Тип | Описание |
|----------|-----|----------|
| `$timing` | `CdrTiming` | Детальная информация о тайминге |
| `$direction` | `Direction` | Направление звонка (входящий/исходящий) |
| `$sipCause` | `SipCause` | SIP-причина ошибки |
| `$hangupCause` | `SipCause` | Причина завершения звонка |
| `$caller` | `Reference` | Идентификация вызывающего абонента |
| `$callee` | `Reference` | Идентификация вызываемого абонента |
| `$plugin` | `Reference` | Идентификация плагина |

#### `CdrWebhookSender`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\Webhook\CdrWebhookSender`

Отправка записей `CdrWebhook` в бэкенд SalesRender через special request (HTTP PUT на `/CRM/plugin/pbx/cdr`).

```php
$sender = new CdrWebhookSender($cdrWebhook1, $cdrWebhook2);
$sender(); // Создаёт SpecialRequestTask в очереди
```

### Интерфейсы парсеров и контейнер

#### `CdrApiParserInterface`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\CdrApiParserInterface`

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `__invoke()` | `CDR[]` | Получить и разобрать CDR-записи из API провайдера |

#### `CdrWebhookParserInterface`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\CdrWebhookParserInterface`

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `httpMethod()` | `string` | HTTP-метод для маршрута webhook (например, `'POST'`, `'PUT'`) |
| `getPattern()` | `string` | URL-паттерн для маршрута webhook (например, `'/protected/cdr-webhook'`) |
| `__invoke(ServerRequest, Response, array)` | `Response` | Обработка webhook-запроса и возврат HTTP-ответа |

#### `CdrParserContainer`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\CdrParserContainer`

Статический контейнер для экземпляров CDR-парсеров. Должен быть сконфигурирован в `bootstrap.php`.

| Метод | Описание |
|-------|----------|
| `static config(?CdrApiParserInterface, CdrWebhookParserInterface ...)` | Зарегистрировать API-парсер и ноль или более webhook-парсеров |
| `static getApiParser(): CdrApiParserInterface` | Получить зарегистрированный API-парсер |
| `static getWebhookParsers(): CdrWebhookParserInterface[]` | Получить все зарегистрированные webhook-парсеры |

### CDR Sender

#### `CdrSender`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\CDR\CdrSender`

Отправка базовых `CDR`-записей в бэкенд SalesRender через special request (HTTP PATCH на `/CRM/plugin/pbx/cdr`). Данные подписываются JWT-токеном с TTL 24 часа.

```php
$sender = new CdrSender($cdr1, $cdr2, $cdr3);
$sender(); // Создаёт и сохраняет SpecialRequestTask
```

### Config

#### `Config`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\Config\Config`

Модель конфигурации SIP-шлюза. Реализует `JsonSerializable`. Все свойства публичные.

| Свойство | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `$username` | `string` | `''` | Имя пользователя SIP-аккаунта |
| `$password` | `string` | `''` | Пароль SIP-аккаунта |
| `$from` | `string` | `''` | Исходящий номер |
| `$prefix` | `string` | `''` | Префикс набора |
| `$protocol` | `string` | `'udp'` | Транспортный протокол SIP (`udp` или `tcp`) |
| `$domain` | `string` | `''` | SIP-домен |
| `$realm` | `string` | `''` | SIP realm |
| `$proxy` | `string` | `''` | Адрес SIP-прокси |
| `$header_for_DID` | `string` | `''` | Имя заголовка, в котором провайдер передаёт DID (А-номер) |
| `$expires` | `int` | `600` | Время жизни регистрации в секундах |
| `$register` | `bool` | `false` | Требуется ли SIP-регистрация |
| `$number_format_with_plus` | `bool` | `true` | Передавать номера с символом `+` |
| `$send_additional_data_via_x_headers` | `bool` | `false` | Отправлять дополнительные данные в SIP X-заголовках |
| `$record` | `bool` | `true` | Включить запись звонков |
| `$map_phone_to_gateway` | `bool` | `false` | Маппинг номеров на шлюз |
| `$originate_timeout` | `int` | `60` | Таймаут инициации звонка в секундах |

JSON-сериализация также включает `company_id`, `plugin_id` и `plugin_alias` из текущего plugin reference.

#### `ConfigBuilder`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\Config\ConfigBuilder`

Интерфейс, который разработчик плагина должен реализовать для построения `Config` из текущих `Settings`.

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `__construct(Settings $settings)` | -- | Получение текущих настроек плагина |
| `__invoke()` | `Config` | Построить и вернуть объект Config |

#### `ConfigSender`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\Config\ConfigSender`

Отправка конфигурации шлюза в бэкенд SalesRender через special request (HTTP PUT на `/CRM/plugin/pbx/gateway`). Обычно используется внутри `Settings::addOnSaveHandler()`.

```php
Settings::addOnSaveHandler(function (Settings $settings) {
    $builder = new ConfigBuilder($settings);
    $sender = new ConfigSender($builder);
    $sender(); // Собирает конфиг, подписывает JWT и ставит запрос в очередь
});
```

### Webhook (инициация звонков)

#### `CallByWebhookAction`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\Webhook\CallByWebhookAction`

Абстрактный класс, расширяющий `SpecialRequestAction`. Реализуйте его для обработки запросов на инициацию звонков от бэкенда SalesRender. Имя special request action -- `'call'`.

Ваша реализация должна переопределить метод `handle()` для выполнения фактического звонка через API провайдера телефонии.

#### `CallByWebhookContainer`

**Пространство имён:** `SalesRender\Plugin\Core\PBX\Components\Webhook\CallByWebhookContainer`

Опциональный статический контейнер для action инициации звонка. Если не сконфигурирован, маршрут инициации звонков не регистрируется.

| Метод | Описание |
|-------|----------|
| `static config(?CallByWebhookAction)` | Зарегистрировать action (передайте `null` для отключения) |
| `static getCallByWebhookAction(): ?CallByWebhookAction` | Получить зарегистрированный action или `null` |

## Special Requests

PBX-плагины используют special requests для асинхронного взаимодействия с бэкендом SalesRender:

| Класс-отправитель | HTTP-метод | Endpoint бэкенда | TTL | Описание |
|-------------------|------------|------------------|-----|----------|
| `CdrSender` | PATCH | `/CRM/plugin/pbx/cdr` | 24ч | Отправка базовых CDR-записей |
| `CdrWebhookSender` | PUT | `/CRM/plugin/pbx/cdr` | 24ч | Отправка расширенных webhook CDR-записей |
| `ConfigSender` | PUT | `/CRM/plugin/pbx/gateway` | 24ч | Отправка конфигурации SIP-шлюза |

Все special requests подписываются JWT-токеном и сохраняются как записи `SpecialRequestTask` в БД, после чего отправляются CLI-командой `special-request:send`.

## Пример плагина

Полный рабочий пример доступен в [`salesrender/plugin-pbx-example`](https://github.com/SalesRender/plugin-pbx-example).

Пример демонстрирует:

- **`CdrApiParser`** -- генерация случайных CDR-записей для тестирования с тарификацией через `CdrPricing` и `Money`
- **`CdrWebhookParser`** -- разбор CDR из POST-webhook на `/protected/cdr-webhook`, аутентификация через заголовок `X-PLUGIN-TOKEN`, отправка CDR через `CdrSender`
- **`ConfigBuilder`** -- маппинг всех полей формы настроек на свойства SIP `Config`
- **`SettingsForm`** -- полная форма настроек с основными полями (логин, пароль, исходящий номер, префикс) и расширенными полями (протокол, домен, realm, прокси, expires, регистрация, формат номера, X-заголовки, заголовок DID)

## Зависимости

| Пакет | Версия | Назначение |
|-------|--------|------------|
| [`salesrender/plugin-core`](https://github.com/SalesRender/plugin-core) | ^0.4.0 | Базовый фреймворк плагинов |
| `moneyphp/money` | ^3.3 | Работа с денежными значениями для тарификации CDR |
| [`salesrender/plugin-component-purpose`](https://github.com/SalesRender/plugin-component-purpose) | ^2.0 | Определения типов и назначений плагинов (`PbxPluginClass`, `PluginEntity`) |

## Смотрите также

- [salesrender/plugin-core](https://github.com/SalesRender/plugin-core) -- Базовый фреймворк плагинов
- [salesrender/plugin-pbx-example](https://github.com/SalesRender/plugin-pbx-example) -- Полный пример PBX-плагина
- [salesrender/plugin-core-logistic](https://github.com/SalesRender/plugin-core-logistic) -- Ядро для логистических плагинов
