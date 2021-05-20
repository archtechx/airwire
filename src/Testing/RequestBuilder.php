<?php

namespace Airwire\Testing;

use Airwire\Airwire;
use Airwire\Component;
use Airwire\Http\AirwireController;

class RequestBuilder
{
    public function __construct(
        public string $alias,
    ) {}

    public string $target = 'test';

    public array $state = [];
    public array $changes = [];
    public array $calls = [];

    public function state(array $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function call(string $method, mixed ...$arguments): static
    {
        $this->calls[$method] = $arguments;

        $this->target = $method;

        return $this;
    }

    public function changes(array $changes): static
    {
        $this->changes = $changes;

        return $this;
    }

    public function change(string $key, mixed $value): static
    {
        $this->changes[$key] = $value;

        $this->target = $key;

        return $this;
    }

    public function send(): AirwireResponse
    {
        return new AirwireResponse((new AirwireController)->response($this->alias, [
            'state' => $this->state,
            'changes' => $this->changes,
            'calls' => $this->calls,
        ], $this->target));
    }

    public function hydrate(): Component
    {
        return (new AirwireController)->makeComponent($this->alias, $this->state)
            ->handle($this->changes, $this->calls, $this->target);
    }
}
