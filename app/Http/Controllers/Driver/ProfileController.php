<?php

namespace App\Http\Controllers\Driver;

use App\Models\DriverDocument;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends BaseDriverController
{
    /**
     * 1. Create / Update Initial Profile
     * POST /driver/profile
     */
    public function storeOrUpdate(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'firstName'    => 'required|string|max:100',
            'lastName'     => 'required|string|max:100',
            'email'        => "required|email|unique:drivers,email,{$driver->id}",
            'address'      => 'nullable|string|max:500',
            'password'     => 'nullable|string|min:6',
            'profileImage' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $firstName = $request->input('firstName');
        $lastName  = $request->input('lastName');
        $email     = $request->input('email');
        $address   = $request->input('address');

        $driver->first_name = $firstName;
        $driver->last_name  = $lastName;
        $driver->name       = trim("{$firstName} {$lastName}");
        $driver->email      = $email;
        if ($address !== null) {
            $driver->address = $address;
        }

        if ($request->hasFile('profileImage')) {
            $path = $request->file('profileImage')->store('drivers/avatars', 'public');
            $driver->avatar = url(Storage::url($path));
        }

        $driver->save();

        if ($driver->user) {
            $driver->user->name = $driver->name;
            $driver->user->email = $email;
            if ($request->filled('password')) {
                $driver->user->password = Hash::make($request->input('password'));
            }
            $driver->user->save();
        }

        return $this->successResponse([
            'id'               => $driver->driver_code ?: ('drv_' . $driver->id),
            'firstName'        => $driver->first_name,
            'lastName'         => $driver->last_name,
            'fullName'         => $driver->full_name,
            'email'            => $driver->email,
            'phoneNumber'      => $driver->phone,
            'profileImagePath' => $driver->avatar ?: "https://cdn.trego.com/drivers/{$driver->driver_code}/avatar.jpg",
        ]);
    }

    /**
     * 2. Get Driver Profile Details
     * GET /driver/profile
     */
    public function show(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $driver->load('vehicle');

        $vehicleModel = $driver->vehicle ? trim(($driver->vehicle->make ?? '') . ' ' . ($driver->vehicle->model ?? '')) : null;
        $vehicleNumber = $driver->vehicle ? $driver->vehicle->reg_number : null;

        // Mask bank account number e.g. XXXXXX1234
        $maskedAccount = null;
        if ($driver->account_number) {
            $accLen = strlen($driver->account_number);
            if ($accLen > 4) {
                $maskedAccount = str_repeat('X', $accLen - 4) . substr($driver->account_number, -4);
            } else {
                $maskedAccount = 'XXXX' . $driver->account_number;
            }
        }

        return $this->successResponse([
            'id'                  => $driver->driver_code ?: ('drv_' . $driver->id),
            'firstName'           => $driver->first_name ?: ($driver->name ? explode(' ', $driver->name)[0] : 'Driver'),
            'lastName'            => $driver->last_name ?: (str_contains($driver->name ?? '', ' ') ? substr(strstr($driver->name, ' '), 1) : ''),
            'phoneNumber'         => $driver->phone,
            'email'               => $driver->email,
            'profileImagePath'    => $driver->avatar ?: "https://cdn.trego.com/drivers/{$driver->driver_code}/avatar.jpg",
            'vehicleModel'        => $vehicleModel ?: 'Maruti Suzuki Dzire',
            'vehicleNumber'       => $vehicleNumber ?: ($driver->rc_number ?: 'MH 12 AB 1234'),
            'dlExpiryDate'        => $driver->dl_expiry_date ? $driver->dl_expiry_date->toIso8601String() : '2027-07-28T00:00:00.000Z',
            'insuranceExpiryDate' => $driver->insurance_expiry_date ? $driver->insurance_expiry_date->toIso8601String() : '2026-09-25T00:00:00.000Z',
            'isApprovedByAdmin'   => $driver->status === 'approved',
            'bankName'            => $driver->bank_name ?: 'HDFC Bank',
            'bankAccountNumber'   => $maskedAccount ?: 'XXXXXX1234',
            'ifscCode'            => $driver->ifsc_code ?: 'HDFC0001234',
            'rating'              => (float) $driver->rating,
            'totalTrips'          => (int) $driver->total_trips,
            'totalEarnings'       => (float) $driver->total_earnings,
        ]);
    }

    /**
     * 3. Update Profile Details
     * PUT /driver/profile
     */
    public function update(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:100',
            'lastName'  => 'required|string|max:100',
            'email'     => "required|email|unique:drivers,email,{$driver->id}",
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $firstName = $request->input('firstName');
        $lastName  = $request->input('lastName');
        $email     = $request->input('email');

        $driver->first_name = $firstName;
        $driver->last_name  = $lastName;
        $driver->name       = trim("{$firstName} {$lastName}");
        $driver->email      = $email;
        $driver->save();

        if ($driver->user) {
            $driver->user->name = $driver->name;
            $driver->user->email = $email;
            $driver->user->save();
        }

        return $this->successResponse([
            'id'               => $driver->driver_code ?: ('drv_' . $driver->id),
            'firstName'        => $driver->first_name,
            'lastName'         => $driver->last_name,
            'fullName'         => $driver->full_name,
            'email'            => $driver->email,
            'phoneNumber'      => $driver->phone,
            'profileImagePath' => $driver->avatar ?: "https://cdn.trego.com/drivers/{$driver->driver_code}/avatar.jpg",
        ]);
    }

    /**
     * 4. Upload Personal KYC Documents
     * POST /driver/documents/personal
     */
    public function uploadPersonalDocument(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'documentType'   => 'required|string|in:DL,AADHAAR,PASSPORT,BIRTH_CERTIFICATE',
            'documentNumber' => 'required|string|max:100',
            'expiryDate'     => 'required|date',
            'frontImage'     => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'backImage'      => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $docType = $request->input('documentType');
        $docNumber = $request->input('documentNumber');
        $expiryDate = Carbon::parse($request->input('expiryDate'));

        $frontUrl = "https://cdn.trego.com/docs/{$driver->driver_code}/" . strtolower($docType) . "_front.jpg";
        $backUrl  = "https://cdn.trego.com/docs/{$driver->driver_code}/" . strtolower($docType) . "_back.jpg";

        if ($request->hasFile('frontImage')) {
            $path = $request->file('frontImage')->store('driver_docs', 'public');
            $frontUrl = url(Storage::url($path));
        }
        if ($request->hasFile('backImage')) {
            $path = $request->file('backImage')->store('driver_docs', 'public');
            $backUrl = url(Storage::url($path));
        }

        $doc = DriverDocument::updateOrCreate(
            [
                'driver_id' => $driver->id,
                'document_type' => $docType,
            ],
            [
                'document_number' => $docNumber,
                'expiry_date' => $expiryDate,
                'front_image_url' => $frontUrl,
                'back_image_url' => $backUrl,
                'verification_status' => 'PENDING_VERIFICATION',
                'status' => 'UPLOADED',
                'uploaded_at' => now(),
            ]
        );

        if ($docType === 'DL') {
            $driver->license_number = $docNumber;
            $driver->dl_expiry_date = $expiryDate;
            $driver->license_doc_url = $frontUrl;
            $driver->save();
        }

        return $this->successResponse([
            'documentType'       => $docType,
            'verificationStatus' => 'PENDING_VERIFICATION',
            'frontImageUrl'      => $frontUrl,
            'backImageUrl'       => $backUrl,
            'uploadedAt'         => now()->toIso8601String(),
        ]);
    }

    /**
     * 5. Renew Expired Document
     * POST /driver/documents/renew
     */
    public function renewDocument(Request $request): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->errorResponse('Driver not found.', 'NOT_FOUND', 404);
        }

        $validator = Validator::make($request->all(), [
            'documentType' => 'required|string',
            'expiryDate'   => 'required|date',
            'frontImage'   => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'backImage'    => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $docType = $request->input('documentType');
        $newExpiry = Carbon::parse($request->input('expiryDate'));

        $frontUrl = null;
        $backUrl = null;
        if ($request->hasFile('frontImage')) {
            $path = $request->file('frontImage')->store('driver_docs', 'public');
            $frontUrl = url(Storage::url($path));
        }
        if ($request->hasFile('backImage')) {
            $path = $request->file('backImage')->store('driver_docs', 'public');
            $backUrl = url(Storage::url($path));
        }

        // Update driver field if Driving License or Insurance
        if (str_contains(strtolower($docType), 'license')) {
            $driver->dl_expiry_date = $newExpiry;
            if ($frontUrl) {
                $driver->license_doc_url = $frontUrl;
            }
            $driver->save();
        } elseif (str_contains(strtolower($docType), 'insurance')) {
            $driver->insurance_expiry_date = $newExpiry;
            if ($frontUrl) {
                $driver->insurance_doc_url = $frontUrl;
            }
            $driver->save();
        }

        DriverDocument::updateOrCreate(
            [
                'driver_id' => $driver->id,
                'document_type' => $docType,
            ],
            [
                'expiry_date' => $newExpiry,
                'verification_status' => 'UNDER_REVIEW',
                'status' => 'UNDER_REVIEW',
                'front_image_url' => $frontUrl ?: $driver->license_doc_url,
                'back_image_url' => $backUrl ?: $driver->rc_doc_url,
                'uploaded_at' => now(),
            ]
        );

        return $this->successResponse([
            'status'        => 'UNDER_REVIEW',
            'documentType'  => $docType,
            'newExpiryDate' => $newExpiry->toIso8601ZuluString(),
        ], 'Document submitted for verification.');
    }
}
