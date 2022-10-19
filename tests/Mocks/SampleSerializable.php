<?php

namespace Kolossal\Multiplex\Tests\Mocks;

use Serializable;

class SampleSerializable implements Serializable
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function serialize()
    {
        return serialize($this->data);
    }

    public function unserialize($serialized)
    {
        $this->data = unserialize($serialized);
    }

    public function __serialize(): array
    {
        return $this->data;
    }

    public function __unserialize(array $data)
    {
        return $this->data = $data;
    }
}
