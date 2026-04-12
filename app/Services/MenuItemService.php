<?php

namespace App\Services;

use App\DTOs\StoreMenuItemDTO;
use App\DTOs\UpdateMenuItemDTO;
use App\Enums\MenuCategory;
use App\Models\MenuItem;
use App\Repositories\MenuItemRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MenuItemService
{
    public function __construct(private MenuItemRepository $repository) {}

    public function paginate(?MenuCategory $category = null, int $perPage = 6): LengthAwarePaginator
    {
        return $this->repository->paginate($category, $perPage);
    }

    public function listForClient(?MenuCategory $category = null, ?bool $featured = null, int $perPage = 6): LengthAwarePaginator
    {
        return $this->repository->paginateForClient($category, $featured, $perPage);
    }

    public function find(int $id): ?MenuItem
    {
        return $this->repository->find($id);
    }

    public function create(StoreMenuItemDTO $dto): MenuItem
    {
        return $this->repository->create(get_object_vars($dto));
    }

    public function update(MenuItem $menuItem, UpdateMenuItemDTO $dto): MenuItem
    {
        return $this->repository->update($menuItem, $dto->toArray());
    }

    public function delete(MenuItem $menuItem): void
    {
        $this->repository->delete($menuItem);
    }
}
