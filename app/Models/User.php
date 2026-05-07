<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
protected $fillable = [
    'name',
    'email',
    'password',
    'user_type',
    'phone',
    'profile_photo',
];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
// Relationship: User has one Driver profile (if user_type is driver)
    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    // Relationship: User has many Bookings as Passenger
    public function passengerBookings()
    {
        return $this->hasMany(Booking::class, 'passenger_id');
    }

    // Relationship: User has many Bookings as Driver
    public function driverBookings()
    {
        return $this->hasMany(Booking::class, 'driver_id');
    }

    // Relationship: User created many Routes (if admin)
    public function createdRoutes()
    {
        return $this->hasMany(Route::class, 'created_by');
    }
}
