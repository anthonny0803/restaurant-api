<?php

namespace Tests\Feature;

use App\Models\RestaurantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class RestaurantSettingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RestaurantSettingSeeder::class);
    }

    // ── Show ─────────────────────────────────────────────────

    public function test_admin_can_view_settings(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'deposit_per_person',
                    'cancellation_deadline_hours',
                    'refund_percentage',
                    'default_reservation_duration_minutes',
                    'reminder_hours_before',
                    'time_slot_interval_minutes',
                    'opening_time',
                    'closing_time',
                ],
            ]);
    }

    // ── Update ───────────────────────────────────────────────

    public function test_admin_can_update_a_single_setting(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'time_slot_interval_minutes' => 30,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.time_slot_interval_minutes', 30);

        $this->assertDatabaseHas('restaurant_settings', [
            'time_slot_interval_minutes' => 30,
        ]);
    }

    public function test_admin_can_update_multiple_settings(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'deposit_per_person' => 10.00,
                'cancellation_deadline_hours' => 48,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.deposit_per_person', '10.00')
            ->assertJsonPath('data.cancellation_deadline_hours', 48);
    }

    public function test_update_does_not_modify_fields_not_sent(): void
    {
        $original = RestaurantSetting::first();

        $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'time_slot_interval_minutes' => 60,
            ]);

        $updated = RestaurantSetting::first();

        $this->assertEquals($original->deposit_per_person, $updated->deposit_per_person);
        $this->assertEquals($original->cancellation_deadline_hours, $updated->cancellation_deadline_hours);
        $this->assertEquals(60, $updated->time_slot_interval_minutes);
    }

    // ── Validation ───────────────────────────────────────────

    public function test_update_rejects_invalid_time_slot_interval(): void
    {
        $admin = $this->adminUser();

        foreach ([15, 20, 45] as $invalidValue) {
            $this->actingAs($admin)
                ->patchJson('/api/admin/settings', [
                    'time_slot_interval_minutes' => $invalidValue,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['time_slot_interval_minutes']);
        }
    }

    public function test_update_rejects_invalid_reservation_duration(): void
    {
        $admin = $this->adminUser();

        foreach ([15, 45, 120, 480] as $invalidValue) {
            $this->actingAs($admin)
                ->patchJson('/api/admin/settings', [
                    'default_reservation_duration_minutes' => $invalidValue,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['default_reservation_duration_minutes']);
        }
    }

    public function test_update_rejects_negative_deposit(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'deposit_per_person' => -5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['deposit_per_person']);
    }

    public function test_update_rejects_refund_percentage_above_100(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'refund_percentage' => 150,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['refund_percentage']);
    }

    // ── Authorization ────────────────────────────────────────

    public function test_client_cannot_access_settings(): void
    {
        $this->actingAs($this->clientUser())
            ->getJson('/api/admin/settings')
            ->assertStatus(403);

        $this->actingAs($this->clientUser())
            ->patchJson('/api/admin/settings', ['time_slot_interval_minutes' => 30])
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_settings(): void
    {
        $this->getJson('/api/admin/settings')
            ->assertStatus(401);

        $this->patchJson('/api/admin/settings', ['time_slot_interval_minutes' => 30])
            ->assertStatus(401);
    }

    // ── Opening Hours ───────────────────────────────────────

    public function test_admin_can_update_opening_hours(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'opening_time' => '10:00',
                'closing_time' => '22:00',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.opening_time', '10:00')
            ->assertJsonPath('data.closing_time', '22:00');
    }

    public function test_update_rejects_opening_time_after_closing_time(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'opening_time' => '22:00',
                'closing_time' => '10:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opening_time']);
    }

    public function test_update_rejects_opening_time_equal_to_closing_time(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'opening_time' => '12:00',
                'closing_time' => '12:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opening_time']);
    }

    public function test_partial_update_validates_against_existing_hours(): void
    {
        RestaurantSetting::first()->update(['opening_time' => '10:00', 'closing_time' => '22:00']);

        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'closing_time' => '09:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opening_time']);
    }

    public function test_update_rejects_invalid_time_format(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'opening_time' => '9am',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['opening_time']);
    }

    public function test_update_rejects_opening_time_not_aligned_to_interval(): void
    {
        RestaurantSetting::first()->update(['time_slot_interval_minutes' => 60]);

        $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'opening_time' => '08:30',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['opening_time']);
    }

    public function test_update_rejects_closing_time_not_aligned_to_interval(): void
    {
        RestaurantSetting::first()->update(['time_slot_interval_minutes' => 60]);

        $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'closing_time' => '22:30',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['closing_time']);
    }

    public function test_update_rejects_interval_change_that_misaligns_existing_times(): void
    {
        RestaurantSetting::first()->update(['opening_time' => '08:30']);

        $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'time_slot_interval_minutes' => 60,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['opening_time']);
    }

    public function test_update_accepts_aligned_times_with_60_minute_interval(): void
    {
        RestaurantSetting::first()->update(['time_slot_interval_minutes' => 60]);

        $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'opening_time' => '08:00',
                'closing_time' => '22:00',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.opening_time', '08:00')
            ->assertJsonPath('data.closing_time', '22:00');
    }

    // ── Public Endpoint ─────────────────────────────────────

    public function test_public_endpoint_returns_schedule_settings(): void
    {
        $response = $this->getJson('/api/settings/public');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'opening_time',
                    'closing_time',
                    'time_slot_interval_minutes',
                    'default_reservation_duration_minutes',
                ],
            ])
            ->assertJsonPath('data.opening_time', '09:00')
            ->assertJsonPath('data.closing_time', '23:00')
            ->assertJsonPath('data.time_slot_interval_minutes', 30)
            ->assertJsonPath('data.default_reservation_duration_minutes', 60);
    }

    public function test_public_endpoint_does_not_expose_sensitive_settings(): void
    {
        $response = $this->getJson('/api/settings/public');

        $response->assertStatus(200)
            ->assertJsonMissing(['deposit_per_person'])
            ->assertJsonMissing(['cancellation_deadline_hours'])
            ->assertJsonMissing(['refund_percentage']);
    }
}
