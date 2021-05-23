<?php

namespace Airwire\Commands;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Console\Command;

class ComponentCommand extends Command
{
    protected $signature = 'airwire:component {name}';

    protected $description = 'Create a new Airwire component';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));

        if (! is_dir($path = app_path('Airwire'))) {
            mkdir($path);
        }

        file_put_contents($path . '/' . $name . '.php', <<<PHP
        <?php

        namespace App\Airwire;

        use Airwire\Attributes\Wired;
        use Airwire\Component;

        class {$name} extends Component
        {
            //
        }
        PHP);

        $this->register($name);
    }

    protected function register($name)
    {
        try {
            $path = app_path('Providers/AppServiceProvider.php');

            $snake = Str::kebab($name);

            file_put_contents($path, str_replace(
                "function boot()\n    {",
                "function boot()\n    {\n        \\Airwire\\Airwire::component('{$snake}', \\App\\Airwire\\{$name}::class);",
                file_get_contents($path)
            ));

            $this->line("✨ Component app/Airwire/{$name}.php has been created and registered!");
        } catch (Exception $exception) {
            $this->error('The component could not be registered. Please check your app/Providers/AppServiceProvider.php');
        }
    }
}
