<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends BaseModel
{
    protected $fillable = [
        'user_id',
        'program_id',
        'is_active',
        'paid_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'paid_at' => 'datetime'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePaid($query)
    {
        return $query->whereNotNull('paid_at');
    }

    public function scopeUnpaid($query)
    {
        return $query->whereNull('paid_at');
    }

    // Accessors
    public function getIsPaidAttribute(): bool
    {
        return !is_null($this->paid_at);
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }
        
        return $this->is_paid ? 'paid' : 'unpaid';
    }
}