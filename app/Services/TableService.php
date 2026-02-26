<?php

namespace App\Services;

use App\Models\Table;
use App\Repositories\TableRepository;
use Illuminate\Database\Eloquent\Collection;

class TableService
{
    public function __construct(private TableRepository $repository) {}

    public function getAll(): Collection
    {
        return $this->repository->all();
    }

    public function getById(int $id): ?Table
    {
        return $this->repository->find($id);
    }

    public function create(array $data): Table
    {
        return $this->repository->create($data);
    }

    public function update(Table $table, array $data): Table
    {
        return $this->repository->update($table, $data);
    }

    public function delete(Table $table): void
    {
        $this->repository->delete($table);
    }
}
