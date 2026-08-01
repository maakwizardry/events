<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'invited_by',
        'email',
        'token',
        'status',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['id', 'event_id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invite) {
            if (empty($invite->uuid)) {
                $invite->uuid = (string) Str::uuid();
            }

            if (empty($invite->token)) {
                $invite->token = Str::random(64);
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    // Relationships
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // Helper methods
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now()->gt($this->expires_at);
    }

    public function accept(): bool
    {
        if ($this->status === 'accepted' || $this->isExpired()) {
            return false;
        }

        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return true;
    }

    public function decline(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->update(['status' => 'declined']);

        return true;
    }
}
