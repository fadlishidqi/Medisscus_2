<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Program extends BaseModel
{
    protected $fillable = [
        'title',
        'description',
        'slug',
        'price',
        'is_active',
        'images'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'images' => 'array'
    ];

    protected static function boot()
    {
        parent::boot();

        // create
        static::creating(function ($program) {
            if (empty($program->slug)) {
                $program->slug = static::generateUniqueSlug($program->title);
            }
        });

        // update
        static::updating(function ($program) {
            if ($program->isDirty('title') && empty($program->slug)) {
                $program->slug = static::generateUniqueSlug($program->title, $program->id);
            }
        });
    }

    public static function generateUniqueSlug($title, $id = null)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        $query = static::where('slug', $slug);
        if ($id) {
            $query->where('id', '!=', $id);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
            $query = static::where('slug', $slug);
            if ($id) {
                $query->where('id', '!=', $id);
            }
        }

        return $slug;
    }

    // Relationships
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->where('is_active', true);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('title', 'like', '%' . $search . '%')
            ->orWhere('description', 'like', '%' . $search . '%');
    }

    // Accessors
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    public function getIsFreeAttribute(): bool
    {
        return $this->price == 0;
    }

    public function getEnrollmentCountAttribute(): int
    {
        return $this->activeEnrollments()->count();
    }

    public function getImageUrlsAttribute(): array
    {
        if (!$this->images || !is_array($this->images)) {
            return [];
        }

        return array_map(function ($image) {
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                return $image;
            }
            return asset('storage/' . $image);
        }, $this->images);
    }

    public function tryouts()
    {
        return $this->hasMany(Tryout::class);
    }
}
