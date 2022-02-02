<?php

declare(strict_types=1);

namespace Airwire\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::TARGET_METHOD)]
class Wired
{
    public function __construct(
        public mixed $default = null,
        public bool $readonly = false,
        public string|null $type = null,
    ) {
    }
}
