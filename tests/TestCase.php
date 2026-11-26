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

    /**
     * Path mode (CLAUDE.md, Tenancy): signing in as a company user also makes route() build that company's URLs (/{slug}/...),
     * the way the app does on a company page. It does not set Tenant::current() (that would turn on the model scope in the test).
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        if (($tid = $user->tenant_id ?? null) && ($tenant = \App\Models\Tenant::find($tid))) {
            $this->forTenant($tenant);
        }

        return parent::actingAs($user, $guard);
    }

    /** route() builds this company's URLs from here on (path mode); for tests with no signed-in company user. */
    protected function forTenant(\App\Models\Tenant $tenant): static
    {
        \App\Support\Tenancy::applyUrlDefaults($tenant);

        return $this;
    }
}
