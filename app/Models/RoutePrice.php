<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoutePrice extends Model
{
    protected $fillable = [
        'route_id',
        'vehicle_type_id',
        'price',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }
}