<?php

declare(strict_types=1);

namespace Airwire;

use Airwire\Commands\ComponentCommand;
use Airwire\Commands\GenerateDefinitions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\ServiceProvider;

class AirwireServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([GenerateDefinitions::class, ComponentCommand::class]);

        $this->loadDefaultTransformers();

        $this->loadRoutesFrom(__DIR__ . '/../routes/airwire.php');
    }

    public function loadDefaultTransformers(): void
    {
        Airwire::typeTransformer(
            Model::class,
            decode: function (mixed $data, string $model) {
                $keyName = $model::make()->getKeyName();

                if (is_array($data)) {
                    if (isset($data[$keyName])) {
                        if ($instance = $model::find($data[$keyName])) {
                            return $instance;
                        }
                    }

                    return new $model($data);
                } else {
                    return $model::find($data);
                }
            },
            encode: fn (Model $model) => $model->toArray()
        );

        Airwire::typeTransformer(
            Collection::class,
            decode: fn (array $data, string $class) => new $class($data),
            encode: fn (Collection $collection) => $collection->toArray(),
        );

        Airwire::typeTransformer(
            LazyCollection::class,
            decode: fn (array $data, string $class) => new $class($data),
            encode: fn (LazyCollection $collection) => $collection->toArray(),
        );
    }
}
