<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\AccountCompletionNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_MESSAGE = 'Si tu correo esta registrado, recibiras instrucciones para restablecer tu contrasena.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_forgot_password_sends_reset_notification_to_user_with_password(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJson(['message' => self::GENERIC_MESSAGE]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_sends_account_completion_notification_to_guest(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => null]);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJson(['message' => self::GENERIC_MESSAGE]);

        Notification::assertSentTo($user, AccountCompletionNotification::class);
        Notification::assertNotSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_returns_same_response_for_nonexistent_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJson(['message' => self::GENERIC_MESSAGE]);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_is_throttled_by_broker(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_updates_password_successfully(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])
            ->assertOk()
            ->assertJson(['message' => 'Tu contrasena ha sido restablecida exitosamente.']);

        $this->assertTrue(Hash::check('NewPassword1', $user->fresh()->password));
    }

    public function test_reset_password_works_for_guest_user(): void
    {
        $user = User::factory()->create(['password' => null]);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewPassword1', $fresh->password));
        $this->assertFalse($fresh->isGuest());
    }

    public function test_reset_password_revokes_all_sanctum_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('api-token');
        $user->createToken('api-token');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => 'invalid-token',
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_fails_with_expired_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->travel(61)->minutes();

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_rejects_weak_password(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => '12345678',
            'password_confirmation' => '12345678',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_requires_password_confirmation(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword1',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_new_password_after_reset(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'NewPassword1',
        ])->assertOk();
    }
}
