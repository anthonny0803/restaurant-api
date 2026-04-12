<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class ProfileTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function clientWithProfile(): User
    {
        $user = $this->clientUser();
        $user->clientProfile()->create(['phone' => '612345678']);

        return $user;
    }

    // ── Show ─────────────────────────────────────────────────

    public function test_client_can_view_profile(): void
    {
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.phone', '612345678')
            ->assertJsonPath('data.role', 'client')
            ->assertJsonPath('data.is_guest', false);
    }

    public function test_admin_can_view_profile(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.is_guest', false);
    }

    public function test_guest_user_shows_is_guest_true(): void
    {
        $guest = User::factory()->create(['password' => null]);
        $guest->assignRole('client');
        $guest->clientProfile()->create([]);

        $response = $this->actingAs($guest)->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.is_guest', true);
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $this->getJson('/api/profile')->assertStatus(401);
    }

    // ── Update ───────────────────────────────────────────────

    public function test_client_can_update_name(): void
    {
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)
            ->putJson('/api/profile', ['name' => 'Nuevo Nombre']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Nuevo Nombre');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nuevo Nombre']);
    }

    public function test_client_can_update_email(): void
    {
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)
            ->putJson('/api/profile', ['email' => 'nuevo@example.com']);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'nuevo@example.com');
    }

    public function test_client_can_update_phone(): void
    {
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)
            ->putJson('/api/profile', ['phone' => '698765432']);

        $response->assertStatus(200)
            ->assertJsonPath('data.phone', '698765432');

        $this->assertDatabaseHas('client_profiles', ['user_id' => $user->id, 'phone' => '698765432']);
    }

    public function test_client_can_update_multiple_fields(): void
    {
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)
            ->putJson('/api/profile', [
                'name' => 'Otro Nombre',
                'email' => 'otro@example.com',
                'phone' => '699999999',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Otro Nombre')
            ->assertJsonPath('data.email', 'otro@example.com')
            ->assertJsonPath('data.phone', '699999999');
    }

    public function test_update_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)
            ->putJson('/api/profile', ['email' => 'taken@example.com']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_allows_own_email(): void
    {
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)
            ->putJson('/api/profile', ['email' => $user->email]);

        $response->assertStatus(200);
    }

    public function test_admin_can_update_profile(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->putJson('/api/profile', ['name' => 'Admin Actualizado']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Admin Actualizado');
    }

    public function test_admin_phone_update_is_ignored_without_profile(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->putJson('/api/profile', ['phone' => '612345678']);

        $response->assertStatus(200)
            ->assertJsonPath('data.phone', null);
    }

    public function test_unauthenticated_user_cannot_update_profile(): void
    {
        $this->putJson('/api/profile', ['name' => 'Test'])->assertStatus(401);
    }

    // ── Update Password ─────────────────────────────────────

    public function test_client_can_change_password(): void
    {
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'NewSecure1!',
                'password_confirmation' => 'NewSecure1!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Contrasena actualizada correctamente.');
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'NewSecure1!',
                'password_confirmation' => 'NewSecure1!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_requires_confirmation(): void
    {
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'NewSecure1!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_change_rejects_weak_password(): void
    {
        $user = $this->clientWithProfile();

        $response = $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => '123',
                'password_confirmation' => '123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_unauthenticated_user_cannot_change_password(): void
    {
        $this->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'NewSecure1!',
            'password_confirmation' => 'NewSecure1!',
        ])->assertStatus(401);
    }
}
