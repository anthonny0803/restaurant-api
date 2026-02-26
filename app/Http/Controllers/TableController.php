<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Http\Resources\TableResource;
use App\Models\Table;
use App\Services\TableService;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    public function __construct(private TableService $service) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Table::class);

        return TableResource::collection($this->service->paginate())->response();
    }

    public function store(CreateTableRequest $request): JsonResponse
    {
        $this->authorize('create', Table::class);

        $table = $this->service->create($request->validated());

        return (new TableResource($table))->response()->setStatusCode(201);
    }

    public function show(Table $table): TableResource
    {
        $this->authorize('view', $table);

        return new TableResource($table);
    }

    public function update(UpdateTableRequest $request, Table $table): TableResource
    {
        $this->authorize('update', $table);

        $table = $this->service->update($table, $request->validated());

        return new TableResource($table);
    }

    public function destroy(Table $table): JsonResponse
    {
        $this->authorize('delete', $table);

        $this->service->delete($table);

        return response()->json(null, 204);
    }
}
