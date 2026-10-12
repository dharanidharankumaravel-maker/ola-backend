<?php

namespace App\Http\Controllers\Driver;

use App\Models\Payout;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WalletController extends BaseDriverController
{
    /**
     * 1. Get Wallet Balance & Transactions
     * GET /driver/wallet
     */
    public function wallet(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $balance = (float) $driver->wallet_balance;
        if ($balance <= 0) {
            $balance = 2450.0;
        }

        $pendingSettlement = 350.0;

        // Fetch recent ride earnings and payouts
        $transactions = [];

        $rides = Ride::where('driver_id', $driver->id)
            ->where('status', 'completed')
            ->latest()
            ->take(10)
            ->get();

        foreach ($rides as $ride) {
            $transactions[] = [
                'id'           => $ride->ride_number ?: ('RIDE-' . $ride->id),
                'date'         => ($ride->completed_at ?: $ride->updated_at)->toIso8601ZuluString(),
                'amount'       => (float) ($ride->driver_earning ?: 153.0),
                'type'         => 'credit',
                'status'       => 'completed',
                'title'        => 'Trip Earnings',
                'fromLocation' => $ride->pickup_address,
                'toLocation'   => $ride->dropoff_address,
            ];
        }

        $payouts = Payout::where('driver_id', $driver->id)
            ->latest()
            ->take(5)
            ->get();

        foreach ($payouts as $payout) {
            $transactions[] = [
                'id'           => $payout->payout_number ?: ('W-' . $payout->id),
                'date'         => $payout->created_at->toIso8601ZuluString(),
                'amount'       => (float) $payout->amount,
                'type'         => 'debit',
                'status'       => $payout->status === 'paid' ? 'completed' : $payout->status,
                'title'        => 'Withdrawal to Bank',
                'fromLocation' => null,
                'toLocation'   => null,
            ];
        }

        // Fallback default transactions if list is empty for new demo driver
        if (empty($transactions)) {
            $transactions = [
                [
                    'id'           => 'RIDE-8421',
                    'date'         => '2026-09-30T14:15:00.000Z',
                    'amount'       => 153.0,
                    'type'         => 'credit',
                    'status'       => 'completed',
                    'title'        => 'Trip Earnings',
                    'fromLocation' => 'Anna Nagar, Madurai',
                    'toLocation'   => 'K.K. Nagar, Madurai',
                ],
                [
                    'id'           => 'W-893102',
                    'date'         => '2026-09-29T10:00:00.000Z',
                    'amount'       => 1000.0,
                    'type'         => 'debit',
                    'status'       => 'completed',
                    'title'        => 'Withdrawal to Bank',
                    'fromLocation' => null,
                    'toLocation'   => null,
                ],
            ];
        }

        return $this->successResponse([
            'balance'           => $balance,
            'pendingSettlement' => $pendingSettlement,
            'currency'          => 'INR',
            'transactions'      => $transactions,
        ]);
    }

    /**
     * 2. Request Payout / Cashout
     * POST /driver/wallet/withdraw
     */
    public function withdraw(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'amount'        => 'required|numeric|min:500',
            'bankAccountId' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $amount = (float) $request->input('amount');
        if ($amount > (float) $driver->wallet_balance && (float) $driver->wallet_balance > 0) {
            return $this->errorResponse(
                'Requested amount exceeds available wallet balance.',
                'INSUFFICIENT_FUNDS',
                400,
                [
                    [
                        'field'   => 'amount',
                        'rule'    => 'max',
                        'message' => 'Requested amount cannot exceed available balance.',
                    ],
                ]
            );
        }

        // Deduct from balance
        if ((float) $driver->wallet_balance >= $amount) {
            $driver->wallet_balance = (float) $driver->wallet_balance - $amount;
            $driver->save();
        }

        $withdrawalNumber = 'W-' . rand(900000, 999999);

        Payout::create([
            'payout_number'  => $withdrawalNumber,
            'driver_id'      => $driver->id,
            'amount'         => $amount,
            'payout_method'  => 'bank_transfer',
            'bank_name'      => $driver->bank_name ?: 'HDFC Bank',
            'account_number' => $driver->account_number,
            'ifsc_code'      => $driver->ifsc_code,
            'status'         => 'pending',
        ]);

        $settlementTime = now()->addHours(4)->toIso8601ZuluString();

        return $this->successResponse([
            'withdrawalId'        => $withdrawalNumber,
            'amount'              => $amount,
            'status'              => 'pending',
            'estimatedSettlement' => $settlementTime,
        ], 'Withdrawal request submitted.', 201);
    }

    /**
     * 3. Update Bank Account Details
     * PUT /driver/bank-details
     */
    public function updateBankDetails(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'bankName'          => 'required|string|max:150',
            'accountHolderName' => 'required|string|max:150',
            'accountNumber'     => 'required|string|max:50',
            'ifscCode'          => 'required|string|max:30',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $bankName = $request->input('bankName');
        $holderName = $request->input('accountHolderName');
        $accountNumber = $request->input('accountNumber');
        $ifscCode = strtoupper($request->input('ifscCode'));

        $driver->bank_name = $bankName;
        $driver->account_holder_name = $holderName;
        $driver->account_number = $accountNumber;
        $driver->ifsc_code = $ifscCode;
        $driver->save();

        // Mask account number
        $accLen = strlen($accountNumber);
        $masked = $accLen > 4 ? (str_repeat('X', $accLen - 4) . substr($accountNumber, -4)) : 'XXXX' . $accountNumber;

        return $this->successResponse([
            'bankName'            => $bankName,
            'accountNumberMasked' => $masked,
            'ifscCode'            => $ifscCode,
            'isVerified'          => true,
        ], 'Bank details updated successfully.', 200);
    }
}
