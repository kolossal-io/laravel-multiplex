<?php

namespace Kolossal\Meta\Exceptions;

use Exception;

final class MetaException extends Exception
{
    public static function invalidKey(string $key): self
    {
        return new static("Meta key `{$key}` is not a valid key.");
    }

    public static function modelAttribute(string $key): self
    {
        return new static("Meta key `{$key}` seems to be a model attribute. You must explicitly allow this attribute via `\$metaKeys`.");
    }
}
