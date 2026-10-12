<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SosEvent extends Model
{
    use HasFactory;

    protected $table = 'sos_events';

    protected $fillable = [
        'customer_id',
        'ride_id',
        'latitude',
        'longitude',
        'emergency_contact',
        'status',
        'triggered_at',
    ];

    protected $casts = [
        'latitude'     => 'decimal:7',
        'longitude'    => 'decimal:7',
        'triggered_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}
