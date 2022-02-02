<?php

declare(strict_types=1);

namespace Airwire;

use Airwire\Attributes\Encode;
use Airwire\Testing\RequestBuilder;
use Exception;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

class Airwire
{
    public static array $components = [];
    public static array $typeTransformers = [];

    public static function component(string $alias, string $class): void
    {
        static::$components[$alias] = $class;
    }

    public static function hasComponent(string $alias): bool
    {
        return isset(static::$components[$alias]);
    }

    public static function typeTransformer(string $type, callable $decode, callable $encode): void
    {
        static::$typeTransformers[$type] = compact('decode', 'encode');
    }

    public static function getDefaultDecoder(): callable
    {
        return fn (array $data, string $class) => new $class($data);
    }

    public static function getDefaultEncoder(): callable
    {
        return fn ($object) => json_decode(json_encode($object), true);
    }

    public static function decode(ReflectionProperty|ReflectionParameter|array $property, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($property)) {
            $property = new ReflectionProperty(...$property);
        }

        if ($property->getType() instanceof ReflectionUnionType) {
            $types = $property->getType()->getTypes();
        } else {
            $types = [$property->getType()];
        }

        foreach ($types as $type) {
            // No type = no transformer
            if ($type === null) {
                continue;
            }

            if ($type->isBuiltin() && gettype($value) === $type->getName()) {
                continue;
            }

            $class = $type->getName();

            $decoder = static::findDecoder($class);

            if ($decoder) {
                return $decoder($value, $class);
            }
        }

        // No class was found
        if (! isset($class)) {
            return $value;
        }

        return static::getDefaultDecoder()($value, $class);
    }

    public static function encode(ReflectionProperty|ReflectionParameter|ReflectionNamedType|ReflectionUnionType|array $property, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($property)) {
            $property = new ReflectionProperty(...$property);
        }

        if (($property instanceof ReflectionProperty || $property instanceof ReflectionParameter) && count($encodeAttributes = $property->getAttributes(Encode::class))) {
            return $encodeAttributes[0]->newInstance()->encode($value);
        }

        if ($property instanceof ReflectionType) {
            $type = $property;
        } else {
            $type = $property->getType();
        }

        if ($type instanceof ReflectionUnionType) {
            $types = $type->getTypes();
        } else {
            $types = [$type];
        }

        foreach ($types as $type) {
            // No type = no transformer
            if ($type === null) {
                continue;
            }

            if ($type->isBuiltin() && gettype($value) === $type->getName()) {
                continue;
            }

            $class = $type->getName();

            $encoder = static::findEncoder($class);

            if ($encoder) {
                return $encoder($value, $class);
            }
        }

        return static::getDefaultEncoder()($value);
    }

    public static function findDecoder(string $class): callable|null
    {
        if (class_exists($class)) {
            return static::getTransformer($class)['decode'] ?? null;
        }

        return match ($class) {
            'int' => fn ($val) => (int) $val,
            'string' => fn ($val) => (string) $val,
            'float' => fn ($val) => (float) $val,
            'bool' => fn ($val) => (bool) $val,
            default => null,
        };
    }

    public static function findEncoder(string $class): callable|null
    {
        if (class_exists($class)) {
            return static::getTransformer($class)['encode'] ?? null;
        }

        return null;
    }

    public static function getTransformer(string $class): array|null
    {
        $transformer = null;

        while (! $transformer) {
            if (isset(static::$typeTransformers[$class])) {
                $transformer = static::$typeTransformers[$class];
            }

            if (! $class = get_parent_class($class)) {
                break;
            }
        }

        return $transformer;
    }

    public static function test(string $component): RequestBuilder
    {
        if (isset(static::$components[$component])) {
            return new RequestBuilder($component);
        } elseif (in_array($component, static::$components)) {
            return new RequestBuilder(array_search($component, static::$components));
        }

        throw new Exception("Component {$component} not found.");
    }

    public static function routes()
    {
        require __DIR__ . '/../routes/airwire.php';
    }
}
