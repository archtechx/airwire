<?php

namespace Airwire\Concerns;

use Airwire\Airwire;
use ReflectionMethod;
use ReflectionObject;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Validation\ValidationException;
use Throwable;

trait ManagesActions
{
    public array $readonly = [];

    public function makeChanges(array $changes): void
    {
        foreach ($this->getSharedProperties() as $property) {
            if (isset($changes[$property]) && ! $this->isReadonly($property)) {
                if (! $this->makeChange($property, $changes[$property])) {
                    unset($changes[$property]);
                }
            } else {
                unset($changes[$property]);
            }
        }

        if ($changes) {
            try {
                $this->changed($changes);
            } catch (ValidationException) {}
        }
    }

    protected function makeChange(string $property, mixed $new): bool
    {
        $old = $this->$property ?? null;

        try {
            if ($this->updating($property, $new, $old) === false) {
                return false;
            }

            if (method_exists($this, $method = ('updating' . ucfirst($property)))) {
                if ($this->$method($new, $old) === false) {
                    return false;
                }
            }
        } catch (ValidationException $e) {
            return false;
        }

        $this->$property = $new;

        $this->updated($property, $new);

        if (method_exists($this, $method = ('updated' . ucfirst($property)))) {
            $this->$method($new, $old);
        }

        return true;
    }

    public function makeCalls(array $calls): void
    {
        $this->metadata['calls'] ??= [];

        foreach ($this->getSharedMethods() as $method) {
            if (isset($calls[$method])) {
                try {
                    $result = $this->callWiredMethod($method, $calls[$method]);

                    if ($method === 'mount') {
                        if (isset($result['readonly'])) {
                            $readonly = $result['readonly'];
                            unset($result['readonly']);
                            $result = array_merge($readonly, $result);

                            $this->readonly = array_unique(array_merge(
                                $this->readonly, array_keys($readonly)
                            ));
                        }
                    }

                    $this->metadata['calls'][$method] = $result;
                } catch (Throwable $e) {
                    if (! app()->isProduction() && ! $e instanceof ValidationException) {
                        $reflection = (new ReflectionObject($handler = (new Handler(app()))))->getMethod('convertExceptionToArray');
                        $reflection->setAccessible(true);

                        $this->metadata['exceptions'] ??= [];
                        $this->metadata['exceptions'][$method] = $reflection->invoke($handler, $e);
                    }
                }
            }
        }
    }

    protected function callWiredMethod(string $method, array $arguments): mixed
    {
        $reflectionMethod = new ReflectionMethod($this, $method);
        $parameters = $reflectionMethod->getParameters();

        foreach ($arguments as $index => &$value) {
            if (! isset($parameters[$index])) {
                break;
            }

            $parameter = $parameters[$index];

            $value = Airwire::decode($parameter, $value);
        }

        $result = $this->$method(...$arguments);

        if ($returnType = $reflectionMethod->getReturnType()) {
            return Airwire::encode($returnType, $result);
        } else {
            return $result;
        }
    }
}
