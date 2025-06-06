<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'username',
        'email',
        'university',
        'phone',
        'role',
        'password',
        'profile_image',
        'is_active',
        'device_id',
        'device_name',
        'last_login_at',
        'last_login_ip',
        'user_agent',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [
            'user_id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'device_id' => $this->device_id,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByUniversity($query, $university)
    {
        return $query->where('university', $university);
    }

    public function scopeByDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }

    /**
     * Update user's device information
     */
    public function updateDeviceInfo($deviceId, $deviceName, $ipAddress, $userAgent)
    {
        $this->update([
            'device_id' => $deviceId,
            'device_name' => $deviceName,
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Clear device information (logout)
     */
    public function clearDeviceInfo()
    {
        $this->update([
            'device_id' => null,
            'device_name' => null,
            'last_login_at' => now(),
            'last_login_ip' => null,
            'user_agent' => null,
        ]);
    }

    /**
     * Check if user is logged in from another device
     */
    public function isLoggedInFromAnotherDevice($currentDeviceId)
    {
        return $this->device_id && $this->device_id !== $currentDeviceId;
    }

    /**
     * Generate device ID from request
     */
    public static function generateDeviceId($request)
    {
        $userAgent = $request->header('User-Agent', '');
        $ipAddress = $request->ip();
        
        return hash('sha256', $userAgent . $ipAddress . config('app.key'));
    }

    /**
     * Get device name from User Agent
     */
    public static function getDeviceName($userAgent)
    {
        $userAgent = strtolower($userAgent);
        
        // Detect mobile devices
        if (strpos($userAgent, 'mobile') !== false || 
            strpos($userAgent, 'android') !== false || 
            strpos($userAgent, 'iphone') !== false ||
            strpos($userAgent, 'ipad') !== false) {
            
            if (strpos($userAgent, 'android') !== false) {
                return 'Android Device';
            } elseif (strpos($userAgent, 'iphone') !== false) {
                return 'iPhone';
            } elseif (strpos($userAgent, 'ipad') !== false) {
                return 'iPad';
            } else {
                return 'Mobile Device';
            }
        }
        
        // Detect desktop browsers
        if (strpos($userAgent, 'chrome') !== false) {
            return 'Chrome Browser';
        } elseif (strpos($userAgent, 'firefox') !== false) {
            return 'Firefox Browser';
        } elseif (strpos($userAgent, 'safari') !== false) {
            return 'Safari Browser';
        } elseif (strpos($userAgent, 'edge') !== false) {
            return 'Edge Browser';
        } else {
            return 'Desktop Browser';
        }
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->where('is_active', true);
    }

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'enrollments')
                    ->withPivot('is_active', 'paid_at')
                    ->withTimestamps();
    }
}