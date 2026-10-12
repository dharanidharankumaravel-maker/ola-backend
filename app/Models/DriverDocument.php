<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'document_type',
        'document_number',
        'expiry_date',
        'front_image_url',
        'back_image_url',
        'verification_status',
        'status',
        'uploaded_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'uploaded_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
