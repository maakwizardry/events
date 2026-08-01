<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TicketType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'quantity',
        'quantity_sold',
        'order',
        'price',
        'currency',
        'sales_start_at',
        'sales_end_at',
        'is_visible',
        'min_per_order',
        'max_per_order',
    ];

    protected $casts = [
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'is_visible' => 'boolean',
        'price' => 'decimal:2',
    ];

    protected $hidden = ['id', 'event_id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticketType) {
            if (empty($ticketType->uuid)) {
                $ticketType->uuid = (string) Str::uuid();
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

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    // Helper methods
    public function isAvailable(): bool
    {
        $now = now();

        if ($this->sales_start_at && $now->lt($this->sales_start_at)) {
            return false;
        }

        if ($this->sales_end_at && $now->gt($this->sales_end_at)) {
            return false;
        }

        return $this->is_visible && !$this->isSoldOut();
    }

    public function isSoldOut(): bool
    {
        if (!$this->quantity) {
            return false; // Unlimited
        }

        return $this->quantity_sold >= $this->quantity;
    }

    public function availableQuantity(): ?int
    {
        if (!$this->quantity) {
            return null; // Unlimited
        }

        return max(0, $this->quantity - $this->quantity_sold);
    }
}
