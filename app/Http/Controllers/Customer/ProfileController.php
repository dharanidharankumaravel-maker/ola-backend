<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Customer\CustomerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    protected CustomerAuthService $authService;

    public function __construct(CustomerAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\Customer|null $customer */
        $customer = $request->attributes->get('customer');

        if (!$customer) {
            return $this->errorResponse('UNAUTHORIZED', 'Unauthorized. Customer session not found.', 401);
        }

        $customer->load('user');
        $user = $customer->user;

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
                Rule::unique('users', 'email')->when($user, fn($rule) => $rule->ignore($user->id)),
            ],
            'phone' => [
                'nullable', 'regex:/^\d{10}$/',
                Rule::unique('customers', 'phone')->ignore($customer->id),
                Rule::unique('users', 'phone')->when($user, fn($rule) => $rule->ignore($user->id)),
            ],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'phone.regex' => 'The phone number must be exactly 10 digits.',
            'email.unique' => 'The email has already been taken.',
            'phone.unique' => 'The phone number has already been taken.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $validated = $validator->validated();
        $avatarUrl = null;

        \Log::info('Profile update request', [
            'has_file' => $request->hasFile('profile_image'),
            'files' => $request->allFiles(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'all_input' => $request->all(),
            'file_keys' => array_keys($request->allFiles()),
            '_FILES_global' => $_FILES ?? [],
            'request_body_preview' => substr($request->getContent(), 0, 500),
        ]);

        // Also check for common alternative field names
        $possibleKeys = ['profile_image', 'avatar', 'image', 'profileImage', 'profile_pic', 'photo'];
        $foundKey = null;
        foreach ($possibleKeys as $key) {
            if ($request->hasFile($key)) {
                $foundKey = $key;
                \Log::info("Found file under key: {$key}");
                break;
            }
        }

        $fileKey = $foundKey ?? 'profile_image';

        // Fallback: manually parse multipart if PHP didn't parse it
        if (!$request->hasFile($fileKey) && $request->header('Content-Type') && Str::startsWith($request->header('Content-Type'), 'multipart/form-data')) {
            $parsedFile = $this->parseMultipartFile($request->getContent(), $fileKey, $request->header('Content-Type'));
            if ($parsedFile) {
                \Log::info('Manually parsed file', ['fileKey' => $fileKey, 'parsed' => $parsedFile]);
                // Create a temporary UploadedFile-like object
                $file = $parsedFile;
            }
        } elseif ($request->hasFile($fileKey)) {
            $file = $request->file($fileKey);
        } else {
            $file = null;
        }

        if ($file) {
            $isParsedArray = is_array($file);

            if ($customer->avatar && !filter_var($customer->avatar, FILTER_VALIDATE_URL)) {
                $oldPath = ltrim(str_replace('/storage/', '', $customer->avatar), '/');
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            if ($isParsedArray) {
                $avatarUrl = $this->storeParsedFile($file);
            } else {
                $path = $file->store('profile_images', 'public');
                $avatarUrl = '/storage/' . $path;
            }

            \Log::info('Profile image stored', ['path' => $avatarUrl, 'isParsed' => $isParsedArray]);
        }

        $customerData = [];
        if (array_key_exists('name', $validated)) $customerData['name'] = trim($validated['name']);
        if (array_key_exists('email', $validated)) $customerData['email'] = $validated['email'] ? trim($validated['email']) : null;
        if (array_key_exists('phone', $validated)) $customerData['phone'] = trim($validated['phone']);
        if ($avatarUrl) $customerData['avatar'] = $avatarUrl;

        \Log::info('Customer data to update', ['customerData' => $customerData]);

        if (!empty($customerData)) $customer->update($customerData);

        if ($user) {
            $userData = [];
            if (array_key_exists('name', $validated)) $userData['name'] = trim($validated['name']);
            if (array_key_exists('email', $validated)) $userData['email'] = $validated['email'] ? trim($validated['email']) : null;
            if (array_key_exists('phone', $validated)) $userData['phone'] = trim($validated['phone']);
            if ($avatarUrl) $userData['avatar'] = $avatarUrl;

            \Log::info('User data to update', ['userData' => $userData]);

            if (!empty($userData)) $user->update($userData);
        }

        $customer->refresh()->load('user');

        return $this->successResponse(
            $this->authService->formatCustomerProfile($customer),
            'Profile updated successfully',
            200
        );
    }

    protected function successResponse($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = ['success' => true, 'statusCode' => $statusCode];
        if ($message) $response['message'] = $message;
        if ($data) $response['data'] = $data;
        return response()->json($response, $statusCode);
    }

    protected function errorResponse(string $code, string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false, 'statusCode' => $statusCode,
            'error' => ['code' => $code, 'message' => $message],
        ], $statusCode);
    }

    protected function parseMultipartFile(string $body, string $fieldName, string $contentType): ?array
    {
        // Extract boundary from Content-Type header
        if (!preg_match('/boundary=([^;]+)/', $contentType, $matches)) {
            return null;
        }
        $boundary = '--' . trim($matches[1]);

        // Split by boundary
        $parts = explode($boundary, $body);

        foreach ($parts as $part) {
            // Look for the field name in Content-Disposition
            if (strpos($part, 'name="' . $fieldName . '"') === false && strpos($part, "name='{$fieldName}'") === false) {
                continue;
            }

            // Check if it's a file (has filename)
            if (preg_match('/filename=["\']([^"\']+)["\']/', $part, $fileMatches)) {
                $filename = $fileMatches[1];

                // Extract file content (after double CRLF)
                $contentStart = strpos($part, "\r\n\r\n");
                if ($contentStart === false) {
                    $contentStart = strpos($part, "\n\n");
                }
                if ($contentStart === false) {
                    continue;
                }

                $fileContent = substr($part, $contentStart + 4);
                // Remove trailing CRLF and boundary
                $fileContent = rtrim($fileContent, "\r\n");

                // Extract MIME type
                $mimeType = 'application/octet-stream';
                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $part, $mimeMatches)) {
                    $mimeType = trim($mimeMatches[1]);
                }

                // Save to temporary file
                $tempPath = sys_get_temp_dir() . '/laravel_upload_' . uniqid() . '_' . basename($filename);
                file_put_contents($tempPath, $fileContent);

                return [
                    'path' => $tempPath,
                    'name' => $filename,
                    'type' => $mimeType,
                    'size' => strlen($fileContent),
                ];
            }
        }

        return null;
    }

    protected function storeParsedFile(array $parsedFile, string $directory = 'profile_images'): string
    {
        $contents = file_get_contents($parsedFile['path']);
        $extension = pathinfo($parsedFile['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = Str::random(40) . '.' . $extension;
        $path = $directory . '/' . $filename;

        Storage::disk('public')->put($path, $contents);
        @unlink($parsedFile['path']);

        return '/storage/' . $path;
    }
}