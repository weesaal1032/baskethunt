<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_admin_route_redirects(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
    }
}
