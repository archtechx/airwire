<?php

namespace Airwire\Commands;

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

        $this->line("✨ Component app/Airwire/{$name}.php has been created!\n");

        $this->warn("🚧 Don't forget to register the component! Add the following line to AppServiceProvider:");
        $this->line("\n\n    Airwire::component('" . Str::snake($name) . "', {$name}::class);\n\n");
    }
}
