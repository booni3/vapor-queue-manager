<?php

namespace Booni3\VaporQueueManager\Tests;

use Orchestra\Testbench\TestCase;
use Booni3\VaporQueueManager\VaporQueueManagerServiceProvider;

class ExampleTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [VaporQueueManagerServiceProvider::class];
    }
    
    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }
}
