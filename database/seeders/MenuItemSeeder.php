<?php

namespace Database\Seeders;

use App\Enums\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Croquetas de jamon', 'description' => 'Croquetas caseras de jamon iberico', 'price' => 9.50, 'category' => MenuCategory::Entrantes, 'daily_stock' => 30],
            ['name' => 'Ensalada mediterranea', 'description' => 'Mezcla de lechugas, tomate, aceitunas y queso feta', 'price' => 8.00, 'category' => MenuCategory::Entrantes, 'daily_stock' => null],
            ['name' => 'Gazpacho andaluz', 'description' => 'Sopa fria de tomate con verduras', 'price' => 7.00, 'category' => MenuCategory::Entrantes, 'daily_stock' => 20],

            ['name' => 'Paella valenciana', 'description' => 'Arroz con pollo, conejo y verduras', 'price' => 16.00, 'category' => MenuCategory::Principales, 'daily_stock' => 15],
            ['name' => 'Merluza a la vasca', 'description' => 'Merluza en salsa verde con almejas', 'price' => 18.50, 'category' => MenuCategory::Principales, 'daily_stock' => 12],
            ['name' => 'Solomillo al whisky', 'description' => 'Solomillo de cerdo con salsa al whisky', 'price' => 15.00, 'category' => MenuCategory::Principales, 'daily_stock' => null],

            ['name' => 'Tarta de queso', 'description' => 'Tarta de queso al horno estilo vasco', 'price' => 6.50, 'category' => MenuCategory::Postres, 'daily_stock' => 10],
            ['name' => 'Crema catalana', 'description' => 'Crema con costra de azucar caramelizado', 'price' => 5.50, 'category' => MenuCategory::Postres, 'daily_stock' => 15],

            ['name' => 'Agua mineral', 'description' => null, 'price' => 2.50, 'category' => MenuCategory::Bebidas, 'daily_stock' => null],
            ['name' => 'Vino tinto Rioja', 'description' => 'Copa de vino tinto D.O. Rioja', 'price' => 4.50, 'category' => MenuCategory::Bebidas, 'daily_stock' => null],
            ['name' => 'Sangria', 'description' => 'Jarra de sangria casera', 'price' => 12.00, 'category' => MenuCategory::Bebidas, 'daily_stock' => 8],
        ];

        foreach ($items as $item) {
            MenuItem::firstOrCreate(['name' => $item['name']], $item);
        }
    }
}
