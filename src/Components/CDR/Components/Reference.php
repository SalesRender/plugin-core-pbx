<?php

namespace SalesRender\Plugin\Core\PBX\Components\CDR\Components;

use JsonSerializable;

class Reference implements JsonSerializable
{
    private string $alias;
    private string $id;

    public function __construct(string $alias, string $id)
    {
        $this->alias = $alias;
        $this->id = $id;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function jsonSerialize(): array
    {
        return [
            'alias' => $this->getAlias(),
            'id' => $this->getId(),
        ];
    }

}