<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSavedPlace extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'title',
        'subtitle',
        'latitude',
        'longitude',
        'formatted_address',
        'short_address',
    ];

    protected $casts = [
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
