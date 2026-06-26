<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_response_keys_are_camel_case(): void
    {
        MenuItem::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/menu-items');

        $item = $response->json('data.0');

        $this->assertArrayHasKey('isAvailable', $item);
        $this->assertArrayHasKey('isFeatured', $item);
        $this->assertArrayHasKey('dailyStock', $item);
        $this->assertArrayHasKey('createdAt', $item);
        $this->assertArrayNotHasKey('is_available', $item);
        $this->assertArrayNotHasKey('created_at', $item);
    }

    public function test_dates_are_serialized_in_iso_8601_with_z_suffix(): void
    {
        MenuItem::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/menu-items');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $response->json('data.0.createdAt'),
        );
    }

    public function test_pagination_meta_is_reshaped_to_contract(): void
    {
        MenuItem::factory()->count(10)->create();

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/menu-items');

        $response->assertJsonStructure([
            'data',
            'meta' => ['total', 'page', 'perPage'],
        ]);

        $this->assertNull($response->json('links'));
        $this->assertNull($response->json('meta.current_page'));
        $this->assertSame(1, $response->json('meta.page'));
        $this->assertSame(10, $response->json('meta.total'));
    }

    public function test_validation_errors_use_contract_envelope(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/admin/menu-items', ['price' => -1]);

        $this->assertApiValidationError($response, ['name', 'category']);
    }

    public function test_accepts_camel_case_json_body_and_persists_snake_case(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/admin/tables', [
                'name' => 'Mesa Contrato',
                'minCapacity' => 2,
                'maxCapacity' => 4,
                'location' => 'interior',
                'isActive' => true,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('tables', [
            'name' => 'Mesa Contrato',
            'min_capacity' => 2,
            'max_capacity' => 4,
            'is_active' => true,
        ]);
    }

    public function test_validates_camel_case_query_string_input(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/analytics/deposits?dateFrom=2026-06-01&dateTo=2026-05-01');

        $this->assertApiValidationError($response, ['dateTo']);
    }
}
