<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_number',
        'license_number',
        'license_image',
        'is_available',
        'status',
        'bank_name',
        'account_holder_name',
        'account_number',
        'qr_code_image',
        'mobile_payment_number',
        'latitude',
        'longitude',
        'location_updated_at',
        'average_rating',  // ✅
        'total_ratings',   // ✅
    ];

    // Status constants
    const STATUS_AVAILABLE = 'available';
    const STATUS_BOOKED    = 'booked';
    const STATUS_OFFLINE   = 'offline';

    // Relationship: Driver belongs to a User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: Driver has many Bookings
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'driver_id', 'user_id');
    }

    // Helper: set driver as available
    public function setAvailable()
    {
        $this->status       = self::STATUS_AVAILABLE;
        $this->is_available = true;
        $this->save();
    }

    // Helper: set driver as booked
    public function setBooked()
    {
        $this->status       = self::STATUS_BOOKED;
        $this->is_available = false;
        $this->save();
    }

    // Helper: set driver as offline
    public function setOffline()
    {
        $this->status       = self::STATUS_OFFLINE;
        $this->is_available = false;
        $this->save();
    }

    // Helper: update driver location
    public function updateLocation($latitude, $longitude)
    {
        $this->latitude            = $latitude;
        $this->longitude           = $longitude;
        $this->location_updated_at = now();
        $this->save();
    }

    // Helper: calculate distance from a point in km
    public function distanceFrom($latitude, $longitude)
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }

        $earthRadius = 6371; // km

        $latDiff = deg2rad($latitude - $this->latitude);
        $lngDiff = deg2rad($longitude - $this->longitude);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($this->latitude)) *
             cos(deg2rad($latitude)) *
             sin($lngDiff / 2) * sin($lngDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}