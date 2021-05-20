<?php

namespace Airwire;

use Airwire\Testing\RequestBuilder;

abstract class Component
{
    use Concerns\ManagesState,
        Concerns\ManagesActions,
        Concerns\ManagesLifecycle,
        Concerns\ManagesValidation;

    public array $requestState;
    public string $requestTarget;
    public array $changes;
    public array $calls;

    public function __construct(array $state)
    {
        foreach ($this->getSharedProperties() as $property) {
            if (isset($state[$property]) && ! $this->isReadonly($property)) {
                $this->$property = Airwire::decode([$this, $property], $state[$property]);
            } else {
                unset($state[$property]);
            }
        }

        $this->requestState = $state;
    }

    public function handle(array $changes, array $calls, string $target = null): static
    {
        $this->changes = $changes;
        $this->calls = $calls;
        $this->requestTarget = $target;

        if (isset($calls['mount']) && $target === 'mount' && method_exists($this, 'mount')) {
            $this->makeCalls(['mount' => $calls['mount']]);
            $this->hasBeenReset = true; // Ignore validation, we're in original state - no request with a user interaction was made
        } else {
            if ($this->hydrateComponent()) {
                $this->makeChanges($changes);
                $this->makeCalls($calls);
            }
        }

        $this->dehydrateComponent();

        return $this;
    }

    public function response(): array
    {
        return [
            'data' => $this->getEncodedState(),
            'metadata' => $this->metadata,
        ];
    }

    public static function test(): RequestBuilder
    {
        return Airwire::test(static::class);
    }
}
