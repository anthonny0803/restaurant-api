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
                    'admin_fee_percentage',
                    'default_reservation_duration_minutes',
                    'reminder_hours_before',
                    'time_slot_interval_minutes',
                ],
            ]);
    }

    // ── Update ───────────────────────────────────────────────

    public function test_admin_can_update_a_single_setting(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'time_slot_interval_minutes' => 15,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.time_slot_interval_minutes', 15);

        $this->assertDatabaseHas('restaurant_settings', [
            'time_slot_interval_minutes' => 15,
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
        $response = $this->actingAs($this->adminUser())
            ->patchJson('/api/admin/settings', [
                'time_slot_interval_minutes' => 20,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time_slot_interval_minutes']);
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
            ->patchJson('/api/admin/settings', ['time_slot_interval_minutes' => 15])
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_settings(): void
    {
        $this->getJson('/api/admin/settings')
            ->assertStatus(401);

        $this->patchJson('/api/admin/settings', ['time_slot_interval_minutes' => 15])
            ->assertStatus(401);
    }
}
