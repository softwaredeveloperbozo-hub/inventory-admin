<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase, CreatesInventory;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name'     => 'Maria Santos',
            'email'    => 'maria@teste.com',
            'role'     => 'operator',
            'password' => 'segredo123',
        ], $extra);
    }

    public function test_only_admins_can_manage_users(): void
    {
        $target = $this->operator();

        $this->asOperator();

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', $this->payload())->assertForbidden();
        $this->putJson("/api/users/{$target->id}", ['role' => 'admin'])->assertForbidden();

        $this->assertSame('operator', $target->fresh()->role);
        $this->assertDatabaseMissing('users', ['email' => 'maria@teste.com']);
    }

    public function test_an_admin_creates_a_user_who_can_log_in(): void
    {
        $this->asAdmin()
            ->postJson('/api/users', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.role', 'operator')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.password');

        $this->assertNotSame('segredo123', User::where('email', 'maria@teste.com')->value('password'));

        $this->postJson('/api/auth/login', ['email' => 'maria@teste.com', 'password' => 'segredo123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'operator');
    }

    public function test_invalid_users_are_rejected(): void
    {
        $valid = $this->payload();

        $cases = [
            'sem nome'        => array_diff_key($valid, ['name' => 1]),
            'email inválido'  => array_merge($valid, ['email' => 'isto-nao-e-email']),
            'email repetido'  => array_merge($valid, ['email' => 'admin@teste.com']),
            'perfil inválido' => array_merge($valid, ['role' => 'chefe']),
            'senha curta'     => array_merge($valid, ['password' => '1234567']),
            'sem senha'       => array_diff_key($valid, ['password' => 1]),
        ];

        $this->asAdmin();

        foreach ($cases as $name => $payload) {
            $status = $this->postJson('/api/users', $payload)->status();
            $this->assertSame(422, $status, "Caso: {$name}");
        }

        $this->assertDatabaseCount('users', 1); // só o administrador
    }

    public function test_the_email_is_stored_in_lowercase(): void
    {
        $this->asAdmin()
            ->postJson('/api/users', $this->payload(['email' => 'MARIA@Teste.com']))
            ->assertCreated()
            ->assertJsonPath('data.email', 'maria@teste.com');
    }

    public function test_an_admin_can_change_another_users_name_and_role(): void
    {
        $operator = $this->operator();

        $this->asAdmin()
            ->putJson("/api/users/{$operator->id}", ['name' => 'Nome novo', 'role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->assertSame('Nome novo', $operator->fresh()->name);
    }

    public function test_nobody_can_demote_deactivate_or_reset_their_own_password_here(): void
    {
        $admin = $this->admin();

        $this->asAdmin();

        $this->putJson("/api/users/{$admin->id}", ['role' => 'operator'])->assertStatus(409);
        $this->putJson("/api/users/{$admin->id}", ['is_active' => false])->assertStatus(409);
        $this->putJson("/api/users/{$admin->id}", ['password' => 'outrasenha99'])->assertStatus(409);

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);

        // o resto da própria conta pode ser editado, e reenviar os mesmos valores é aceito
        $this->putJson("/api/users/{$admin->id}", ['name' => 'Novo nome', 'role' => 'admin', 'is_active' => true])
            ->assertOk();
    }

    public function test_deactivating_a_user_cuts_their_access_at_once(): void
    {
        $operator = $this->operator();

        $this->asAdmin()
            ->patchJson("/api/users/{$operator->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($operator->fresh(), 'api')
            ->getJson('/api/products')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Conta desativada.');
    }

    public function test_an_admin_can_reset_another_users_password(): void
    {
        $operator = $this->operator();

        $this->asAdmin();

        // editar sem enviar senha não mexe nela
        $this->putJson("/api/users/{$operator->id}", ['name' => 'Outro nome'])->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'operador@teste.com', 'password' => '12345678'])->assertOk();

        $this->putJson("/api/users/{$operator->id}", ['password' => 'novasenha99'])->assertOk();

        $this->postJson('/api/auth/login', ['email' => 'operador@teste.com', 'password' => 'novasenha99'])->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'operador@teste.com', 'password' => '12345678'])->assertUnauthorized();
    }

    public function test_users_can_be_filtered_and_flag_the_current_one(): void
    {
        $this->operator();
        User::create([
            'name' => 'Inativo', 'email' => 'inativo@teste.com',
            'password' => '12345678', 'role' => 'operator', 'is_active' => false,
        ]);

        $this->asAdmin();

        $this->getJson('/api/users')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/users?role=operator')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/users?is_active=0')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/users?search=inativo')->assertOk()->assertJsonCount(1, 'data');

        $rows = collect($this->getJson('/api/users')->json('data'));
        $this->assertTrue($rows->firstWhere('email', 'admin@teste.com')['is_me']);
        $this->assertFalse($rows->firstWhere('email', 'operador@teste.com')['is_me']);
    }

    public function test_users_cannot_be_deleted(): void
    {
        $operator = $this->operator();

        $this->asAdmin()->deleteJson("/api/users/{$operator->id}")->assertStatus(405);

        $this->assertDatabaseHas('users', ['id' => $operator->id]);
    }
}
