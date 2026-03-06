<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    protected $fillable = [
        'room_id',
        'jira_issue_id',
        'jira_issue_key',
        'summary',
        'description',
        'jira_url',
        'status',
        'final_score',
        'sort_order',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'final_score' => 'decimal:1',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function getVoteCount(): int
    {
        return $this->votes()->count();
    }
}
