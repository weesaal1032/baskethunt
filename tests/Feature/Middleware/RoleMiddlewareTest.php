<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function () {
            Route::get('/role-protected', function () {
                return 'ok';
            })->middleware(['auth', 'role:qa']);
        });
    }

    public function test_user_with_role_can_access_route(): void
    {
        $user = User::factory()->create([
            'role' => 'qa',
            'password' => bcrypt('Password123!'),
        ]);

        $this->actingAs($user);

        $response = $this->get('/role-protected');

        $response->assertOk();
    }

    public function test_user_without_role_is_forbidden(): void
    {
        $user = User::factory()->create([
            'role' => 'readonly',
            'password' => bcrypt('Password123!'),
        ]);

        $this->actingAs($user);

        $response = $this->get('/role-protected');

        $response->assertForbidden();
    }
}
