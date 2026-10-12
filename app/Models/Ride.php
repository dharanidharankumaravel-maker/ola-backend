<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_number',
        'customer_id',
        'driver_id',
        'vehicle_id',
        'vehicle_type',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'dropoff_address',
        'dropoff_lat',
        'dropoff_lng',
        'distance_km',
        'duration_mins',
        'base_fare',
        'distance_fare',
        'time_fare',
        'tax_amount',
        'surge_amount',
        'discount_amount',
        'total_fare',
        'driver_earning',
        'platform_fee',
        'payment_method',
        'payment_status',
        'status',
        'cancel_reason',
        'cancelled_by',
        'otp',
        'polyline',
        'accepted_at',
        'arrived_at',
        'reached_destination_at',
        'toll_charges',
        'parking_charges',
        'cancellation_fee',
        'cancellation_fee_eligible',
        'customer_review',
        'customer_rating',
        'started_at',
        'completed_at',
        'cancelled_at',
        // New customer-app fields
        'tip_amount',
        'ride_category',
        'scheduled_at',
        'driver_review',
        'driver_rating',
        'share_token',
        'parcel_details',
        'promo_code',
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'distance_km' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'distance_fare' => 'decimal:2',
        'time_fare' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'surge_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_fare' => 'decimal:2',
        'driver_earning' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'toll_charges' => 'decimal:2',
        'parking_charges' => 'decimal:2',
        'cancellation_fee' => 'decimal:2',
        'cancellation_fee_eligible' => 'boolean',
        'customer_rating' => 'decimal:2',
        'tip_amount'      => 'decimal:2',
        'driver_rating'   => 'decimal:2',
        'accepted_at'     => 'datetime',
        'arrived_at'      => 'datetime',
        'reached_destination_at' => 'datetime',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
        'scheduled_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Ride $ride) {
            if (empty($ride->ride_number)) {
                $ride->ride_number = static::generateRideNumber();
            }
        });
    }

    public static function generateRideNumber(): string
    {
        $lastRide = static::whereNotNull('ride_number')
            ->where('ride_number', 'like', 'RIDE-%')
            ->orderByRaw('CAST(SUBSTRING(ride_number, 6) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;
        if ($lastRide && preg_match('/^RIDE-(\d+)$/', $lastRide->ride_number, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return sprintf('RIDE-%06d', $nextNumber);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }
}
