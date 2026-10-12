<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Module 8: Alo Money (Wallet) & Payments
 */
class WalletController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // 8.1  GET /wallet
    // ──────────────────────────────────────────────────────────────────────────
    public function wallet(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 20);

        $total        = WalletTransaction::where('customer_id', $customer->id)->count();
        $transactions = WalletTransaction::where('customer_id', $customer->id)
            ->latest()
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(fn($t) => [
                'id'          => $t->id,
                'txnNumber'   => $t->transaction_number,
                'type'        => $t->type,
                'amount'      => (float) $t->amount,
                'description' => $t->description,
                'status'      => $t->status,
                'referenceId' => $t->reference_id,
                'createdAt'   => $t->created_at?->format('Y-m-d\TH:i:s\Z'),
            ]);

        return $this->successResponse([
            'balance'      => (float) $customer->wallet_balance,
            'currency'     => 'INR',
            'passbook'     => $transactions,
            'meta'         => [
                'total'      => $total,
                'page'       => $page,
                'limit'      => $limit,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ], 'Wallet retrieved');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8.2  POST /wallet/topup
    // ──────────────────────────────────────────────────────────────────────────
    public function topup(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $pm      = $request->input('paymentMethod') ?? $request->input('payment_method', 'upi');
        $gateway = $request->input('gateway') ?? $request->input('payment_gateway', 'razorpay');

        $validator = Validator::make([
            'amount'        => $request->input('amount'),
            'paymentMethod' => $pm,
            'gateway'       => $gateway,
        ], [
            'amount'         => ['required', 'numeric', 'min:1', 'max:100000'],
            'paymentMethod'  => ['required', 'in:upi,card,netbanking,wallet'],
            'gateway'        => ['nullable', 'string', 'in:razorpay,stripe,payu'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $amount  = (float) $request->input('amount');

        // Simulate payment gateway order creation
        $orderId     = strtoupper($gateway[0]) . 'ORD_' . Str::random(16);
        $gatewayKey  = 'rzp_test_XXXXXXXXXXXXXXXX';

        // The actual top-up is credited AFTER gateway callback (webhook).
        // Here we simulate immediate credit for the demo.
        DB::transaction(function () use ($customer, $amount, $gateway, $orderId) {
            $customer->increment('wallet_balance', $amount);

            WalletTransaction::create([
                'customer_id'     => $customer->id,
                'type'            => 'credit',
                'amount'          => $amount,
                'description'     => 'Wallet top-up via ' . strtoupper($gateway),
                'status'          => 'completed',
                'reference_id'    => $orderId,
                'payment_gateway' => $gateway,
            ]);
        });

        $customer->refresh();

        return $this->successResponse([
            'orderId'        => $orderId,
            'amount'         => $amount,
            'currency'       => 'INR',
            'gatewayKey'     => $gatewayKey,
            'newBalance'     => (float) $customer->wallet_balance,
            'status'         => 'completed',
        ], 'Wallet topped up successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8.3  POST /rides/:id/pay
    // ──────────────────────────────────────────────────────────────────────────
    public function pay(Request $request, string $id): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $validator = Validator::make($request->all(), [
            'paymentMethod'  => ['required', 'in:cash,wallet,upi,card'],
            'transactionId'  => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $ride = Ride::where('customer_id', $customer->id)
            ->where(fn($q) => $q->where('id', $id)->orWhere('ride_number', $id))
            ->first();

        if (!$ride) {
            return $this->errorResponse('NOT_FOUND', 'Ride not found.', 404);
        }

        if ($ride->payment_status === 'paid') {
            return $this->errorResponse('ALREADY_PAID', 'This ride has already been paid.', 400);
        }

        $amount        = (float) ($ride->total_fare ?? 0) + (float) ($ride->tip_amount ?? 0);
        $paymentMethod = $request->input('paymentMethod');

        // Wallet payment — deduct balance
        if ($paymentMethod === 'wallet') {
            if ((float) $customer->wallet_balance < $amount) {
                return $this->errorResponse('INSUFFICIENT_BALANCE', 'Insufficient wallet balance.', 400);
            }
            $customer->decrement('wallet_balance', $amount);
        }

        $txnId = $request->input('transactionId') ?? 'TXN_' . strtoupper(Str::random(12));

        DB::transaction(function () use ($ride, $customer, $amount, $paymentMethod, $txnId) {
            $ride->update([
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
            ]);

            // Update customer totals
            $customer->increment('total_spent', $amount);
            $customer->increment('total_rides');

            // Record payment
            Payment::firstOrCreate(
                ['ride_id' => $ride->id],
                [
                    'payment_number' => 'PMT-' . rand(10000, 99999),
                    'customer_id'    => $customer->id,
                    'amount'         => $amount,
                    'payment_method' => $paymentMethod,
                    'transaction_id' => $txnId,
                    'status'         => 'completed',
                ]
            );

            // Record wallet transaction (debit side)
            if ($paymentMethod !== 'cash') {
                WalletTransaction::create([
                    'customer_id'  => $customer->id,
                    'ride_id'      => $ride->id,
                    'type'         => 'debit',
                    'amount'       => $amount,
                    'description'  => 'Trip payment for ride ' . $ride->ride_number,
                    'status'       => 'completed',
                    'reference_id' => $txnId,
                ]);
            }
        });

        return $this->successResponse([
            'rideId'         => $ride->ride_number,
            'amount'         => $amount,
            'currency'       => 'INR',
            'paymentMethod'  => $paymentMethod,
            'transactionId'  => $txnId,
            'paymentStatus'  => 'paid',
            'walletBalance'  => (float) $customer->fresh()->wallet_balance,
        ], 'Payment successful');
    }

    protected function successResponse($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $r = ['success' => true, 'statusCode' => $statusCode];
        if ($message) $r['message'] = $message;
        if ($data !== null) $r['data'] = $data;
        return response()->json($r, $statusCode);
    }

    protected function errorResponse(string $code, string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success'    => false,
            'statusCode' => $statusCode,
            'error'      => ['code' => $code, 'message' => $message],
        ], $statusCode);
    }
}
