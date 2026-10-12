<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'reg_number',
        'vehicle_type',
        'make',
        'model',
        'year',
        'color',
        'owner_name',
        'status',
        'service_type',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function documents()
    {
        return $this->hasMany(DriverDocument::class);
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }
}
