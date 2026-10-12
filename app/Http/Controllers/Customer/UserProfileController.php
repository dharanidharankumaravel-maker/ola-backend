<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerSavedPlace;
use App\Models\WalletTransaction;
use App\Services\Customer\CustomerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Module 2: User Profile & Account Settings
 */
class UserProfileController extends Controller
{
    protected CustomerAuthService $authService;

    public function __construct(CustomerAuthService $authService)
    {
        $this->authService = $authService;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2.0  GET /users/profile
    // ──────────────────────────────────────────────────────────────────────────
    public function getProfile(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $customer->refresh()->load('user');

        return $this->successResponse(
            $this->authService->formatCustomerProfile($customer),
            'Profile retrieved successfully'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2.1  PUT /users/profile (also supports PATCH and POST)
    // ──────────────────────────────────────────────────────────────────────────
    public function updateProfile(Request $request): JsonResponse
    {
        $this->parseMultipartIfPut($request);

        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $hasAvatarFile = ($request->hasFile('avatar') && $request->file('avatar') instanceof \Illuminate\Http\UploadedFile)
            || ($request->hasFile('profile_photo') && $request->file('profile_photo') instanceof \Illuminate\Http\UploadedFile)
            || ($request->hasFile('profile_image') && $request->file('profile_image') instanceof \Illuminate\Http\UploadedFile);

        $rules = [
            'name'              => ['nullable', 'string', 'max:255'],
            'email'             => [
                'nullable', 'email', 'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'avatarId'          => ['nullable', 'string', 'max:100'],
            'avatar_id'         => ['nullable', 'string', 'max:100'],
            'emergency_contact' => ['nullable', 'string', 'max:50'],
            'emergencyContact'  => ['nullable', 'string', 'max:50'],
        ];

        if ($hasAvatarFile) {
            $rules['avatar'] = ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:3072'];
        } else {
            $rules['avatar'] = ['nullable', 'string', 'max:255'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = [];

        if ($request->filled('name')) {
            $data['name'] = trim($request->input('name'));
        }
        if ($request->filled('email')) {
            $data['email'] = trim($request->input('email'));
        }

        // Avatar string (avatar, avatarId, avatar_id)
        $avatarString = $request->input('avatarId') ?? $request->input('avatar_id');
        if (!$avatarString && is_string($request->input('avatar')) && !empty(trim($request->input('avatar')))) {
            $avatarString = trim($request->input('avatar'));
        }
        if ($avatarString) {
            $data['avatar'] = $avatarString;
        }

        // Handle file upload
        $avatarFile = $request->file('avatar') ?? $request->file('profile_photo') ?? $request->file('profile_image');
        if ($avatarFile && $avatarFile->isValid()) {
            // Delete old avatar if local
            if ($customer->avatar && !filter_var($customer->avatar, FILTER_VALIDATE_URL)) {
                $old = ltrim(str_replace('/storage/', '', $customer->avatar), '/');
                if (Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }
            $path          = $avatarFile->store('profile_images', 'public');
            $data['avatar'] = '/storage/' . $path;
        }

        // Emergency contact in update profile
        $rawEmerg = $request->input('emergency_contact') ?? $request->input('emergencyContact');
        if ($rawEmerg !== null) {
            $cleaned = preg_replace('/[^\d]/', '', (string) $rawEmerg);
            if (strlen($cleaned) >= 10) {
                $data['emergency_contact'] = trim((string) $rawEmerg);
            }
        }

        if (!empty($data)) {
            $customer->update($data);

            // Mirror name/email to users table
            if ($customer->user) {
                $userUpdate = array_intersect_key($data, array_flip(['name', 'email', 'avatar']));
                if (!empty($userUpdate)) {
                    $customer->user->update($userUpdate);
                }
            }
        }

        $customer->refresh()->load('user');

        return $this->successResponse(
            $this->authService->formatCustomerProfile($customer),
            'Profile updated successfully'
        );
    }

    /**
     * Parses multipart/form-data for PUT/PATCH requests where PHP does not populate $_POST and $_FILES.
     */
    protected function parseMultipartIfPut(Request $request): void
    {
        $contentType = $request->header('Content-Type', '');
        if (str_contains($contentType, 'multipart/form-data') && in_array($request->method(), ['PUT', 'PATCH'])) {
            $rawContent = $request->getContent();
            if (empty($rawContent)) {
                return;
            }

            preg_match('/boundary=(.*)$/', $contentType, $matches);
            if (empty($matches[1])) {
                return;
            }
            $boundary = trim($matches[1], '"');

            $parts = array_slice(explode('--' . $boundary, $rawContent), 1);
            $parsedData = [];

            foreach ($parts as $part) {
                if ($part === "--\r\n" || $part === "--" || empty(trim($part))) {
                    continue;
                }

                $sections = explode("\r\n\r\n", $part, 2);
                if (count($sections) < 2) {
                    continue;
                }

                $headers = $sections[0];
                $body = substr($sections[1], 0, -2);

                if (preg_match('/name="([^"]+)"(?:;\s*filename="([^"]+)")?/', $headers, $matches)) {
                    $name = $matches[1];
                    $filename = $matches[2] ?? null;

                    if ($filename !== null) {
                        preg_match('/Content-Type:\s*([^\r\n]+)/', $headers, $mimeMatches);
                        $mimeType = $mimeMatches[1] ?? 'application/octet-stream';

                        $tempPath = tempnam(sys_get_temp_dir(), 'put_upload_');
                        file_put_contents($tempPath, $body);

                        $file = new \Illuminate\Http\UploadedFile(
                            $tempPath,
                            $filename,
                            $mimeType,
                            null,
                            true
                        );
                        $request->files->set($name, $file);
                    } else {
                        $parsedData[$name] = $body;
                    }
                }
            }

            if (!empty($parsedData)) {
                $request->merge($parsedData);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2.2  PATCH /users/preferences
    // ──────────────────────────────────────────────────────────────────────────
    public function updatePreferences(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $validator = Validator::make($request->all(), [
            'notifications'                     => ['nullable', 'array'],
            'notifications.rideUpdates'         => ['nullable', 'boolean'],
            'notifications.promotions'          => ['nullable', 'boolean'],
            'notifications.sosAlerts'           => ['nullable', 'boolean'],
            'notifications.appUpdates'          => ['nullable', 'boolean'],
            'location'                          => ['nullable', 'array'],
            'location.shareWithDriver'          => ['nullable', 'boolean'],
            'location.highAccuracyMode'         => ['nullable', 'boolean'],
            'location.backgroundTracking'       => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $notifPrefs    = $request->input('notifications');
        $locationPrefs = $request->input('location');

        // If flat preference keys were sent, organize them into categories
        if ($notifPrefs === null) {
            $flatNotifs = array_filter([
                'push_notifications' => $request->input('push_notifications'),
                'sms_updates'        => $request->input('sms_updates'),
                'promo_emails'       => $request->input('promo_emails'),
                'ride_receipts'      => $request->input('ride_receipts'),
            ], fn($v) => $v !== null);
            if (!empty($flatNotifs)) {
                $notifPrefs = $flatNotifs;
            }
        }

        if ($locationPrefs === null) {
            $flatLoc = array_filter([
                'precise_location'   => $request->input('precise_location'),
                'background_sharing' => $request->input('background_sharing'),
            ], fn($v) => $v !== null);
            if (!empty($flatLoc)) {
                $locationPrefs = $flatLoc;
            }
        }

        $current = [
            'notification_prefs' => $customer->notification_prefs ? (is_array($customer->notification_prefs) ? $customer->notification_prefs : json_decode($customer->notification_prefs, true)) : [],
            'location_prefs'     => $customer->location_prefs     ? (is_array($customer->location_prefs) ? $customer->location_prefs : json_decode($customer->location_prefs, true))     : [],
        ];

        if ($notifPrefs !== null) {
            $merged = array_merge($current['notification_prefs'], (array) $notifPrefs);
            $customer->notification_prefs = $merged;
        }
        if ($locationPrefs !== null) {
            $merged = array_merge($current['location_prefs'], (array) $locationPrefs);
            $customer->location_prefs = $merged;
        }

        $customer->save();

        return $this->successResponse([
            'notifications' => $customer->notification_prefs,
            'location'      => $customer->location_prefs,
        ], 'Preferences updated successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2.3  PUT /users/emergency-contact
    // ──────────────────────────────────────────────────────────────────────────
    public function setEmergencyContact(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        $rawPhone = (string) $request->input('phone');
        // Strip out +, spaces, dashes
        $cleaned = preg_replace('/[^\d]/', '', $rawPhone);
        if (strlen($cleaned) > 10 && str_starts_with($rawPhone, '+91')) {
            $cleaned = substr($cleaned, -10);
        } elseif (strlen($cleaned) > 10 && str_starts_with($cleaned, '91') && strlen($cleaned) === 12) {
            $cleaned = substr($cleaned, 2);
        }

        $validator = Validator::make(['phone' => $cleaned], [
            'phone' => ['required', 'string', 'regex:/^\d{10}$/'],
        ], [
            'phone.regex' => 'Emergency contact must be a valid 10-digit phone number.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $customer->update(['emergency_contact' => trim($rawPhone)]);

        return $this->successResponse([
            'emergencyContact' => $customer->fresh()->emergency_contact,
        ], 'Emergency contact saved successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2.4  DELETE /users/account
    // ──────────────────────────────────────────────────────────────────────────
    public function deleteAccount(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized.', 401);
        }

        DB::transaction(function () use ($customer) {
            // Revoke all tokens
            $customer->tokens()->update(['is_revoked' => true]);

            // Soft-wipe PII — anonymize rather than hard-delete to preserve ride history integrity
            $customer->update([
                'name'              => 'Deleted User',
                'email'             => null,
                'avatar'            => null,
                'emergency_contact' => null,
                'status'            => 'blocked',
            ]);

            if ($customer->user) {
                $customer->user->update([
                    'name'    => 'Deleted User',
                    'email'   => null,
                    'fcm_key' => null,
                    'status'  => 'blocked',
                ]);
            }
        });

        return $this->successResponse(null, 'Account deleted successfully.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper responses (duplicated here to avoid base-class coupling issues)
    // ──────────────────────────────────────────────────────────────────────────
    protected function successResponse($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $r = ['success' => true, 'statusCode' => $statusCode];
        if ($message) $r['message'] = $message;
        if ($data !== null) $r['data'] = $data;
        return response()->json($r, $statusCode);
    }

    protected function errorResponse(string $code, string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success'    => false,
            'statusCode' => $statusCode,
            'error'      => ['code' => $code, 'message' => $message],
        ], $statusCode);
    }
}
