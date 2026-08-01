<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'cover_image_url',
        'visibility',
        'location_type',
        'location_address',
        'location_city',
        'location_state',
        'location_country',
        'location_zip',
        'location_latitude',
        'location_longitude',
        'online_meeting_url',
        'starts_at',
        'ends_at',
        'timezone',
        'capacity',
        'total_registered',
        'enable_waitlist',
        'auto_approve_registrations',
        'require_approval',
        'registration_opens_at',
        'registration_closes_at',
        'category',
        'tags',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'enable_waitlist' => 'boolean',
        'auto_approve_registrations' => 'boolean',
        'require_approval' => 'boolean',
        'tags' => 'array',
        'location_latitude' => 'decimal:7',
        'location_longitude' => 'decimal:7',
    ];

    protected $hidden = ['id', 'organization_id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }

            if (empty($event->slug)) {
                $event->slug = Str::slug($event->name);
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    // Relationships
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class)->orderBy('order');
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function confirmedRegistrations()
    {
        return $this->registrations()->where('status', 'confirmed');
    }

    public function invites()
    {
        return $this->hasMany(EventInvite::class);
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public')
            ->where('status', 'published');
    }

    public function scopePrivate($query)
    {
        return $query->where('visibility', 'private');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now())
            ->orderBy('starts_at', 'asc');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByLocation($query, $city = null, $country = null)
    {
        if ($city) {
            $query->where('location_city', 'like', "%{$city}%");
        }
        if ($country) {
            $query->where('location_country', $country);
        }
        return $query;
    }

    public function scopeNearby($query, $latitude, $longitude, $radiusKm = 50)
    {
        // Haversine formula for geographical proximity
        $query->selectRaw("
            *, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(location_latitude)) *
                    cos(radians(location_longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(location_latitude))
                )
            ) AS distance
        ", [$latitude, $longitude, $latitude])
        ->having('distance', '<=', $radiusKm)
        ->orderBy('distance');

        return $query;
    }

    // Helper methods
    public function isFull(): bool
    {
        if (!$this->capacity) {
            return false; // Unlimited capacity
        }

        return $this->total_registered >= $this->capacity;
    }

    public function availableSpots(): ?int
    {
        if (!$this->capacity) {
            return null; // Unlimited
        }

        return max(0, $this->capacity - $this->total_registered);
    }

    public function isRegistrationOpen(): bool
    {
        $now = now();

        if ($this->registration_opens_at && $now->lt($this->registration_opens_at)) {
            return false;
        }

        if ($this->registration_closes_at && $now->gt($this->registration_closes_at)) {
            return false;
        }

        return $this->status === 'published';
    }

    public function canUserRegister(?User $user = null): bool
    {
        if ($this->visibility === 'private' && !$user) {
            return false; // Private events require authentication
        }

        return $this->isRegistrationOpen() && !$this->isFull();
    }
}
