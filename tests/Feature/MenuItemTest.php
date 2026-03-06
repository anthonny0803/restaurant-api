<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }

    // --- List ---

    public function test_admin_can_list_menu_items(): void
    {
        MenuItem::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/menu-items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'description', 'price', 'category', 'is_available', 'daily_stock', 'created_at']],
                'links',
                'meta',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_filter_menu_items_by_category(): void
    {
        MenuItem::factory()->create(['category' => 'entrantes']);
        MenuItem::factory()->create(['category' => 'entrantes']);
        MenuItem::factory()->create(['category' => 'postres']);

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/menu-items?category=entrantes');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_invalid_category_filter_returns_all_items(): void
    {
        MenuItem::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/menu-items?category=invalida');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    // --- Create ---

    public function test_admin_can_create_a_menu_item(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/menu-items', [
                'name' => 'Tortilla de patatas',
                'description' => 'Tortilla casera con cebolla',
                'price' => 8.50,
                'category' => 'entrantes',
                'daily_stock' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Tortilla de patatas')
            ->assertJsonPath('data.category', 'entrantes')
            ->assertJsonPath('data.price', '8.50');

        $this->assertDatabaseHas('menu_items', ['name' => 'Tortilla de patatas']);
    }

    public function test_menu_item_name_must_be_unique(): void
    {
        MenuItem::factory()->create(['name' => 'Paella']);

        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/menu-items', [
                'name' => 'Paella',
                'price' => 15.00,
                'category' => 'principales',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_name_and_price_and_category_are_required(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/menu-items', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price', 'category']);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/menu-items', [
                'name' => 'Pizza',
                'price' => 12.00,
                'category' => 'pizzas',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_price_must_be_positive(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/menu-items', [
                'name' => 'Agua',
                'price' => 0,
                'category' => 'bebidas',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    // --- Show ---

    public function test_admin_can_view_a_menu_item(): void
    {
        $menuItem = MenuItem::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->getJson("/api/menu-items/{$menuItem->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $menuItem->id);
    }

    public function test_show_returns_404_for_nonexistent_menu_item(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/menu-items/999');

        $response->assertStatus(404);
    }

    // --- Update ---

    public function test_admin_can_update_a_menu_item(): void
    {
        $menuItem = MenuItem::factory()->create(['price' => '10.00']);

        $response = $this->actingAs($this->adminUser())
            ->putJson("/api/menu-items/{$menuItem->id}", ['price' => 12.50]);

        $response->assertStatus(200)
            ->assertJsonPath('data.price', '12.50');

        $this->assertDatabaseHas('menu_items', ['id' => $menuItem->id, 'price' => 12.50]);
    }

    public function test_admin_can_update_name_without_unique_conflict(): void
    {
        $menuItem = MenuItem::factory()->create(['name' => 'Paella']);

        $response = $this->actingAs($this->adminUser())
            ->putJson("/api/menu-items/{$menuItem->id}", ['name' => 'Paella']);

        $response->assertStatus(200);
    }

    public function test_admin_can_clear_nullable_fields(): void
    {
        $menuItem = MenuItem::factory()->create([
            'description' => 'Una descripcion',
            'daily_stock' => 10,
        ]);

        $response = $this->actingAs($this->adminUser())
            ->putJson("/api/menu-items/{$menuItem->id}", [
                'description' => null,
                'daily_stock' => null,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.daily_stock', null);

        $this->assertDatabaseHas('menu_items', [
            'id' => $menuItem->id,
            'description' => null,
            'daily_stock' => null,
        ]);
    }

    // --- Delete ---

    public function test_admin_can_delete_a_menu_item(): void
    {
        $menuItem = MenuItem::factory()->create();

        $response = $this->actingAs($this->adminUser())
            ->deleteJson("/api/menu-items/{$menuItem->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('menu_items', ['id' => $menuItem->id]);
    }

    // --- Authorization ---

    public function test_client_cannot_manage_menu_items(): void
    {
        $response = $this->actingAs($this->clientUser())
            ->postJson('/api/menu-items', [
                'name' => 'Tortilla',
                'price' => 8.00,
                'category' => 'entrantes',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_manage_menu_items(): void
    {
        $response = $this->postJson('/api/menu-items', [
            'name' => 'Tortilla',
            'price' => 8.00,
            'category' => 'entrantes',
        ]);

        $response->assertStatus(401);
    }
}
