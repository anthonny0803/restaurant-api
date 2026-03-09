<?php

namespace App\Repositories;

use App\Enums\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MenuItemRepository
{
    public function paginate(?MenuCategory $category = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = MenuItem::query();

        if ($category) {
            $query->byCategory($category);
        }

        return $query->paginate($perPage);
    }

    public function paginateForClient(?MenuCategory $category = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = MenuItem::available()->inStock();

        if ($category) {
            $query->byCategory($category);
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): ?MenuItem
    {
        return MenuItem::find($id);
    }

    public function create(array $data): MenuItem
    {
        return MenuItem::create($data);
    }

    public function update(MenuItem $menuItem, array $data): MenuItem
    {
        $menuItem->update($data);

        return $menuItem;
    }

    public function delete(MenuItem $menuItem): void
    {
        $menuItem->delete();
    }

    public function decrementStock(MenuItem $menuItem, int $quantity): void
    {
        $menuItem->decrement('daily_stock', $quantity);
    }

    public function incrementStock(MenuItem $menuItem, int $quantity): void
    {
        $menuItem->increment('daily_stock', $quantity);
    }
}
