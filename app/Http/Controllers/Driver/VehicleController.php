<?php

namespace App\Http\Controllers\Driver;

use App\Models\DriverDocument;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VehicleController extends BaseDriverController
{
    /**
     * 1. Register Vehicle
     * POST /driver/vehicle
     */
    public function registerVehicle(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'serviceType'      => 'required|string|max:100',
            'brand'            => 'required|string|max:100',
            'model'            => 'required|string|max:100',
            'manufacturerYear' => 'nullable|string|max:10',
            'numberPlate'      => 'required|string|max:50',
            'color'            => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $serviceType = $request->input('serviceType');
        $brand = $request->input('brand');
        $model = $request->input('model');
        $numberPlate = $request->input('numberPlate');
        $year = $request->input('manufacturerYear');
        $color = $request->input('color');

        // Map serviceType to vehicle_type enum ('bike', 'auto', 'mini', 'sedan', 'suv')
        $lowerService = strtolower($serviceType);
        $vehicleType = 'sedan';
        if (str_contains($lowerService, 'bike')) {
            $vehicleType = 'bike';
        } elseif (str_contains($lowerService, 'auto')) {
            $vehicleType = 'auto';
        } elseif (str_contains($lowerService, 'mini')) {
            $vehicleType = 'mini';
        } elseif (str_contains($lowerService, 'suv')) {
            $vehicleType = 'suv';
        }

        $vehicle = Vehicle::where('reg_number', $numberPlate)->first();
        if (!$vehicle) {
            $vehicle = Vehicle::create([
                'driver_id'    => $driver->id,
                'reg_number'   => $numberPlate,
                'vehicle_type' => $vehicleType,
                'service_type' => $serviceType,
                'make'         => $brand,
                'model'        => $model,
                'year'         => $year,
                'color'        => $color,
                'owner_name'   => $driver->name ?: 'Driver',
                'status'       => 'active',
            ]);
        } else {
            $vehicle->update([
                'driver_id'    => $driver->id,
                'service_type' => $serviceType,
                'make'         => $brand,
                'model'        => $model,
                'year'         => $year,
                'color'        => $color,
            ]);
        }

        $driver->vehicle_id = $vehicle->id;
        $driver->rc_number = $numberPlate;
        $driver->save();

        $vehicleIdStr = 'veh_' . (49100 + $vehicle->id);

        return $this->successResponse([
            'vehicleId'     => $vehicleIdStr,
            'serviceType'   => $serviceType,
            'vehicleModel'  => trim("{$brand} {$model}"),
            'vehicleNumber' => $numberPlate,
            'status'        => 'PENDING_DOCS',
        ], 'Operation completed successfully.', 201);
    }

    /**
     * 2. Upload Vehicle Documents
     * POST /driver/vehicle/documents
     */
    public function uploadVehicleDocument(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'vehicleId'          => 'nullable|string',
            'documentCategory'   => 'required|string|in:RC_BOOK,INSURANCE,OWNER_CERT,PUC',
            'policyOrCertNumber' => 'nullable|string|max:100',
            'expiryDate'         => 'nullable|date',
            'frontImage'         => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'backImage'          => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $category = $request->input('documentCategory');
        $certNumber = $request->input('policyOrCertNumber');
        $expiryDate = $request->input('expiryDate') ? Carbon::parse($request->input('expiryDate')) : null;

        $frontUrl = null;
        $backUrl = null;
        if ($request->hasFile('frontImage')) {
            $path = $request->file('frontImage')->store('vehicle_docs', 'public');
            $frontUrl = url(Storage::url($path));
        }
        if ($request->hasFile('backImage')) {
            $path = $request->file('backImage')->store('vehicle_docs', 'public');
            $backUrl = url(Storage::url($path));
        }

        DriverDocument::updateOrCreate(
            [
                'driver_id'     => $driver->id,
                'vehicle_id'    => $driver->vehicle_id,
                'document_type' => $category,
            ],
            [
                'document_number'     => $certNumber,
                'expiry_date'         => $expiryDate,
                'front_image_url'     => $frontUrl,
                'back_image_url'      => $backUrl,
                'verification_status' => 'PENDING_APPROVAL',
                'status'              => 'UPLOADED',
                'uploaded_at'         => now(),
            ]
        );

        if ($category === 'RC_BOOK' && $certNumber) {
            $driver->rc_number = $certNumber;
            if ($frontUrl) $driver->rc_doc_url = $frontUrl;
            $driver->save();
        } elseif ($category === 'INSURANCE') {
            if ($expiryDate) $driver->insurance_expiry_date = $expiryDate;
            if ($frontUrl) $driver->insurance_doc_url = $frontUrl;
            $driver->save();
        }

        return $this->successResponse([
            'documentCategory'   => $category,
            'status'             => 'UPLOADED',
            'verificationStatus' => 'PENDING_APPROVAL',
        ]);
    }
}
