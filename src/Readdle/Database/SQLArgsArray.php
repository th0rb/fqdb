<?php

namespace Readdle\Database;

class SQLArgsArray implements \JsonSerializable
{

    private $argsArray;

    public function __construct(array $argsArray)
    {
        $this->argsArray = [];

        foreach ($argsArray as $arg) {
            if (is_null($arg)) {
                $this->argsArray[] = new SQLValueNull();
            } else {
                $this->argsArray[] = $arg;
            }
        }

    }

    public function toArray()
    {
        return $this->argsArray;
    }

    public function jsonSerialize()
    {
        return $this->argsArray;
    }
}
