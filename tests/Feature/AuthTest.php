<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase, CreatesInventory;

    private function credentials(string $password = '12345678'): array
    {
        return ['email' => 'admin@teste.com', 'password' => $password];
    }

    public function test_login_returns_a_token_and_never_the_password(): void
    {
        $this->admin();

        $this->postJson('/api/auth/login', $this->credentials())
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonMissingPath('user.password');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->admin();

        $this->postJson('/api/auth/login', $this->credentials('errada'))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciais inválidas.');
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::create([
            'name' => 'Inativo', 'email' => 'inativo@teste.com',
            'password' => '12345678', 'role' => 'operator', 'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', ['email' => 'inativo@teste.com', 'password' => '12345678'])
            ->assertUnauthorized();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])->assertUnprocessable();
    }

    public function test_login_is_limited_to_five_attempts_per_minute(): void
    {
        $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $this->credentials('errada'))->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', $this->credentials('errada'))->assertStatus(429);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $routes = [
            '/api/products', '/api/categories', '/api/suppliers', '/api/purchases',
            '/api/sales', '/api/dashboard/summary', '/api/products/export?format=xlsx',
        ];

        foreach ($routes as $uri) {
            $this->getJson($uri)
                ->assertUnauthorized()
                ->assertJsonPath('message', 'Não autenticado.');
        }
    }

    public function test_a_valid_token_works_and_logout_invalidates_it(): void
    {
        $this->admin();

        $token   = $this->postJson('/api/auth/login', $this->credentials())->json('access_token');
        $headers = ['Authorization' => 'Bearer '.$token];

        Auth::forgetGuards();
        $this->getJson('/api/auth/me', $headers)
            ->assertOk()
            ->assertJsonPath('email', 'admin@teste.com');

        $this->postJson('/api/auth/logout', [], $headers)->assertOk();

        Auth::forgetGuards();
        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
    }

    public function test_a_deactivated_user_loses_access_even_with_a_valid_session(): void
    {
        $user = User::create([
            'name' => 'Inativo', 'email' => 'inativo@teste.com',
            'password' => '12345678', 'role' => 'operator', 'is_active' => false,
        ]);

        $this->actingAs($user, 'api')
            ->getJson('/api/products')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Conta desativada.');
    }
}
