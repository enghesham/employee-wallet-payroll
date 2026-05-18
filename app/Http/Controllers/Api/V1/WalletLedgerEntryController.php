<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallets\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexWalletLedgerEntryRequest;
use App\Http\Resources\Api\V1\WalletLedgerEntryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletLedgerEntryController extends Controller
{
    public function index(IndexWalletLedgerEntryRequest $request, Wallet $wallet): AnonymousResourceCollection
    {
        $entries = $wallet->ledgerEntries()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->string('source_type')))
            ->when($request->filled('date_from'), fn ($query) => $query->where('created_at', '>=', $request->date('date_from')->startOfDay()))
            ->when($request->filled('date_to'), fn ($query) => $query->where('created_at', '<=', $request->date('date_to')->endOfDay()))
            ->when($request->filled('amount_min'), fn ($query) => $query->where('amount', '>=', $request->input('amount_min')))
            ->when($request->filled('amount_max'), fn ($query) => $query->where('amount', '<=', $request->input('amount_max')))
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return WalletLedgerEntryResource::collection($entries);
    }
}
