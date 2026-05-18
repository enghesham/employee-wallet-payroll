<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallets\Actions\TransferWalletFundsAction;
use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWalletTransferRequest;
use App\Http\Resources\Api\V1\WalletLedgerEntryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WalletTransferController extends Controller
{
    public function store(StoreWalletTransferRequest $request, Wallet $wallet, TransferWalletFundsAction $action): JsonResponse
    {
        $entries = $action->execute(
            fromWallet: $wallet,
            data: $request->safe()->except('idempotency_key'),
            idempotencyKey: $request->validated('idempotency_key'),
        );

        return response()->json([
            'data' => [
                'debit' => (new WalletLedgerEntryResource($entries['debit']))->resolve($request),
                'credit' => (new WalletLedgerEntryResource($entries['credit']))->resolve($request),
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
