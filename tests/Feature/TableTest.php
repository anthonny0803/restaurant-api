<?php

namespace Tests\Feature;

use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class TableTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function tableData(array $overrides = []): array
    {
        return array_merge([
            'name'         => 'Mesa 1',
            'min_capacity' => 2,
            'max_capacity' => 4,
            'location'     => 'interior',
            'description'  => null,
            'is_active'    => true,
        ], $overrides);
    }

    public function test_admin_can_list_tables(): void
    {
        Table::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/tables');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'  => [['id', 'name', 'min_capacity', 'max_capacity', 'location', 'is_active']],
                'links',
                'meta',
            ]);
    }

    public function test_admin_can_create_a_table(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/admin/tables', $this->tableData());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'min_capacity', 'max_capacity', 'location', 'is_active'],
            ]);

        $this->assertDatabaseHas('tables', ['name' => 'Mesa 1']);
    }

    public function test_admin_can_view_a_table(): void
    {
        $table = Table::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->getJson("/api/admin/tables/{$table->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $table->id);
    }

    public function test_admin_can_update_a_table(): void
    {
        $table = Table::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->putJson("/api/admin/tables/{$table->id}", ['name' => 'Mesa Actualizada']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Mesa Actualizada');

        $this->assertDatabaseHas('tables', ['name' => 'Mesa Actualizada']);
    }

    public function test_admin_can_delete_a_table(): void
    {
        $table = Table::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->deleteJson("/api/admin/tables/{$table->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tables', ['id' => $table->id]);
    }

    public function test_client_cannot_manage_tables(): void
    {
        $response = $this->actingAs($this->clientUser())
            ->postJson('/api/admin/tables', $this->tableData());

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_tables(): void
    {
        $response = $this->getJson('/api/admin/tables');

        $response->assertStatus(401);
    }

    public function test_table_name_must_be_unique(): void
    {
        Table::factory()->create(['name' => 'Mesa 1']);

        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/admin/tables', $this->tableData());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_max_capacity_must_be_greater_than_or_equal_to_min_capacity(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/admin/tables', $this->tableData([
                'min_capacity' => 6,
                'max_capacity' => 2,
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_capacity']);
    }

    public function test_admin_can_clear_nullable_fields(): void
    {
        $table = Table::factory()->create(['description' => 'Mesa junto a la ventana']);

        $response = $this->actingAs($this->adminUser())
            ->putJson("/api/admin/tables/{$table->id}", [
                'description' => null,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.description', null);

        $this->assertDatabaseHas('tables', [
            'id' => $table->id,
            'description' => null,
        ]);
    }

    public function test_show_returns_404_for_nonexistent_table(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/tables/999');

        $response->assertStatus(404);
    }
}
