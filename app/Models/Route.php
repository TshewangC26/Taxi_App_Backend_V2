<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $fillable = [
        'pickup_location',
        'dropoff_location',
        'price_4_seater',
        'price_7_seater',
        'price_8_seater',
        'is_active',
        'created_by'
    ];

    // Relationship: Route created by an Admin (User)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}