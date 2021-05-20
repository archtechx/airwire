<?php

namespace Airwire\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class Wired
{
    public function __construct(
        public mixed $default = null,
        public bool $readonly = false,
        public string|null $type = null,
    ) {}
}
