<?php

namespace App\Services;

use App\Models\Table;
use App\Repositories\TableRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TableService
{
    public function __construct(private TableRepository $repository) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
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
