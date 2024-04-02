<?php

namespace SalesRender\Plugin\Core\PBX\Components\CDR\Components;

use JsonSerializable;

class Reference implements JsonSerializable
{
    public string $alias;
    public string $id;

    public function __construct(string $alias, string $id)
    {
        $this->alias = $alias;
        $this->id = $id;
    }

    public function jsonSerialize(): array
    {
        return [
            'alias' => $this->alias,
            'id' => $this->id,
        ];
    }

}