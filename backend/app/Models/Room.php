<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Room extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'created_by',
        'status',
        'estimation_scale',
    ];

    protected function casts(): array
    {
        return [
            'estimation_scale' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_participants')
            ->withPivot('role', 'is_online', 'joined_at');
    }

    public function roomParticipants(): HasMany
    {
        return $this->hasMany(RoomParticipant::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class)->orderBy('sort_order');
    }

    public function isParticipant(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }
}
