<?php

namespace Airwire\Tests;

use Airwire\AirwireServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

class TestCase extends TestbenchTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            AirwireServiceProvider::class,
        ];
    }
}
