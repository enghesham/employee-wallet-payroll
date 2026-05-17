<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Employees\Actions\CreateEmployeeAction;
use App\Domain\Employees\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexEmployeeRequest;
use App\Http\Requests\Api\V1\StoreEmployeeRequest;
use App\Http\Resources\Api\V1\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EmployeeController extends Controller
{
    public function index(IndexEmployeeRequest $request): AnonymousResourceCollection
    {
        $employees = Employee::query()
            ->withCount('wallets')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request, CreateEmployeeAction $action): JsonResponse
    {
        $employee = $action->execute($request->validated());

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee->loadCount('wallets'));
    }
}
