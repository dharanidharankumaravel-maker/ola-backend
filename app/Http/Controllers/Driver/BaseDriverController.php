<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseDriverController extends Controller
{
    /**
     * Standard success response structure.
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @param array|null $meta
     * @return JsonResponse
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operation completed successfully.',
        int $statusCode = 200,
        ?array $meta = null
    ): JsonResponse {
        $response = [
            'success' => true,
            'statusCode' => $statusCode,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Standard error response structure.
     *
     * @param string $message
     * @param string $error
     * @param int $statusCode
     * @param array $errors
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message,
        string $error = 'BAD_REQUEST',
        int $statusCode = 400,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'statusCode' => $statusCode,
            'error' => $error,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Standard validation error response structure.
     *
     * @param Validator $validator
     * @return JsonResponse
     */
    protected function validationErrorResponse(Validator $validator): JsonResponse
    {
        $formattedErrors = [];
        $failed = $validator->failed();
        $messages = $validator->errors();

        foreach ($failed as $field => $rules) {
            foreach ($rules as $ruleName => $ruleParams) {
                $ruleLower = strtolower($ruleName);
                $formattedErrors[] = [
                    'field' => $field,
                    'rule' => $ruleLower,
                    'message' => $messages->first($field) ?: "{$field} is invalid.",
                ];
                break;
            }
        }

        $firstMessage = $validator->errors()->first() ?: 'Validation error occurred.';

        return response()->json([
            'success' => false,
            'statusCode' => 400,
            'error' => 'BAD_REQUEST',
            'message' => 'Validation failed: ' . $firstMessage,
            'errors' => $formattedErrors,
        ], 400);
    }

    /**
     * Get authenticated Driver instance from Request.
     *
     * @param Request $request
     * @return Driver|null
     */
    protected function getAuthenticatedDriver(Request $request): ?Driver
    {
        $driver = $request->attributes->get('driver');
        if ($driver instanceof Driver) {
            return $driver;
        }

        if (auth()->check()) {
            return Driver::where('user_id', auth()->id())->first();
        }

        return null;
    }
}
