<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Employees\Models\Employee;
use App\Domain\Wallets\Actions\CreateWalletAction;
use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexWalletRequest;
use App\Http\Requests\Api\V1\StoreWalletRequest;
use App\Http\Resources\Api\V1\WalletResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WalletController extends Controller
{
    public function index(IndexWalletRequest $request): AnonymousResourceCollection
    {
        return $this->walletCollection($request);
    }

    public function employeeIndex(IndexWalletRequest $request, Employee $employee): AnonymousResourceCollection
    {
        return $this->walletCollection($request, $employee);
    }

    public function store(StoreWalletRequest $request, Employee $employee, CreateWalletAction $action): JsonResponse
    {
        $wallet = $action->execute($employee, $request->validated());

        return (new WalletResource($wallet->load('employee')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Wallet $wallet): WalletResource
    {
        return new WalletResource($wallet->load('employee'));
    }

    private function walletCollection(IndexWalletRequest $request, ?Employee $employee = null): AnonymousResourceCollection
    {
        $wallets = Wallet::query()
            ->with('employee')
            ->when($employee !== null, fn ($query) => $query->whereBelongsTo($employee))
            ->when($request->filled('currency'), fn ($query) => $query->where('currency', $request->string('currency')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return WalletResource::collection($wallets);
    }
}
