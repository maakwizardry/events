<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Registration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'ticket_type_id',
        'user_id',
        'guest_email',
        'guest_name',
        'guest_phone',
        'qr_code_path',
        'qr_code_data',
        'quantity',
        'status',
        'is_checked_in',
        'checked_in_at',
        'checked_in_by',
        'check_in_location',
        'custom_fields',
        'notes',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'is_checked_in' => 'boolean',
        'custom_fields' => 'array',
    ];

    protected $hidden = ['id', 'event_id', 'ticket_type_id', 'user_id'];

    protected $appends = ['attendee_name', 'attendee_email'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($registration) {
            if (empty($registration->uuid)) {
                $registration->uuid = (string) Str::uuid();
            }

            if (empty($registration->qr_code_data)) {
                $registration->qr_code_data = Str::random(32);
            }
        });

        static::created(function ($registration) {
            $registration->generateQrCode();
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

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    // Accessors
    public function getAttendeeNameAttribute()
    {
        return $this->user?->name ?? $this->guest_name;
    }

    public function getAttendeeEmailAttribute()
    {
        return $this->user?->email ?? $this->guest_email;
    }

    // Methods
    public function generateQrCode()
    {
        $qrCode = QrCode::format('png')
            ->size(300)
            ->generate($this->qr_code_data);

        $path = "qr-codes/{$this->event_id}/{$this->uuid}.png";
        Storage::disk('public')->put($path, $qrCode);

        $this->update(['qr_code_path' => $path]);
    }

    public function checkIn(User $checkedInBy, $location = null): bool
    {
        if ($this->is_checked_in) {
            return false; // Already checked in
        }

        $this->update([
            'is_checked_in' => true,
            'checked_in_at' => now(),
            'checked_in_by' => $checkedInBy->id,
            'check_in_location' => $location,
        ]);

        return true;
    }
}
