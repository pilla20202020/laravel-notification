<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'payload',
        'scheduled_at',
        'status',
        'attempts',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
        'payload'      => 'array',
    ];

    /**
     * A notification belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
