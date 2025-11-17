<?php

namespace App\Utils;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\MessageBag;

class ResponseUtils
{
    /**
     * Create a success response
     *
     * @param mixed $data The response data
     * @param string $message Success message
     * @param int $code HTTP status code
     * @return JsonResponse
     */
    public static function success($data = null, string $message = 'Success', int $code = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Create an error response
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param mixed $errors Additional error details
     * @return JsonResponse
     */
    public static function error(string $message, int $code = Response::HTTP_BAD_REQUEST, $errors = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];

        if ($errors instanceof MessageBag) {
            $response['errors'] = $errors->toArray();
        } elseif ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Create a validation error response
     *
     * @param MessageBag|array $errors Validation errors
     * @param string $message Error message
     * @return JsonResponse
     */
    public static function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Create a not found response
     *
     * @param string $message Not found message
     * @return JsonResponse
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Create an unauthorized response
     *
     * @param string $message Unauthorized message
     * @return JsonResponse
     */
    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Create a forbidden response
     *
     * @param string $message Forbidden message
     * @return JsonResponse
     */
    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, Response::HTTP_FORBIDDEN);
    }
}