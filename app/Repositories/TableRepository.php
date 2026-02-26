<?php

namespace App\Repositories;

use App\Models\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TableRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Table::paginate($perPage);
    }

    public function find(int $id): ?Table
    {
        return Table::find($id);
    }

    public function create(array $data): Table
    {
        return Table::create($data);
    }

    public function update(Table $table, array $data): Table
    {
        $table->update($data);

        return $table;
    }

    public function delete(Table $table): void
    {
        $table->delete();
    }
}
