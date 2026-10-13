<?php

namespace Kolossal\Multiplex\Exceptions;

use Exception;
use Kolossal\Multiplex\Meta;

final class MetaException extends Exception
{
    public static function invalidKey(string $key): self
    {
        return new self("Meta key `{$key}` is not a valid key.");
    }

    public static function modelAttribute(string $key): self
    {
        return new self("Meta key `{$key}` seems to be a model attribute. You must explicitly allow this attribute via `\$metaKeys`.");
    }

    public static function invalidModel(): self
    {
        return new self('The configured `multiplex.model` must be a subclass of `' . Meta::class . '`.');
    }
}
