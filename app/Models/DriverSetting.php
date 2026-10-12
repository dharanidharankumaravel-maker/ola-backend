<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'notifications_enabled',
        'language',
        'theme_mode',
        'auto_accept_rides',
        'navigation_app',
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'auto_accept_rides' => 'boolean',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
