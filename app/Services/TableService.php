<?php

namespace App\Services;

use App\DTOs\StoreTableDTO;
use App\DTOs\UpdateTableDTO;
use App\Models\Table;
use App\Repositories\TableRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TableService
{
    public function __construct(private TableRepository $repository) {}

    public function paginate(int $perPage = 6): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function create(StoreTableDTO $dto): Table
    {
        return $this->repository->create($dto->toArray());
    }

    public function update(Table $table, UpdateTableDTO $dto): Table
    {
        return $this->repository->update($table, $dto->toArray());
    }

    public function delete(Table $table): void
    {
        $this->repository->delete($table);
    }
}
