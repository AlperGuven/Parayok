<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomParticipant extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'user_id',
        'role',
        'is_online',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
