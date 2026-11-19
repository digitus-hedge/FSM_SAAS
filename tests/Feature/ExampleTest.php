<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The application answers, and the root address sends a visitor to sign in (no database needed). */
class ExampleTest extends TestCase
{
    public function test_the_health_check_answers(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_the_root_address_sends_a_visitor_to_sign_in(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
