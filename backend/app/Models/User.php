<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'jira_account_id',
        'display_name',
        'email',
        'avatar_url',
        'jira_access_token',
        'jira_refresh_token',
        'jira_cloud_id',
        'jira_site_url',
        'jira_story_points_field_id',
        'token_expires_at',
    ];

    protected $hidden = [
        'jira_access_token',
        'jira_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
        ];
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_participants')
            ->withPivot('role', 'is_online', 'joined_at');
    }

    public function createdRooms()
    {
        return $this->hasMany(Room::class, 'created_by');
    }
}
