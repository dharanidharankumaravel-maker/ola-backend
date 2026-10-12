<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class VehicleTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $vehicleTypes = Vehicle::where('status', 'active')
            ->select('vehicle_type')
            ->distinct()
            ->pluck('vehicle_type')
            ->map(function ($type) {
                $config = $this->getVehicleTypeConfig($type);
                return array_merge(['type' => $type, 'is_active' => true], $config);
            })
            ->values();

        return $this->successResponse($vehicleTypes, 'Vehicle types retrieved successfully', 200);
    }

    protected function getVehicleTypeConfig(string $type): array
    {
        $configs = [
            'bike' => [
                'name' => 'Bike',
                'description' => 'Quick solo rides',
                'capacity' => 1,
                'icon' => 'bike',
                'base_fare' => 20.00,
                'per_km' => 8.00,
                'per_min' => 1.50,
            ],
            'auto' => [
                'name' => 'Auto',
                'description' => 'Affordable 3-seater',
                'capacity' => 3,
                'icon' => 'auto',
                'base_fare' => 30.00,
                'per_km' => 12.00,
                'per_min' => 2.00,
            ],
            'mini' => [
                'name' => 'Mini',
                'description' => 'Compact 4-seater car',
                'capacity' => 4,
                'icon' => 'mini',
                'base_fare' => 50.00,
                'per_km' => 15.00,
                'per_min' => 2.50,
            ],
            'sedan' => [
                'name' => 'Sedan',
                'description' => 'Comfortable 4-seater',
                'capacity' => 4,
                'icon' => 'sedan',
                'base_fare' => 70.00,
                'per_km' => 18.00,
                'per_min' => 3.00,
            ],
            'suv' => [
                'name' => 'SUV',
                'description' => 'Spacious 6-seater',
                'capacity' => 6,
                'icon' => 'suv',
                'base_fare' => 100.00,
                'per_km' => 22.00,
                'per_min' => 4.00,
            ],
        ];

        return $configs[$type] ?? [
            'name' => ucfirst($type),
            'description' => '',
            'capacity' => 4,
            'icon' => $type,
            'base_fare' => 50.00,
            'per_km' => 15.00,
            'per_min' => 2.50,
        ];
    }

    protected function successResponse($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = ['success' => true, 'statusCode' => $statusCode];
        if ($message) $response['message'] = $message;
        if ($data) $response['data'] = $data;
        return response()->json($response, $statusCode);
    }
}