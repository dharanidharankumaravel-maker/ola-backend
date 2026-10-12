<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_code',
        'name',
        'email',
        'phone',
        'avatar',
        'wallet_balance',
        'rating',
        'total_rides',
        'total_spent',
        'referral_code',
        'status',
        'emergency_contact',
        'notification_prefs',
        'location_prefs',
    ];

    protected $casts = [
        'wallet_balance' => 'decimal:2',
        'total_spent'    => 'decimal:2',
        'rating'         => 'decimal:2',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->customer_code)) {
                $customer->customer_code = static::generateCustomerCode();
            }
        });
    }

    /**
     * Generate auto-incrementing customer code in format CUST-00001.
     *
     * @return string
     */
    public static function generateCustomerCode(): string
    {
        $lastCustomer = static::whereNotNull('customer_code')
            ->where('customer_code', 'like', 'CUST-%')
            ->orderByRaw('CAST(SUBSTRING(customer_code, 6) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;
        if ($lastCustomer && preg_match('/^CUST-(\d+)$/', $lastCustomer->customer_code, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return sprintf('CUST-%05d', $nextNumber);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function tokens()
    {
        return $this->hasMany(CustomerToken::class);
    }

    public function savedPlaces()
    {
        return $this->hasMany(CustomerSavedPlace::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function sosEvents()
    {
        return $this->hasMany(SosEvent::class);
    }
}
