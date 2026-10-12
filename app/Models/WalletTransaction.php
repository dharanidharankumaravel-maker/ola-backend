<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'customer_id',
        'ride_id',
        'type',
        'amount',
        'description',
        'status',
        'reference_id',
        'payment_gateway',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (WalletTransaction $txn) {
            if (empty($txn->transaction_number)) {
                $txn->transaction_number = 'TXN-' . strtoupper(Str::random(10));
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}
