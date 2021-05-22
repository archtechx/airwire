<?php
namespace Airwire\Commands;

use Illuminate\Support\Str;

class Component {
    public static function register(string $name)
    {
        $service_provider = base_path() . "\app\Providers\AppServiceProvider.php";
        file_put_contents($service_provider, preg_replace('/register[(][)][\s\S].*/', "register() {\n\t\tAirwire::component('" . Str::snake($name) . "', {$name}::class);", file_get_contents($service_provider)));
    }
}