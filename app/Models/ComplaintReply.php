<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'sender_type',
        'sender_id',
        'sender_name',
        'message',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }
}
