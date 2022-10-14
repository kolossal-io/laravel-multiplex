<?php

namespace Kolossal\Meta\Exceptions;

use Exception;

class MetaException extends Exception
{
    public static function unknownKey(string $key): self
    {
        return new static("Meta key '{$key}' is not allowed.");
    }
}
