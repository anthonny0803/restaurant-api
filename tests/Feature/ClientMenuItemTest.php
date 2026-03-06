<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientMenuItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_available_menu_items_without_authentication(): void
    {
        MenuItem::factory()->count(3)->create();
        MenuItem::factory()->unavailable()->create();
        MenuItem::factory()->withoutStock()->create();

        $response = $this->getJson('/api/menu-items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'description', 'price', 'category', 'is_available', 'daily_stock', 'created_at']],
                'links',
                'meta',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_does_not_show_unavailable_items(): void
    {
        MenuItem::factory()->create();
        MenuItem::factory()->unavailable()->create();

        $response = $this->getJson('/api/menu-items');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_does_not_show_items_with_zero_stock(): void
    {
        MenuItem::factory()->create();
        MenuItem::factory()->withoutStock()->create();

        $response = $this->getJson('/api/menu-items');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_shows_items_with_unlimited_stock(): void
    {
        MenuItem::factory()->unlimitedStock()->create();

        $response = $this->getJson('/api/menu-items');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.daily_stock', null);
    }

    public function test_can_filter_by_category(): void
    {
        MenuItem::factory()->create(['category' => 'entrantes']);
        MenuItem::factory()->create(['category' => 'entrantes']);
        MenuItem::factory()->create(['category' => 'postres']);

        $response = $this->getJson('/api/menu-items?category=entrantes');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_category_filter_excludes_unavailable_items(): void
    {
        MenuItem::factory()->create(['category' => 'entrantes']);
        MenuItem::factory()->unavailable()->create(['category' => 'entrantes']);

        $response = $this->getJson('/api/menu-items?category=entrantes');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_invalid_category_returns_validation_error(): void
    {
        $response = $this->getJson('/api/menu-items?category=invalida');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_response_is_paginated(): void
    {
        MenuItem::factory()->count(20)->create();

        $response = $this->getJson('/api/menu-items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertLessThan(20, count($response->json('data')));
    }
}
