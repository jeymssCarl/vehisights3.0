<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'email_verified_at',
        'password',
        'department',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Get the cameras created by this user.
     */
    public function cameras()
    {
        return $this->hasMany(Camera::class, 'created_by');
    }

    /**
     * Get the manual sessions created by this user.
     */
    public function manualSessions()
    {
        return $this->hasMany(ManualSession::class, 'created_by');
    }

    /**
     * Get the auto sessions created by this user.
     */
    public function autoSessions()
    {
        return $this->hasMany(AutoSession::class, 'created_by');
    }

    /**
     * Get the schedules created by this user.
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'created_by');
    }

    /**
     * Get the manual counts created by this user.
     */
    public function manualCounts()
    {
        return $this->hasMany(ManualCount::class, 'created_by');
    }

    /**
     * Get the sessions created by this user.
     */
    public function sessions()
    {
        return $this->hasMany(Session::class, 'created_by');
    }

    /**
     * Get the camera logs created by this user.
     */
    public function cameraLogs()
    {
        return $this->hasMany(CameraLog::class, 'created_by');
    }

    /**
     * Get the system settings updated by this user.
     */
    public function systemSettings()
    {
        return $this->hasMany(SystemSetting::class, 'updated_by');
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin()
    {
        return $this->role === 'admin' || $this->role === 'super_admin';
    }

    /**
     * Check if user is an analyst.
     */
    public function isAnalyst()
    {
        return $this->role === 'analyst';
    }

    /**
     * Check if user is a viewer.
     */
    public function isViewer()
    {
        return $this->role === 'viewer';
    }

    /**
     * Get user's full name.
     */
    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }
}
