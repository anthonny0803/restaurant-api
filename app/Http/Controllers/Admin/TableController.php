<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\StoreTableDTO;
use App\DTOs\UpdateTableDTO;
use App\Http\Controllers\Controller;
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
        return TableResource::collection($this->service->paginate())->response();
    }

    public function store(CreateTableRequest $request): JsonResponse
    {
        $table = $this->service->create(new StoreTableDTO(...$request->validated()));

        return (new TableResource($table))->response()->setStatusCode(201);
    }

    public function show(Table $table): TableResource
    {
        return new TableResource($table);
    }

    public function update(UpdateTableRequest $request, Table $table): TableResource
    {
        $table = $this->service->update($table, UpdateTableDTO::fromValidated($request->validated()));

        return new TableResource($table);
    }

    public function destroy(Table $table): JsonResponse
    {
        $this->service->delete($table);

        return response()->json(null, 204);
    }
}
