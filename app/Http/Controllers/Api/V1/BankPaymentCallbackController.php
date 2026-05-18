<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Banking\Actions\HandleBankPaymentCallbackAction;
use App\Domain\Banking\Models\BankPaymentRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBankPaymentCallbackRequest;
use App\Http\Resources\Api\V1\BankPaymentRequestResource;

class BankPaymentCallbackController extends Controller
{
    public function store(
        StoreBankPaymentCallbackRequest $request,
        BankPaymentRequest $bankPaymentRequest,
        HandleBankPaymentCallbackAction $action,
    ): BankPaymentRequestResource {
        $payment = $action->execute($bankPaymentRequest, $request->validated());

        return new BankPaymentRequestResource($payment);
    }
}
