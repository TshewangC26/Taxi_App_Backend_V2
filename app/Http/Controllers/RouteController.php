<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RoutePrice;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    // Get all active routes with dynamic prices
    public function index()
    {
        $routes = Route::where('is_active', true)->get()->map(function ($route) {
            $prices = RoutePrice::where('route_id', $route->id)
                ->with('vehicleType')
                ->get()
                ->mapWithKeys(function ($rp) {
                    return [$rp->vehicleType->name => [
                        'id'    => $rp->vehicle_type_id,
                        'price' => $rp->price,
                    ]];
                });
            $routeArray           = $route->toArray();
            $routeArray['prices'] = $prices;
            return $routeArray;
        });

        return response()->json(['routes' => $routes]);
    }

    // Get a specific route
    public function show($id)
    {
        $route      = Route::findOrFail($id);
        $prices     = RoutePrice::where('route_id', $route->id)
            ->with('vehicleType')
            ->get()
            ->mapWithKeys(function ($rp) {
                return [$rp->vehicleType->name => [
                    'id'    => $rp->vehicle_type_id,
                    'price' => $rp->price,
                ]];
            });
        $routeArray           = $route->toArray();
        $routeArray['prices'] = $prices;

        return response()->json(['route' => $routeArray]);
    }

    // Create a new route (Admin only)
    public function store(Request $request)
    {
        $request->validate([
            'pickup_location'  => 'required|string',
            'dropoff_location' => 'required|string',
            'price_4_seater'   => 'required|numeric',
            'price_7_seater'   => 'required|numeric',
            'price_8_seater'   => 'required|numeric',
        ]);

        $route = Route::create([
            'pickup_location'  => $request->pickup_location,
            'dropoff_location' => $request->dropoff_location,
            'price_4_seater'   => $request->price_4_seater,
            'price_7_seater'   => $request->price_7_seater,
            'price_8_seater'   => $request->price_8_seater,
            'is_active'        => true,
            'created_by'       => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Route created successfully',
            'route'   => $route
        ], 201);
    }

    // Update a route (Admin only)
    public function update(Request $request, $id)
    {
        $route = Route::findOrFail($id);

        $request->validate([
            'pickup_location'  => 'string',
            'dropoff_location' => 'string',
            'price_4_seater'   => 'numeric',
            'price_7_seater'   => 'numeric',
            'price_8_seater'   => 'numeric',
        ]);

        $route->update($request->only([
            'pickup_location',
            'dropoff_location',
            'price_4_seater',
            'price_7_seater',
            'price_8_seater',
            'is_active',
        ]));

        return response()->json([
            'message' => 'Route updated successfully',
            'route'   => $route
        ]);
    }

    // Delete a route (Admin only)
    public function destroy($id)
    {
        $route = Route::findOrFail($id);
        $route->delete();

        return response()->json(['message' => 'Route deleted successfully']);
    }
}