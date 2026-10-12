<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'driver_code',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'avatar',
        'license_number',
        'rc_number',
        'vehicle_id',
        'status',
        'online_status',
        'active_session_started_at',
        'current_lat',
        'current_lng',
        'bearing',
        'speed',
        'rating',
        'total_trips',
        'total_earnings',
        'wallet_balance',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'upi_id',
        'documents_verified',
        'id_proof_url',
        'license_doc_url',
        'rc_doc_url',
        'insurance_doc_url',
        'dl_expiry_date',
        'insurance_expiry_date',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'wallet_balance' => 'decimal:2',
        'documents_verified' => 'boolean',
        'current_lat' => 'decimal:7',
        'current_lng' => 'decimal:7',
        'bearing' => 'decimal:2',
        'speed' => 'decimal:2',
        'dl_expiry_date' => 'datetime',
        'insurance_expiry_date' => 'datetime',
        'active_session_started_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $name !== '' ? $name : ($this->name ?? 'Driver');
    }

    public static function generateDriverCode(): string
    {
        $last = self::max('id') ?? 0;
        return 'drv_' . (10290 + $last + 1);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function documents()
    {
        return $this->hasMany(DriverDocument::class);
    }

    public function tokens()
    {
        return $this->hasMany(DriverToken::class);
    }

    public function settings()
    {
        return $this->hasOne(DriverSetting::class);
    }
}
