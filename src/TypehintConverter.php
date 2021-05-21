<?php

namespace Airwire;

use Airwire\Attributes\Wired;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionProperty;
use ReflectionUnionType;

class TypehintConverter
{
    public array $namedTypes = [];

    public function convertBuiltinType(string $type): string
    {
        return match ($type) {
            'int' => 'number',
            'float' => 'number',
            'string' => 'string',
            'array' => 'any', // Arrays can be associative, so they're essentially objects
            'object' => 'any',
            'null' => 'null',
            default => 'any',
        };
    }

    public function convertType(string $php, string $target = 'property'): string
    {
        if (class_exists($php)) {
            if (is_subclass_of($php, Model::class) && ($model = $php::first())) {
                return $target === 'parameter'
                    ? $this->convertModel($model) . '|string|number' // Models can be resolved from IDs
                    : $this->convertModel($model);
            }

            if (is_subclass_of($php, Collection::class)) {
                return 'any'; // Later maybe typed arrays?
            }

            return 'any';
        }

        return $this->convertBuiltinType($php);
    }

    public function typeFromValue(mixed $value): string
    {
        return match (true) {
            $value instanceof Model => $this->convertModel($value),
            $value instanceof Collection => 'array',
            default => $this->convertBuiltinType(gettype($value)),
        };
    }

    public function convertModel(Model $model): string
    {
        $alias = $this->getClassName($model);

        if (! isset($this->namedTypes[$alias])) {
            $this->namedTypes[$alias] = 'pending'; // We do this to avoid infinite loops when recursively generating model type definitions

            $values = $model->toArray()
                ?: $model->first()->toArray() // If this model is empty, attempt finding the first one in the DB
                ?: collect(Schema::getColumnListing($model->getTable()))->mapWithKeys(fn (string $column) => [$column => []])->toArray(); // [] for any

            $this->namedTypes[$alias] = '{ ' .
                collect($values)
                    ->map(fn (mixed $value) => $this->typeFromValue($value))
                    ->map(function (string $type, string $property) use ($model) {
                        if ($model->getKeyName() !== $property) {
                            // Don't do anything
                            return $type;
                        }

                        if ($type === 'any' && $model->getIncrementing()) {
                            $type = 'number';
                        }

                        return $type;
                    })
                    ->merge($this->getModelRelations($model))
                    ->map(fn (string $type, string $property) => "{$property}: {$type}")->join(', ')
            . ' }';
        }

        return $alias;
    }

    public function getModelRelations(Model $model): array
    {
        $loaded = collect($model->getRelations())
            ->map(fn ($value) => $value instanceof Enumerable ? $value->first() : $value) // todo plural relations are incorrectly typed - should be e.g. Report[]
            ->filter(fn ($value) => $value instanceof Model);

        /** @var Collection<string, Model> */
        $reflected = collect((new ReflectionObject($model))->getMethods())
            ->keyBy(fn (ReflectionMethod $method) => $method->getName())
            ->filter(fn (ReflectionMethod $method) => $method->getReturnType() && is_subclass_of($method->getReturnType()->getName(), Relation::class)) // todo support this even without typehints
            ->map(fn (ReflectionMethod $method, string $name) => $model->$name()->getRelated())
            ->filter(fn ($value, $relation) => ! $loaded->has($relation)); // Ignore relations that we could find using getRelations()

        $relations = $loaded->merge($reflected);

        return $relations->map(fn (Model $model) => $this->convertModel($model))->toArray();
    }

    public function getClassName(object|string $class): string
    {
        if (is_object($class)) {
            $class = $class::class;
        }

        return last(explode('\\', $class));
    }

    public function convertComponent(Component $component): string
    {
        $properties = $component->getSharedProperties();
        $methods = $component->getSharedMethods();

        $tsProperties = [];
        $tsMethods = [];

        foreach ($properties as $property) {
            $tsProperties[$property] = $this->convertProperty($component, $property);
        }

        foreach ($methods as $method) {
            $tsMethods[] = $this->convertMethod($component, $method);
        }

        $definition = '';

        $class = $this->getClassName($component);
        $definition .= "interface {$class} {\n";

        foreach ($tsProperties as $property => $type) {
            $definition .= "    {$property}: {$type};\n";
        }

        foreach ($tsMethods as $signature) {
            $definition .= "    {$signature}\n";
        }

        $definition .= <<<TS
            errors: {
                [key in keyof WiredProperties<{$class}>]: string[];
            }

            loading: boolean;

            watch(responses: (response: ComponentResponse<{$class}>) => void, errors?: (error: AirwireException) => void): void;
            defer(callback: CallableFunction): void;
            refresh(): ComponentResponse<{$class}>;
            remount(...args: any): ComponentResponse<{$class}>;

            readonly: {$class};

            deferred: {$class};
            \$component: {$class};
        }
        TS;

        return $definition;
    }

    public function convertProperty(object $object, string $property): string
    {
        $reflection = new ReflectionProperty($object, $property);

        if ($wired = optional($reflection->getAttributes(Wired::class))[0]) {
            if ($type = $wired->newInstance()->type) {
                return $type;
            }
        }

        $type = $reflection->getType();

        if ($type instanceof ReflectionUnionType) {
            $types = $type->getTypes();
        } else {
            $types = [$type];
        }

        if ($type->allowsNull()) {
            $types[] = 'null';
        }

        $results = [];

        foreach ($types as $type) {
            // If we're working with a union type, some types are only accessible
            // from the typehint, but for one type we'll also have the value.
            if (isset($object->$property) && gettype($object->$property) === $type->getName()) {
                $results[] = $this->typeFromValue($object->$property);
            } else {
                $results[] = $this->convertType($type->getName(), 'property');
            }
        }

        return join(' | ', $results);
    }

    public function convertMethod(object $object, string $method): string
    {
        $reflection = new ReflectionMethod($object, $method);

        $parameters = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionUnionType) {
                $types = $type->getTypes();
            } else {
                $types = [$type];
            }

            if ($type->allowsNull()) {
                $types[] = 'null';
            }

            $parameters[$parameter->getName()] = join(' | ', array_map(fn (ReflectionNamedType $type) => $this->convertType($type->getName(), 'parameter'), $types));
        }

        $parameters = collect($parameters)->map(fn (string $type, string $name) => "{$name}: {$type}")->join(', ');

        $return = match ($type = $reflection->getReturnType()) {
            null => 'any',
            default => $this->convertType($type, 'return'),
        };

        return "{$method}(" . $parameters . "): AirwirePromise<{$return}>;";
    }
}
