<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'passenger_id',
        'driver_id',
        'pickup_location',
        'dropoff_location',
        'vehicle_type',
        'estimated_price',
        'final_price',
        'status',
        'booking_type',
        'scheduled_date',
        'scheduled_time',
        'passenger_latitude',
        'passenger_longitude',
        'cancellation_reason', // ✅
        'rating',              // ✅
        'rating_comment',      // ✅
    ];

    // Relationship: Booking belongs to a Passenger (User)
    public function passenger()
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    // Relationship: Booking belongs to a Driver (User)
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    // Relationship: Booking belongs to Driver profile
    public function driverProfile()
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'user_id');
    }

    // Relationship: Booking has one Payment
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}