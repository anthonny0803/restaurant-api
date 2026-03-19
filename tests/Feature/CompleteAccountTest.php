<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompleteAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function lazyUser(): User
    {
        $user = User::create([
            'name' => 'Juan Perez',
            'email' => 'lazy@example.com',
        ]);

        $user->assignRole('client');
        $user->clientProfile()->create([]);

        return $user;
    }

    public function test_lazy_user_can_complete_account(): void
    {
        $user = $this->lazyUser();

        $response = $this->actingAs($user)
            ->postJson('/api/auth/complete-account', [
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'email']]);

        $user->refresh();
        $this->assertNotNull($user->password);
        $this->assertTrue(Hash::check('NewPassword1', $user->password));
    }

    public function test_registered_user_cannot_complete_account(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($user)
            ->postJson('/api/auth/complete-account', [
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account']);
    }

    public function test_complete_account_rejects_missing_fields(): void
    {
        $user = $this->lazyUser();

        $response = $this->actingAs($user)
            ->postJson('/api/auth/complete-account', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_unauthenticated_user_cannot_complete_account(): void
    {
        $response = $this->postJson('/api/auth/complete-account', [
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(401);
    }
}
