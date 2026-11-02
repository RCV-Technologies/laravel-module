<?php

namespace RCV\Core\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['view']->addNamespace('core', dirname(__DIR__).'/src/Resources/views');
    }
}
