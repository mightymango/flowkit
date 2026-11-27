<?php

namespace Act\Flowkit\Exceptions;

use InvalidArgumentException;

class InvalidDefinition extends InvalidArgumentException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid flow definition: '.implode(' ', $errors));
    }
}
