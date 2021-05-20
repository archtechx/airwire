<?php

namespace Airwire\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Encode
{
    public function __construct(
        public string|null $property = null,
        public string|null $method = null,
        public string|null $function = null,
     ) {}

    public function encode(mixed $value): mixed
    {
        if ($this->property && isset($value->${$this->property})) {
            return $value->{$this->property};
        }

        if ($this->method && method_exists($value, $this->method)) {
            return $value->{$this->method}();
        }

        if ($this->function && function_exists($this->function)) {
            return ($this->function)($value);
        }

        return null;
    }
}
