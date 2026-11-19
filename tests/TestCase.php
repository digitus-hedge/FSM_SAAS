<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The suite uses the MySQL database named in phpunit.xml. RefreshDatabase drops every table, so refuse to run
     * on any real database whose name does not end in _test (for example when DB_DATABASE is set in the shell).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $name       = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' && ! str_ends_with($name, '_test')) {
            $this->markTestSkipped("Refusing to run on database \"{$name}\": its name must end in _test.");
        }
    }
}
