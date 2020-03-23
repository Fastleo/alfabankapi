<?php

namespace Agenta\Alfabankapi\Tests;

use Orchestra\Testbench\TestCase;
use Agenta\Alfabankapi\AlfabankapiServiceProvider;

class ExampleTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [AlfabankapiServiceProvider::class];
    }

    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }
}
