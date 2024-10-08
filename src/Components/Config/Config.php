<?php
/**
 * Created for plugin-core-pbx
 * Date: 09.07.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\PBX\Components\Config;

use JsonSerializable;
use SalesRender\Plugin\Components\Db\Components\Connector;

class Config implements JsonSerializable
{

    public string $username = '';
    public string $password = '';
    public string $from = '';
    public string $prefix = '';
    public string $protocol = 'udp';
    public string $domain = '';
    public string $realm = '';
    public string $proxy = '';
    public string $header_for_DID = '';
    public int $expires = 600;
    public bool $register = false;
    public bool $number_format_with_plus = true;
    public bool $send_additional_data_via_x_headers = false;
    public bool $record = true;

    public int $originate_timeout = 60;

    public function jsonSerialize(): array
    {
        return [
            'company_id' => Connector::getReference()->getCompanyId(),
            'plugin_id' => Connector::getReference()->getId(),
            'plugin_alias' => Connector::getReference()->getAlias(),
            'username' => $this->username,
            'password' => $this->password,
            'from' => $this->from,
            'prefix' => $this->prefix,
            'protocol' => $this->protocol,
            'domain' => $this->domain,
            'realm' => $this->realm,
            'proxy' => $this->proxy,
            'expires' => $this->expires,
            'need_registration' => $this->register,
            'number_format_with_plus' => $this->number_format_with_plus,
            'send_additional_data_via_x_headers' => $this->send_additional_data_via_x_headers,
            'record' => $this->record,
            'header_for_DID' => $this->header_for_DID,
            'originate_timeout' => $this->originate_timeout,
        ];
    }
}