<?php

namespace Act\Flowkit\Exceptions;

use InvalidArgumentException;

class UnknownBrick extends InvalidArgumentException
{
    public static function key(string $key): self
    {
        return new self("No brick is registered with the key [{$key}].");
    }
}
