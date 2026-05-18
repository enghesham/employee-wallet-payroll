<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Banking\Actions\RequestBankWithdrawalAction;
use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWithdrawalRequest;
use App\Http\Resources\Api\V1\WithdrawalRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WithdrawalRequestController extends Controller
{
    public function store(StoreWithdrawalRequest $request, Wallet $wallet, RequestBankWithdrawalAction $action): JsonResponse
    {
        $withdrawal = $action->execute(
            wallet: $wallet,
            data: $request->safe()->except('idempotency_key'),
            idempotencyKey: $request->validated('idempotency_key'),
        );

        return (new WithdrawalRequestResource($withdrawal))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
