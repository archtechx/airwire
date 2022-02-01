<?php

declare(strict_types=1);

namespace Airwire\Concerns;

use Airwire\Airwire;
use Airwire\Attributes\Wired;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;

trait ManagesState
{
    public bool $hasBeenReset = false;

    public function getSharedProperties(): array
    {
        return collect((new ReflectionObject($this))->getProperties())
            ->filter(
                fn (ReflectionProperty $property) => collect($property->getAttributes(Wired::class))->isNotEmpty()
            )
            ->map(fn (ReflectionProperty $property) => $property->getName())
            ->toArray();
    }

    public function getSharedMethods(): array
    {
        return collect((new ReflectionObject($this))->getMethods())
            ->filter(
                fn (ReflectionMethod $method) => collect($method->getAttributes(Wired::class))->isNotEmpty()
            )
            ->map(fn (ReflectionMethod $method) => $method->getName())
            ->merge(method_exists($this, 'mount') ? ['mount'] : [])
            ->toArray();
    }

    public function getState(): array
    {
        return collect($this->getSharedProperties())
            ->combine($this->getSharedProperties())
            ->map(function (string $property) {
                if (isset($this->$property)) {
                    return $this->$property;
                }

                $default = optional((new ReflectionProperty($this, $property))->getAttributes(Wired::class))[0]->newInstance()->default;
                if ($default !== null) {
                    return Airwire::decode([$this, $property], $default);
                }

                return null;
            })
            ->all();
    }

    public function getEncodedState(): array
    {
        return collect($this->getState())
            ->map(fn ($value, $key) => Airwire::encode([$this, $key], $value))
            ->toArray();
    }

    public function getReadonlyProperties(): array
    {
        return collect($this->getSharedProperties())
            ->filter(fn (string $property) => $this->isReadonly($property))
            ->values()
            ->toArray();
    }

    public function isReadonly(string $property): bool
    {
        $attributes = (new ReflectionProperty($this, $property))->getAttributes(Wired::class);

        return count($attributes) === 1 && $attributes[0]->newInstance()->readonly === true;
    }

    public function reset(array $properties = null): void
    {
        $properties ??= $this->getSharedProperties();

        foreach ($properties as $property) {
            unset($this->$property);
        }

        $this->hasBeenReset = true;
    }

    public function meta(string|array $key, mixed $value): void
    {
        if (is_array($key)) {
            $this->metadata = array_merge($this->metadata, $key);
        } else {
            $this->metadata[$key] = $value;
        }
    }
}
