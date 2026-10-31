<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase, CreatesInventory;

    public function test_a_user_can_update_their_own_name_and_email(): void
    {
        $this->asOperator()
            ->putJson('/api/profile', ['name' => 'Novo Nome', 'email' => 'NOVO@teste.com'])
            ->assertOk()
            ->assertJsonPath('name', 'Novo Nome')
            ->assertJsonPath('email', 'novo@teste.com');
    }

    public function test_the_email_must_be_unique_but_can_stay_the_same(): void
    {
        $this->admin();

        $this->asOperator();

        $this->putJson('/api/profile', ['name' => 'Operador', 'email' => 'admin@teste.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->putJson('/api/profile', ['name' => 'Operador', 'email' => 'operador@teste.com'])->assertOk();
    }

    public function test_the_role_and_status_cannot_be_changed_through_the_profile(): void
    {
        $this->asOperator()
            ->putJson('/api/profile', [
                'name' => 'Operador', 'email' => 'operador@teste.com',
                'role' => 'admin', 'is_active' => false,
            ])
            ->assertOk();

        $user = $this->operator()->fresh();

        $this->assertSame('operator', $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_changing_the_password_is_validated(): void
    {
        $this->asOperator();

        $cases = [
            'senha atual errada' => ['current_password' => 'errada', 'password' => 'novasenha99', 'password_confirmation' => 'novasenha99'],
            'confirmação difere' => ['current_password' => '12345678', 'password' => 'novasenha99', 'password_confirmation' => 'diferente11'],
            'igual à atual'      => ['current_password' => '12345678', 'password' => '12345678', 'password_confirmation' => '12345678'],
            'senha curta'        => ['current_password' => '12345678', 'password' => 'curta1', 'password_confirmation' => 'curta1'],
        ];

        foreach ($cases as $name => $payload) {
            $status = $this->putJson('/api/profile/password', $payload)->status();
            $this->assertSame(422, $status, "Caso: {$name}");
        }
    }

    public function test_a_successful_change_ends_the_current_session(): void
    {
        $this->operator();

        $login = fn (string $password) => $this->postJson('/api/auth/login', [
            'email' => 'operador@teste.com', 'password' => $password,
        ]);

        $token   = $login('12345678')->assertOk()->json('access_token');
        $headers = ['Authorization' => 'Bearer '.$token];

        Auth::forgetGuards();
        $this->putJson('/api/profile/password', [
            'current_password' => '12345678',
            'password' => 'novasenha99',
            'password_confirmation' => 'novasenha99',
        ], $headers)->assertOk();

        Auth::forgetGuards();
        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();

        $login('12345678')->assertUnauthorized();
        $login('novasenha99')->assertOk();
    }
}
